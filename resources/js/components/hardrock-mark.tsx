/**
 * The HardRock emblem (the mark only, not the wordmark) for the footer build
 * credit, traced from the agency's master `HOR-BLACK LOGO.svg`.
 *
 * Only the two emblem paths are kept — in the master they sit at x 0–155.55
 * while the "HARDROCK" letterforms start at x=177, so the mark separates
 * cleanly. Using the emblem rather than the full wordmark keeps the SVG from
 * repeating the adjacent text: the mark is the picture, the text is the name.
 *
 * 🔑 Painted in `currentColor`, deliberately dropping the master's gradient.
 * At the size this renders (~11px wide beside 12px type) a two-stop gradient is
 * invisible as a gradient and reads only as uneven tone, and it would be the one
 * thing in the footer's cream-and-gold palette wearing a foreign colour. Taking
 * the colour from the link instead means the mark mutes and warms to gold in
 * step with the label it belongs to.
 */
export default function HardRockMark({ className }: { className?: string }) {
    return (
        <svg viewBox="0 0 155.55 219.67" fill="currentColor" className={className} aria-hidden focusable="false">
            <path d="M155.54,3.33v174.82c-5.18-5.46-10.36-10.87-15.54-16.32-4.11-4.35-8.28-8.69-12.39-12.99-10.13-10.73-31.53-33.85-43-46.28l-6.24-6.75c-1.39-1.53-3.42-2.4-5.55-2.4s-3.93.79-5.41,2.22L0,162.15V3.33C0,1.49,1.49,0,3.33,0h37.82c1.84,0,3.33,1.49,3.33,3.33v70.97h66.3V3.33c0-1.84,1.49-3.33,3.33-3.33h38.09c1.84,0,3.33,1.49,3.33,3.33Z" />
            <path d="M155.54,184.85v15.07c0,7.72-4.44,14.93-11.37,17.94-.42.18-.83.32-1.25.51-.6.18-1.2.37-1.85.51-.05,0-.14.05-.19.05-2.31.51-4.48.74-6.61.74-7.31,0-13.82-2.64-19.97-6.33-.88-.56-1.76-1.11-2.64-1.66-2.17-1.39-4.25-2.91-6.33-4.48-.74-.51-1.48-1.02-2.17-1.57-3.01-2.27-5.96-4.58-8.92-6.61-.79-.55-1.57-1.11-2.4-1.66-.42-.28-.83-.51-1.29-.79-.88-.51-1.76-.97-2.64-1.34-2.17-1.06-4.44-1.76-6.75-2.22-2.17-.42-4.39-.6-6.61-.6-5.69,0-11.37,1.25-17.02,3.19-1.11.37-2.27.83-3.42,1.25-2.91,1.16-5.78,2.45-8.55,3.79-1.02.46-2.03.97-3.01,1.48-1.71.83-3.38,1.76-5.04,2.73-.6.37-1.2.51-1.76.51-.92,0-1.76-.42-2.36-1.06-.46-.46-.83-1.06-.97-1.71-.14-.6-.09-1.25.14-1.85.14-.37.37-.74.69-1.11,6.29-6.94,12.53-13.92,18.36-21.22,5.13-6.43,18.63-22.89,18.86-30.61.18-5.96-4.44-12.3-8.69-17.2-.09-.14-1-1.21-2.64-1.2-.74,0-1.53.28-2.13.83-5.32,4.72-10.5,10.03-15.49,15.12-.83.83-1.66,1.71-2.59,2.64C25.11,161.92.74,186.33,0,186.38v-17.76l70.7-69.73c.6-.6,1.39-.88,2.13-.88.79,0,1.57.28,2.17.92,8.05,8.69,36.85,39.95,49.29,53.08,10.4,10.91,20.81,21.92,31.26,32.83Z" />
        </svg>
    );
}
