<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ContactMessageController as StorefrontContactMessageController;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\Auth\OtpService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Inbox for the storefront "أرسل رسالتك" form. Read + resolve: staff read the
 * message, reply directly by email or WhatsApp, then mark it handled.
 *
 * Mirrors ProductRequestController — same list/filter/mark-handled shape — but
 * carries the message BODY, because unlike a demand signal the content is the
 * whole point. It was originally left to the staff email alone; that turned out
 * to be a dead end in the panel (the bell row names the sender but not what they
 * wrote), so this is the page the notification now deep-links to.
 */
class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // open | handled | (all)
        $perPage = $this->perPage($request, 30);

        $messages = ContactMessage::query()
            ->when($status === 'open', fn ($q) => $q->whereNull('handled_at'))
            ->when($status === 'handled', fn ($q) => $q->whereNotNull('handled_at'))
            ->latest()
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => trim("{$m->first_name} {$m->last_name}"),
                'email' => $m->email,
                'phone' => $m->phone,
                // Built here rather than in the page so the number goes through the
                // app's single normalizer (E.164 digits, no '+'). Unconditional:
                // phone is NOT NULL in the schema and required by the form's rules.
                'whatsapp_url' => 'https://wa.me/'.app(OtpService::class)->normalize($m->phone),
                'inquiry_type' => $m->inquiry_type,
                'message' => $m->message,
                'handled' => $m->handled_at !== null,
                'created_at' => $m->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('admin/contact-messages/index', [
            'messages' => $messages,
            'filters' => [
                'status' => in_array($status, ['open', 'handled'], true) ? $status : null,
                'per_page' => $perPage,
            ],
            'openCount' => ContactMessage::whereNull('handled_at')->count(),
            // Drives the filter chips; shared with the storefront form's validation
            // so a new inquiry type can never exist here without being selectable.
            'inquiryTypes' => StorefrontContactMessageController::INQUIRY_TYPES,
        ]);
    }

    public function markHandled(ContactMessage $contactMessage)
    {
        $contactMessage->update(['handled_at' => now()]);

        return back()->with('success', __('messages.admin.message_handled'));
    }
}
