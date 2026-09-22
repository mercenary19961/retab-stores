import Button from '@/components/admin/button';
import ConfirmDeleteButton from '@/components/admin/confirm-delete-button';
import HeroCropPreview from '@/components/admin/hero-crop-preview';
import HeroLinkPicker, { type LinkTargets } from '@/components/admin/hero-link-picker';
import Modal from '@/components/admin/modal';
import StatusBadge from '@/components/admin/status-badge';
import StatusToggle from '@/components/admin/status-toggle';
import { discardDraft, listDrafts, useFormDraft, type DraftSummary } from '@/hooks/use-form-draft';
import { useAdminT } from '@/i18n/use-admin-t';
import AdminLayout from '@/layouts/admin-layout';
import { CARD } from '@/lib/admin-ui';
import { startUpload } from '@/lib/uploads';
import { posterFromVideo, videoSupport } from '@/lib/video-poster';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Film, GalleryHorizontal, GripVertical, Image as ImageIcon, Lock, Pencil, Plus, RotateCcw, X } from 'lucide-react';
import { useEffect, useState } from 'react';

interface Slide {
    id: number;
    kind: 'image' | 'video';
    image: string | null;
    image_full: string | null;
    image_mobile: string | null;
    image_mobile_full: string | null;
    video: string | null;
    video_poster: string | null;
    focal_x: number;
    focal_y: number;
    focal_mobile_x: number;
    focal_mobile_y: number;
    href: string | null;
    alt_ar: string | null;
    alt_en: string | null;
    is_active: boolean;
    starts_at: string | null;
    ends_at: string | null;
    sort_order: number;
    state: string;
}

/** One entry of the composed hero, exactly as the storefront receives it. */
interface PreviewItem {
    id: string;
    kind: 'image' | 'video';
    image: string | null;
    image_mobile: string | null;
    video: string | null;
    href: string | null;
    alt_ar: string | null;
    alt_en: string | null;
}

/** `datetime-local` wants `YYYY-MM-DDTHH:mm`; the server ships ISO-8601. */
const toInput = (iso: string | null) => (iso ? iso.slice(0, 16) : '');

export default function HeroIndex({
    slides,
    campaignBanners,
    mode,
    modes,
    preview,
    videoMaxMb,
    linkTargets,
    canManage,
}: {
    slides: Slide[];
    campaignBanners: PreviewItem[];
    mode: string;
    modes: string[];
    preview: PreviewItem[];
    videoMaxMb: number;
    linkTargets: LinkTargets;
    canManage: boolean;
}) {
    const { t, i18n } = useAdminT();
    const [editing, setEditing] = useState<Slide | null>(null);
    const [open, setOpen] = useState(false);

    const form = useForm<{
        kind: 'image' | 'video';
        image: File | null;
        image_mobile: File | null;
        video: File | null;
        video_poster: File | null;
        href: string;
        alt_ar: string;
        alt_en: string;
        is_active: boolean;
        focal_x: number;
        focal_y: number;
        focal_mobile_x: number;
        focal_mobile_y: number;
        starts_at: string;
        ends_at: string;
        sort_order: number;
    }>({
        kind: 'image',
        image: null,
        image_mobile: null,
        video: null,
        video_poster: null,
        href: '',
        alt_ar: '',
        alt_en: '',
        is_active: true,
        focal_x: 50,
        focal_y: 50,
        focal_mobile_x: 50,
        focal_mobile_y: 50,
        starts_at: '',
        ends_at: '',
        sort_order: 0,
    });

    /*
     * A local preview of whatever art is currently chosen: the object URL of a
     * freshly picked file, or the stored image when editing and nothing new was
     * picked. This is what makes the crop visible BEFORE saving, which was the
     * whole complaint.
     */
    const [preview_, setPreview_] = useState<string | null>(null);
    /*
     * 🔴 The phone file needs its OWN preview URL. Without one the phone panel
     * fell back to the desktop file, so a client who uploaded phone art watched
     * it be ignored — which is exactly what was reported.
     */
    const [phonePreview, setPhonePreview] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    /*
     * 🔴 Whether `preview_` is a VIDEO, so the crop editor draws it with <video>.
     *
     * The editor used to be handed the captured poster instead, which meant a
     * failed frame grab left it showing its "choose a picture or video" empty
     * state - identical, from the client's side, to the upload not working. The
     * video is on their machine regardless, so the editor must not depend on the
     * grab succeeding.
     */
    const [previewVideo, setPreviewVideo] = useState(false);
    /** The grab failed, so the storefront needs a first frame supplied by hand. */
    const [posterFailed, setPosterFailed] = useState(false);
    /*
     * 🔴 This browser cannot open the chosen video AT ALL (no metadata, so not
     * merely no frame). Client-reported: two black crop boxes both claiming the
     * art "fits exactly", which is what the box says when it has no dimensions.
     *
     * When this is true the editor must stop trying to render the video and use
     * the first-frame picture instead - which is precisely what the client had
     * already supplied by hand, and watched be ignored.
     */
    const [videoUnplayable, setVideoUnplayable] = useState(false);
    /*
     * 🔑 WHY it is unplayable, asked of the browser rather than guessed.
     *
     * 'noH264' means this browser cannot play H.264 at ALL, so no MP4 will ever
     * preview here and the advice is to use a different browser. 'file' means
     * H.264 works but this file was still refused, which points at its profile
     * level, resolution or an HEVC track wearing an .mp4 extension. Two very
     * different messages, and only the browser can tell them apart.
     */
    const [videoDiagnosis, setVideoDiagnosis] = useState<'noH264' | 'file' | 'csp' | null>(null);
    /** Short technical line, so a report carries its own cause. */
    const [videoDetail, setVideoDetail] = useState('');

    // ⚠️ Object URLs are leaked until revoked, and this dialog can be opened
    // dozens of times in a session while choosing artwork.
    useEffect(() => {
        if (!preview_?.startsWith('blob:')) return;

        return () => URL.revokeObjectURL(preview_);
    }, [preview_]);

    useEffect(() => {
        if (!phonePreview?.startsWith('blob:')) return;

        return () => URL.revokeObjectURL(phonePreview);
    }, [phonePreview]);

    const draft = useFormDraft({
        key: `hero.${editing?.id ?? 'new'}`,
        data: form.data,
        setData: form.setData as unknown as (values: typeof form.data) => void,
        // Only while the dialog is open: a closed form must neither save nor
        // restore, or opening "New slide" would inherit the last edit.
        active: open,
    });

    /*
     * 🔴 Unfinished work has to be visible from the PAGE, not only from inside the
     * dialog. A refresh closes the modal, so the client saw an untouched page and
     * concluded everything was lost - the draft was there the whole time, with
     * nothing on screen saying so. This is that missing signal.
     */
    const [drafts, setDrafts] = useState<DraftSummary[]>([]);
    // Read in an effect, never during render: the SSR sidecar has no localStorage.
    const refreshDrafts = () => setDrafts(listDrafts('hero'));
    useEffect(refreshDrafts, []);
    // A save clears its draft and a delete can orphan one, so re-read whenever the
    // server sends fresh rows rather than trying to remember every path that changes them.
    useEffect(refreshDrafts, [slides]);

    const openFor = (row: Slide | null) => {
        setEditing(row);
        form.setData({
            kind: row?.kind ?? 'image',
            // Files are never pre-filled: a file input cannot be given a value, and
            // the server keeps whatever is already stored when none is sent.
            image: null,
            image_mobile: null,
            video: null,
            video_poster: null,
            href: row?.href ?? '',
            alt_ar: row?.alt_ar ?? '',
            alt_en: row?.alt_en ?? '',
            is_active: row?.is_active ?? true,
            focal_x: row?.focal_x ?? 50,
            focal_y: row?.focal_y ?? 50,
            focal_mobile_x: row?.focal_mobile_x ?? 50,
            focal_mobile_y: row?.focal_mobile_y ?? 50,
            starts_at: toInput(row?.starts_at ?? null),
            ends_at: toInput(row?.ends_at ?? null),
            sort_order: row?.sort_order ?? 0,
        });
        form.clearErrors();
        // Editing: show the art that is already stored, so the focal point can be
        // adjusted without re-uploading anything.
        // An existing video previews the STORED VIDEO, not its poster: the crop
        // box should show what the storefront actually renders.
        setPreview_(row ? (row.kind === 'video' ? (row.video ?? row.video_poster) : row.image_full) : null);
        setPreviewVideo(row?.kind === 'video' && !!row.video);
        setPosterFailed(false);
        setVideoUnplayable(false);
        setVideoDiagnosis(null);
        setVideoDetail('');
        setPhonePreview(row?.image_mobile_full ?? null);
        setOpen(true);
    };

    /**
     * Reopen the dialog on whichever record the draft belongs to; the hook then
     * puts the typed values back.
     *
     * ⚠️ A draft can outlive its slide (edited, then deleted). Opening a dialog for
     * a row that no longer exists would save a NEW slide carrying the old one's
     * text, so the draft is thrown away instead and the client told why.
     */
    /*
     * ⚠️ EVERY exit goes through here. Cancel used to call `setOpen(false)`
     * directly, so a cancelled draft was kept but not OFFERED until the next page
     * load - the exact invisibility this feature exists to fix.
     */
    const closeDialog = () => {
        setOpen(false);
        refreshDrafts();
    };

    /*
     * 🔑 CANCEL MEANS CANCEL (client's call). Keeping the draft here was the
     * original design - "someone who hits Cancel by accident is who this is for" -
     * and it was wrong: pressing a button labelled Cancel is a deliberate act, and
     * having the work reappear afterwards reads as the dialog refusing to let go.
     *
     * ⚠️ The ×, Escape and the backdrop deliberately still KEEP the draft. Those
     * are ambiguous or easily hit by accident, so they stay the forgiving path and
     * the resume button still covers them.
     */
    const cancelDialog = () => {
        draft.clear();
        closeDialog();
    };

    const resumeDraft = (d: DraftSummary) => {
        if (d.id === 'new') {
            openFor(null);

            return;
        }

        const row = slides.find((s) => String(s.id) === d.id);
        if (!row) {
            discardDraft('hero', d.id);
            refreshDrafts();

            return;
        }

        openFor(row);
    };

    /**
     * Choosing artwork: keep a local preview so the crop is visible immediately.
     */
    const chooseImage = (f: File | null) => {
        form.setData('image', f);
        setPreview_(f ? URL.createObjectURL(f) : null);
        setPreviewVideo(false);
    };

    const choosePhoneImage = (f: File | null) => {
        form.setData('image_mobile', f);
        setPhonePreview(f ? URL.createObjectURL(f) : null);
    };

    /**
     * Choosing a video: take its first frame as the poster automatically.
     *
     * 🔴 This is the fix for "the video doesn't show". A <video> with no poster
     * paints NOTHING until it has buffered, so a posterless hero video is a flat
     * block of background colour for the length of the download — which is what
     * the client saw and reasonably read as broken. The file is already on their
     * machine here, so we take the frame rather than asking them for one.
     *
     * Best effort: if it fails they simply have no poster, exactly as before, and
     * can still supply one by hand.
     */
    const chooseVideo = async (f: File | null) => {
        form.setData('video', f);
        setPosterFailed(false);

        if (!f) {
            setPreview_(null);
            setPreviewVideo(false);

            return;
        }

        // 🔑 Shown IMMEDIATELY, from the file itself. Nothing here waits on the
        // frame grab, which can take seconds on a large video and can fail outright.
        setPreview_(URL.createObjectURL(f));
        setPreviewVideo(true);
        setVideoUnplayable(false);
        setVideoDiagnosis(null);
        setVideoDetail('');

        // The poster still matters, but for the STOREFRONT, not for this editor:
        // a posterless hero video paints nothing while it buffers.
        setBusy(true);
        try {
            const result = await posterFromVideo(f);

            if (result.poster) {
                form.setData('video_poster', result.poster);

                return;
            }

            setPosterFailed(true);

            // ⚠️ NO DIMENSIONS means metadata never arrived, i.e. this browser
            // cannot open the file - so the crop boxes would stay black however
            // long we waited. Anything else means the video plays and only the
            // still could not be taken, which is cosmetic.
            if (!result.width || !result.height) {
                const support = videoSupport();

                setVideoUnplayable(true);
                // ⚠️ CSP first: it is OUR fault, and it presents identically to an
                // unsupported codec, which is exactly how it went misdiagnosed.
                setVideoDiagnosis(result.reason === 'csp' ? 'csp' : support.h264 ? 'file' : 'noH264');
                // Deliberately terse and untranslated: it exists to be pasted into
                // a message to us, not to be read as prose.
                setVideoDetail(
                    [
                        `error ${result.mediaError ?? '-'}`,
                        result.reason ?? '-',
                        `h264 ${support.h264 ? 'yes' : 'NO'}`,
                        `hevc ${support.hevc ? 'yes' : 'no'}`,
                        f.type || 'unknown type',
                    ].join(' · '),
                );
                setPreviewVideo(false);
                // Fall back to a first frame if one is already attached; otherwise
                // choosePoster() picks it up the moment the client supplies one.
                setPreview_(form.data.video_poster instanceof File ? URL.createObjectURL(form.data.video_poster) : null);
            }

            // Left for diagnosis: the client's browser is the only place this
            // reproduces, so the cause has to be visible from their console.
            console.warn('[hero] could not read a frame from this video', {
                reason: result.reason,
                mediaError: result.mediaError,
                width: result.width,
                height: result.height,
                type: f.type,
                size: f.size,
                support: videoSupport(),
                ua: navigator.userAgent,
            });
        } finally {
            setBusy(false);
        }
    };

    /**
     * The first-frame picture.
     *
     * 🔑 When the video cannot be rendered here, this becomes the crop preview.
     * The client supplied exactly this image for exactly that reason and it was
     * ignored, because the preview was hard-wired to the video.
     */
    const choosePoster = (f: File | null) => {
        form.setData('video_poster', f);
        if (!videoUnplayable) return;

        setPreview_(f ? URL.createObjectURL(f) : null);
        setPreviewVideo(false);
    };

    /*
     * ⚠️ POST for BOTH create and update, never PUT. The body is multipart and PHP
     * does not parse a multipart PUT, so the files would arrive empty — the same
     * reason product images live on their own POST endpoint.
     */
    const submit = () => {
        /*
         * 🔴 The boolean MUST go as 1/0. multipart/form-data carries strings
         * only, so Inertia serialises `true` as the literal "true" — which
         * Laravel's `boolean` rule rejects, because it accepts only
         * true/false/1/0/"1"/"0". The whole form then 302s back with an error
         * and nothing is saved.
         *
         * Caught in a browser, not by the suite: the PHPUnit test omitted the
         * field entirely and so exercised the default instead of this path.
         */
        form.transform((data) => ({
            ...data,
            is_active: data.is_active ? 1 : 0,
            focal_x: Math.round(data.focal_x),
            focal_y: Math.round(data.focal_y),
            focal_mobile_x: Math.round(data.focal_mobile_x),
            focal_mobile_y: Math.round(data.focal_mobile_y),
        }));

        /*
         * 🔑 Sent in the BACKGROUND, not through `router.post`, so a large video
         * does not pin the admin to this dialog (client's request). The dialog
         * closes at once and the upload reports itself from the tray.
         */
        const body = new FormData();
        const values: Record<string, unknown> = {
            ...form.data,
            is_active: form.data.is_active ? 1 : 0,
            focal_x: Math.round(form.data.focal_x),
            focal_y: Math.round(form.data.focal_y),
            focal_mobile_x: Math.round(form.data.focal_mobile_x),
            focal_mobile_y: Math.round(form.data.focal_mobile_y),
        };

        for (const [key, value] of Object.entries(values)) {
            // ⚠️ A null file must be OMITTED, not sent as the string "null": the
            // upload rules are `nullable|file`, and a string would fail the file
            // rule and reject the whole form (the 2026-09-21 required_if bug).
            if (value === null || value === undefined) continue;
            body.append(key, value instanceof File ? value : String(value));
        }

        startUpload({
            url: editing ? `/admin/hero/${editing.id}` : '/admin/hero',
            body,
            label: t(editing ? 'admin.uploads.savingSlide' : 'admin.uploads.newSlide'),
            fallbackError: t('admin.uploads.failed'),
            onSuccess: () => {
                // 🔑 Cleared on SUCCESS only, exactly as before — a failure must
                // leave the typed work recoverable from the resume button.
                draft.clear();
                refreshDrafts();
                // Only reload if still looking at the hero; elsewhere the toast is
                // the whole story and a reload would yank the page they moved to.
                if (window.location.pathname.startsWith('/admin/hero')) {
                    router.reload({ only: ['slides', 'preview', 'drafts'] });
                }
            },
            onFailure: () => refreshDrafts(),
        });

        closeDialog();
    };

    const text = (name: 'href' | 'alt_ar' | 'alt_en' | 'starts_at' | 'ends_at' | 'sort_order', label: string, type = 'text', hint?: string) => (
        <label className="block">
            <span className="text-sm text-neutral-300">{label}</span>
            <input
                type={type}
                value={String(form.data[name] ?? '')}
                onChange={(e) => form.setData(name, (type === 'number' ? Number(e.target.value) : e.target.value) as never)}
                className="focus:border-brand-gold mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-white outline-none"
            />
            {hint && <span className="mt-1 block text-xs text-neutral-500">{hint}</span>}
            {form.errors[name] && <span className="text-xs text-red-400">{form.errors[name]}</span>}
        </label>
    );

    const file = (name: 'image' | 'image_mobile' | 'video' | 'video_poster', label: string, accept: string, hint: string) => (
        <label className="block">
            <span className="text-sm text-neutral-300">{label}</span>
            <input
                type="file"
                accept={accept}
                aria-label={label}
                onChange={(e) => {
                    const f = e.target.files?.[0] ?? null;
                    if (name === 'image') chooseImage(f);
                    else if (name === 'image_mobile') choosePhoneImage(f);
                    else if (name === 'video') chooseVideo(f);
                    // All four are handled, so there is no fallback branch left:
                    // `name` narrows to never and tsc rejects a setData on it.
                    else choosePoster(f);
                }}
                className="mt-1 w-full rounded-lg border border-neutral-700 bg-neutral-950 px-3 py-2 text-sm text-neutral-300 outline-none file:mr-3 file:rounded file:border-0 file:bg-neutral-800 file:px-3 file:py-1 file:text-neutral-200"
            />
            <span className="mt-1 block text-xs text-neutral-500">{hint}</span>
            {form.errors[name] && <span className="text-xs text-red-400">{form.errors[name]}</span>}
        </label>
    );

    const label = (item: PreviewItem) => (i18n.language === 'en' && item.alt_en ? item.alt_en : (item.alt_ar ?? ''));

    /*
     * Drag-and-drop on the preview, which is where the client thinks about order.
     *
     * 🔑 Only the client's OWN slides can move. The rest of the preview is
     * campaign banners, which live in another table and are ordered by their
     * event — a drop there could not be expressed, so they are marked with a
     * padlock rather than silently refusing.
     *
     * ⚠️ Native HTML5 drag events rather than a library: this is one short list,
     * and the up/down buttons stay as the KEYBOARD path, since dragging is
     * mouse-only and removing them would make reordering pointer-dependent.
     */
    const [dragId, setDragId] = useState<string | null>(null);
    const [order, setOrder] = useState<PreviewItem[] | null>(null);
    const items = order ?? preview;
    const mine = (item: PreviewItem) => item.id.startsWith('slide-');

    // Server state wins whenever it arrives, so a reorder that failed — or a
    // campaign banner appearing — can never leave a stale local order on screen.
    useEffect(() => setOrder(null), [preview]);

    const drop = (targetId: string) => {
        if (!dragId || dragId === targetId) return;

        const list = [...items];
        const from = list.findIndex((x) => x.id === dragId);
        const to = list.findIndex((x) => x.id === targetId);
        if (from < 0 || to < 0 || !mine(list[from]) || !mine(list[to])) return;

        const [moved] = list.splice(from, 1);
        list.splice(to, 0, moved);
        setOrder(list); // optimistic, so the card stays where it was dropped

        router.post(
            '/admin/hero/reorder',
            { ids: list.filter(mine).map((x) => Number(x.id.replace('slide-', ''))) },
            { preserveScroll: true, onFinish: () => setDragId(null) },
        );
    };

    return (
        <AdminLayout title={t('admin.hero.title')}>
            <Head title={t('admin.hero.title')} />

            <div className="mb-4 flex items-start justify-between gap-4">
                <p className="max-w-2xl text-sm text-neutral-400">{t('admin.hero.intro')}</p>
                {canManage && (
                    <div className="flex flex-wrap items-center justify-end gap-2">
                        {drafts.length > 0 && (
                            <span className="inline-flex items-stretch overflow-hidden rounded-md border border-amber-500/40 bg-amber-500/10">
                                <button
                                    type="button"
                                    onClick={() => resumeDraft(drafts[0])}
                                    /* Amber, matching the in-dialog notice, and deliberately
                                   quieter than the primary action: it is a recovery, not
                                   the thing most visitors came to do. */
                                    className="inline-flex items-center gap-2 px-3 py-2 text-xs font-medium text-amber-300 transition hover:bg-amber-500/20"
                                    title={t('admin.hero.resumeHint')}
                                >
                                    <RotateCcw className="size-4 shrink-0" />
                                    <span>
                                        {drafts[0].id === 'new' ? t('admin.hero.resumeNew') : t('admin.hero.resumeEdit')}
                                        {drafts.length > 1 && ` (${drafts.length})`}
                                    </span>
                                </button>
                                {/* ⚠️ Throwing work away, so it is a separate press with its
                                own label — never a click target the resume button can
                                be mistaken for. */}
                                <button
                                    type="button"
                                    onClick={() => {
                                        discardDraft('hero', drafts[0].id);
                                        refreshDrafts();
                                    }}
                                    aria-label={t('admin.hero.resumeDiscard')}
                                    title={t('admin.hero.resumeDiscard')}
                                    className="border-s border-amber-500/40 px-2 text-amber-400/80 transition hover:bg-amber-500/20 hover:text-amber-200"
                                >
                                    <X className="size-3.5" />
                                </button>
                            </span>
                        )}
                        <Button onClick={() => openFor(null)} icon={Plus}>
                            {t('admin.hero.add')}
                        </Button>
                    </div>
                )}
            </div>

            {/* ── PREVIEW ───────────────────────────────────────────────────────
                🔑 Built server-side by HeroBanners::live(), the SAME method the
                storefront calls. It is not a second guess at the rules: a preview
                that re-derived them would agree until the day they changed, and
                then quietly show the client something the homepage does not do. */}
            <section className={`${CARD} mb-6 p-5`}>
                <div className="mb-3 flex items-baseline justify-between gap-3">
                    <h2 className="font-medium text-neutral-200">{t('admin.hero.previewTitle')}</h2>
                    <span className="text-xs text-neutral-500">{canManage ? t('admin.hero.dragHint') : t('admin.hero.previewNote')}</span>
                </div>

                {preview.length === 0 ? (
                    <p className="rounded-lg border border-dashed border-neutral-700 px-4 py-8 text-center text-sm text-neutral-400">
                        {t('admin.hero.previewEmpty')}
                    </p>
                ) : (
                    <ol className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {items.map((item, i) => (
                            <li
                                key={item.id}
                                draggable={canManage && mine(item)}
                                onDragStart={() => setDragId(item.id)}
                                onDragEnd={() => setDragId(null)}
                                onDragOver={(e) => {
                                    // Without preventDefault the browser refuses the drop outright.
                                    if (dragId && mine(item)) e.preventDefault();
                                }}
                                onDrop={(e) => {
                                    e.preventDefault();
                                    drop(item.id);
                                }}
                                className={`overflow-hidden rounded-lg border bg-neutral-950 transition-opacity ${
                                    dragId === item.id ? 'border-brand-gold opacity-40' : 'border-neutral-800'
                                } ${canManage && mine(item) ? 'cursor-grab active:cursor-grabbing' : ''}`}
                            >
                                <div className="relative aspect-[2/1] bg-neutral-900">
                                    {/* A video's poster is shipped in `image`, so one
                                        <img> covers both kinds and the preview never
                                        downloads the film itself. */}
                                    {item.image ? (
                                        <img src={item.image} alt={label(item)} className="h-full w-full object-cover" />
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-neutral-600">
                                            <Film className="h-6 w-6" />
                                        </div>
                                    )}
                                    <span className="absolute start-2 top-2 flex items-center gap-1 rounded bg-black/70 px-1.5 py-0.5 text-xs font-medium text-white">
                                        {i + 1}
                                        {/* Grip = you can drag this one. Padlock = it belongs to a
                                            campaign and is ordered by its event, so saying nothing
                                            would just look like a drop that failed. */}
                                        {canManage &&
                                            (mine(item) ? <GripVertical className="h-3 w-3 opacity-70" /> : <Lock className="h-3 w-3 opacity-70" />)}
                                    </span>
                                    {item.kind === 'video' && (
                                        <span className="absolute end-2 top-2 flex items-center gap-1 rounded bg-black/70 px-1.5 py-0.5 text-xs text-white">
                                            <Film className="h-3 w-3" /> {t('admin.hero.kinds.video')}
                                        </span>
                                    )}
                                </div>
                                <p className="truncate px-3 py-2 text-xs text-neutral-400" dir="auto">
                                    {/* Where it came from, because "why did my slide
                                        disappear" is the question this page exists to
                                        answer, and a campaign is usually the reason. */}
                                    {item.id.startsWith('event-') ? t('admin.hero.fromCampaign') : t('admin.hero.fromYou')}
                                </p>
                            </li>
                        ))}
                    </ol>
                )}
            </section>

            {/* ── MODE ─────────────────────────────────────────────────────────── */}
            <section className={`${CARD} mb-6 p-5`}>
                <h2 className="mb-1 font-medium text-neutral-200">{t('admin.hero.modeTitle')}</h2>
                <p className="mb-3 text-sm text-neutral-400">{t('admin.hero.modeIntro')}</p>
                <div className="grid gap-2 sm:grid-cols-3">
                    {modes.map((m) => {
                        const selected = m === mode;

                        return (
                            <button
                                key={m}
                                type="button"
                                disabled={!canManage}
                                aria-pressed={selected}
                                onClick={() => router.post('/admin/hero/mode', { mode: m }, { preserveScroll: true })}
                                className={`rounded-lg border p-3 text-start transition-colors disabled:opacity-50 ${
                                    selected ? 'border-brand-gold bg-brand-teal/20' : 'border-neutral-800 bg-neutral-950 hover:border-neutral-700'
                                }`}
                            >
                                <span className="block text-sm font-medium text-neutral-100">{t(`admin.hero.modes.${m}.label`)}</span>
                                <span className="mt-1 block text-xs text-neutral-400">{t(`admin.hero.modes.${m}.hint`)}</span>
                            </button>
                        );
                    })}
                </div>
                {campaignBanners.length > 0 && <p className="mt-3 text-xs text-neutral-500">{t('admin.hero.campaignRunning')}</p>}
            </section>

            {/* ── THE CLIENT'S OWN SLIDES ──────────────────────────────────────── */}
            <section className={`${CARD} overflow-hidden`}>
                {slides.length === 0 ? (
                    <div className="px-6 py-12 text-center">
                        <GalleryHorizontal className="mx-auto mb-3 h-8 w-8 text-neutral-600" />
                        <p className="font-medium text-neutral-300">{t('admin.hero.emptyTitle')}</p>
                        <p className="mt-1 text-sm text-neutral-500">{t('admin.hero.emptyHint')}</p>
                    </div>
                ) : (
                    <ul className="divide-y divide-neutral-800">
                        {slides.map((s, i) => (
                            <li key={s.id} className="flex flex-wrap items-center gap-4 px-4 py-3">
                                <div className="h-14 w-28 shrink-0 overflow-hidden rounded bg-neutral-900">
                                    {s.kind === 'video' ? (
                                        s.video_poster ? (
                                            <img src={s.video_poster} alt="" className="h-full w-full object-cover" />
                                        ) : (
                                            <div className="flex h-full items-center justify-center text-neutral-600">
                                                <Film className="h-5 w-5" />
                                            </div>
                                        )
                                    ) : s.image ? (
                                        <img src={s.image} alt="" className="h-full w-full object-cover" />
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-neutral-600">
                                            <ImageIcon className="h-5 w-5" />
                                        </div>
                                    )}
                                </div>

                                <div className="min-w-0 flex-1">
                                    <p className="flex items-center gap-2 truncate text-sm text-neutral-200" dir="auto">
                                        {s.kind === 'video' ? (
                                            <Film className="h-3.5 w-3.5 shrink-0" />
                                        ) : (
                                            <ImageIcon className="h-3.5 w-3.5 shrink-0" />
                                        )}
                                        {s.alt_ar || t(`admin.hero.kinds.${s.kind}`)}
                                    </p>
                                    <p className="truncate text-xs text-neutral-500">
                                        {/* Blank means "no bound", which is the feature:
                                            no end date runs until it is switched off. */}
                                        {s.starts_at ? new Date(s.starts_at).toLocaleString() : t('admin.hero.noStart')}
                                        {' · '}
                                        {s.ends_at ? new Date(s.ends_at).toLocaleString() : t('admin.hero.noEnd')}
                                    </p>
                                </div>

                                <StatusBadge domain="hero" value={s.state} />

                                {canManage && (
                                    <div className="flex items-center gap-2">
                                        <div className="flex shrink-0 flex-col">
                                            <button
                                                type="button"
                                                disabled={i === 0}
                                                aria-label={t('admin.hero.moveUp')}
                                                onClick={() =>
                                                    router.post(`/admin/hero/${s.id}/reorder`, { direction: 'up' }, { preserveScroll: true })
                                                }
                                                className="rounded p-0.5 text-neutral-500 hover:bg-neutral-800 hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-25"
                                            >
                                                <ChevronUp className="h-3.5 w-3.5" />
                                            </button>
                                            <button
                                                type="button"
                                                disabled={i === slides.length - 1}
                                                aria-label={t('admin.hero.moveDown')}
                                                onClick={() =>
                                                    router.post(`/admin/hero/${s.id}/reorder`, { direction: 'down' }, { preserveScroll: true })
                                                }
                                                className="rounded p-0.5 text-neutral-500 hover:bg-neutral-800 hover:text-neutral-200 disabled:pointer-events-none disabled:opacity-25"
                                            >
                                                <ChevronDown className="h-3.5 w-3.5" />
                                            </button>
                                        </div>
                                        <StatusToggle
                                            tone={s.is_active ? 'active' : 'stopped'}
                                            label={s.is_active ? t('admin.hero.on') : t('admin.hero.off')}
                                            url={`/admin/hero/${s.id}/toggle`}
                                            method="patch"
                                        />
                                        <Button variant="secondary" size="sm" icon={Pencil} onClick={() => openFor(s)}>
                                            {t('admin.common.edit')}
                                        </Button>
                                        <ConfirmDeleteButton
                                            reversible
                                            itemName={s.alt_ar || t(`admin.hero.kinds.${s.kind}`)}
                                            onConfirm={() => router.delete(`/admin/hero/${s.id}`, { preserveScroll: true })}
                                        />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <Modal
                open={open}
                onClose={closeDialog}
                title={editing ? t('admin.hero.editTitle') : t('admin.hero.addTitle')}
                /* Wide enough for two columns. The artwork and its crop editor are
                   inherently large, and in one narrow column every setting sat
                   below a tall preview and had to be scrolled to. */
                size="xl"
            >
                {/* Actions at the TOP (client's request). The dialog is tall, and
                    Save sitting under a full-height crop editor meant scrolling
                    past everything to commit a one-field change. */}
                <div className="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-neutral-800 pb-4">
                    <div className="min-w-0 text-xs">
                        {draft.restored && (
                            <p className="text-amber-400">
                                {t('admin.hero.draftRestored')}{' '}
                                <button
                                    type="button"
                                    onClick={() => {
                                        // 🔑 A real discard: throw the draft away AND blank the
                                        // fields. Merely hiding the notice left the restored text
                                        // in place, so "start over" did not start over.
                                        draft.clear();
                                        openFor(editing);
                                        refreshDrafts();
                                    }}
                                    className="underline"
                                >
                                    {t('admin.hero.draftDiscard')}
                                </button>
                            </p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="secondary" onClick={cancelDialog}>
                            {t('admin.common.cancel')}
                        </Button>
                        <Button onClick={submit} disabled={form.processing}>
                            {t('admin.hero.save')}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)]">
                    {/* LEFT: the artwork, and what will survive the crop. */}
                    <div className="grid content-start gap-4">
                        <div>
                            <span className="text-sm text-neutral-300">{t('admin.hero.kind')}</span>
                            <div className="mt-1 flex gap-2">
                                {(['image', 'video'] as const).map((k) => (
                                    <button
                                        key={k}
                                        type="button"
                                        aria-pressed={form.data.kind === k}
                                        onClick={() => form.setData('kind', k)}
                                        className={`flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors ${
                                            form.data.kind === k
                                                ? 'border-brand-gold bg-brand-teal/20 text-neutral-100'
                                                : 'border-neutral-700 bg-neutral-950 text-neutral-300'
                                        }`}
                                    >
                                        {k === 'video' ? <Film className="h-4 w-4" /> : <ImageIcon className="h-4 w-4" />}
                                        {t(`admin.hero.kinds.${k}`)}
                                    </button>
                                ))}
                            </div>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
                            {form.data.kind === 'image'
                                ? [
                                      file('image', t('admin.hero.imageDesktop'), 'image/*', t('admin.hero.imageDesktopHint')),
                                      file('image_mobile', t('admin.hero.imagePhone'), 'image/*', t('admin.hero.imagePhoneHint')),
                                  ]
                                : [
                                      file('video', t('admin.hero.video'), 'video/mp4,video/webm', t('admin.hero.videoHint', { n: videoMaxMb })),
                                      file('video_poster', t('admin.hero.poster'), 'image/*', t('admin.hero.posterHint')),
                                  ]}
                        </div>

                        <HeroCropPreview
                            src={preview_}
                            phoneSrc={phonePreview}
                            focal={{ x: form.data.focal_x, y: form.data.focal_y }}
                            onFocal={(x, y) => {
                                form.setData('focal_x', x);
                                form.setData('focal_y', y);
                            }}
                            focalMobile={{ x: form.data.focal_mobile_x, y: form.data.focal_mobile_y }}
                            onFocalMobile={(x, y) => {
                                form.setData('focal_mobile_x', x);
                                form.setData('focal_mobile_y', y);
                            }}
                            video={previewVideo}
                            emptyHint={videoUnplayable ? t('admin.hero.cropNeedsPoster') : undefined}
                            t={t}
                        />
                        {busy && <p className="text-xs text-neutral-400">{t('admin.hero.readingVideo')}</p>}
                        {/* ⚠️ Said nothing at all before, so a failed grab was
                            indistinguishable from a broken upload. */}
                        {posterFailed && !busy && (
                            <div className="text-xs text-amber-400">
                                {/* Three different problems, three different sentences. "No
                                    still could be taken" is cosmetic; "this browser has no
                                    H.264" means no MP4 will ever preview here; "this file was
                                    refused" points at the file. Only the first is our doing. */}
                                <p>
                                    {t(
                                        !videoUnplayable
                                            ? 'admin.hero.posterFailed'
                                            : videoDiagnosis === 'csp'
                                              ? 'admin.hero.videoCsp'
                                              : videoDiagnosis === 'noH264'
                                                ? 'admin.hero.videoNoH264'
                                                : // ⚠️ Once the picture IS supplied and driving the
                                                  // preview, stop telling them to supply one.
                                                  preview_ && !previewVideo
                                                  ? 'admin.hero.videoUsingPoster'
                                                  : 'admin.hero.videoUnplayable',
                                    )}
                                </p>
                                {videoDetail && <p className="mt-1 font-mono text-[11px] text-neutral-500">{videoDetail}</p>}
                            </div>
                        )}
                    </div>

                    {/* RIGHT: the settings. Moved out from under the preview at the
                        client's request - in one column they sat below a tall crop
                        editor and had to be scrolled to; beside it they are all
                        visible at once, and the dialog uses its width. */}
                    <div className="grid content-start gap-4">
                        <section className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
                            <h3 className="mb-2 text-xs font-semibold tracking-wide text-neutral-400 uppercase">{t('admin.hero.groupLink')}</h3>
                            <HeroLinkPicker
                                /* Remount per slide: the kind is local state, so
                                   without this, opening a second slide would keep
                                   the first one's choice. */
                                key={editing?.id ?? 'new'}
                                href={form.data.href}
                                onChange={(v) => form.setData('href', v)}
                                targets={linkTargets}
                                lang={i18n.language}
                                t={t}
                            />
                            {form.errors.href && <span className="mt-1 block text-xs text-red-400">{form.errors.href}</span>}
                        </section>

                        <section className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
                            <h3 className="mb-2 text-xs font-semibold tracking-wide text-neutral-400 uppercase">{t('admin.hero.groupWhen')}</h3>
                            <div className="grid gap-3">
                                {text('starts_at', t('admin.hero.startsAt'), 'datetime-local')}
                                {text('ends_at', t('admin.hero.endsAt'), 'datetime-local')}
                            </div>
                            <p className="mt-2 text-xs text-neutral-500">{t('admin.hero.whenHint')}</p>
                        </section>

                        <section className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
                            <h3 className="mb-2 text-xs font-semibold tracking-wide text-neutral-400 uppercase">{t('admin.hero.groupShow')}</h3>
                            <div className="grid grid-cols-[6rem_1fr] items-end gap-3">
                                {text('sort_order', t('admin.hero.order'), 'number')}
                                <label className="flex items-center gap-2 pb-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_active}
                                        onChange={(e) => form.setData('is_active', e.target.checked)}
                                        className="h-4 w-4 rounded border-neutral-700 bg-neutral-950"
                                    />
                                    <span className="text-sm text-neutral-300">{t('admin.hero.showOnStore')}</span>
                                </label>
                            </div>
                        </section>

                        <section className="rounded-lg border border-neutral-800 bg-neutral-950/60 p-3">
                            <h3 className="mb-2 text-xs font-semibold tracking-wide text-neutral-400 uppercase">{t('admin.hero.groupAlt')}</h3>
                            <div className="grid gap-3">
                                {text('alt_ar', t('admin.hero.altAr'))}
                                {text('alt_en', t('admin.hero.altEn'))}
                            </div>
                            <p className="mt-2 text-xs text-neutral-500">{t('admin.hero.altHint')}</p>
                        </section>

                        {/* Every remaining field's error, so a rejection can never be
                            silent. `is_active` in particular has no visible control of
                            its own to hang a message under. */}
                        <div className="empty:hidden">
                            {form.errors.is_active && <span className="text-xs text-red-400">{form.errors.is_active}</span>}
                            {form.errors.kind && <span className="text-xs text-red-400">{form.errors.kind}</span>}
                        </div>
                    </div>
                </div>
            </Modal>
        </AdminLayout>
    );
}
