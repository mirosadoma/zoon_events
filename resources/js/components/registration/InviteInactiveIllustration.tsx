type Props = {
  className?: string
}

/** Soft illustration for expired / invalid private invite links. */
export default function InviteInactiveIllustration({ className }: Props) {
  return (
    <svg
      className={className}
      viewBox="0 0 280 200"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      role="img"
      aria-hidden="true"
    >
      <defs>
        <linearGradient id="invite-inactive-sky" x1="40" y1="20" x2="240" y2="180" gradientUnits="userSpaceOnUse">
          <stop stopColor="color-mix(in srgb, var(--brand) 18%, var(--surface))" />
          <stop offset="1" stopColor="color-mix(in srgb, var(--warning) 14%, var(--surface-elevated))" />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="232" height="144" rx="28" fill="url(#invite-inactive-sky)" />
      <circle cx="68" cy="58" r="18" fill="color-mix(in srgb, var(--brand) 22%, transparent)" />
      <circle cx="220" cy="148" r="26" fill="color-mix(in srgb, var(--warning) 18%, transparent)" />

      <g transform="translate(70 58)">
        <path
          d="M0 24 C0 10.7 10.7 0 24 0 H116 C129.3 0 140 10.7 140 24 V36 C128.4 36 120 44.4 120 56 C120 67.6 128.4 76 140 76 V88 C140 101.3 129.3 112 116 112 H24 C10.7 112 0 101.3 0 88 V76 C11.6 76 20 67.6 20 56 C20 44.4 11.6 36 0 36 V24 Z"
          fill="var(--surface-elevated)"
          stroke="var(--border)"
          strokeWidth="2.5"
        />
        <path d="M36 0 V112" stroke="var(--border)" strokeWidth="2" strokeDasharray="5 7" />
        <rect x="52" y="28" width="64" height="10" rx="5" fill="color-mix(in srgb, var(--ink) 12%, transparent)" />
        <rect x="52" y="48" width="44" height="8" rx="4" fill="color-mix(in srgb, var(--ink) 8%, transparent)" />
        <rect x="8" y="42" width="16" height="28" rx="4" fill="color-mix(in srgb, var(--brand) 35%, var(--surface))" />
      </g>

      <g transform="translate(168 86)">
        <circle
          cx="28"
          cy="28"
          r="28"
          fill="color-mix(in srgb, var(--warning) 16%, var(--surface-elevated))"
          stroke="var(--warning)"
          strokeWidth="2.5"
        />
        <path
          d="M17 17 L39 39 M39 17 L17 39"
          stroke="var(--warning)"
          strokeWidth="3.5"
          strokeLinecap="round"
        />
      </g>
    </svg>
  )
}
