type Props = {
  className?: string
}

/** Soft illustration for fully booked / sold-out registration. */
export default function RegistrationSoldOutIllustration({ className }: Props) {
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
        <linearGradient id="reg-soldout-sky" x1="40" y1="20" x2="240" y2="180" gradientUnits="userSpaceOnUse">
          <stop stopColor="color-mix(in srgb, var(--brand) 12%, var(--surface))" />
          <stop offset="1" stopColor="color-mix(in srgb, var(--ink) 6%, var(--surface-elevated))" />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="232" height="144" rx="28" fill="url(#reg-soldout-sky)" />
      <circle cx="58" cy="58" r="14" fill="color-mix(in srgb, var(--brand) 16%, transparent)" />
      <circle cx="228" cy="148" r="20" fill="color-mix(in srgb, var(--ink) 5%, transparent)" />

      {/* Ticket */}
      <g transform="translate(78 58)">
        <path
          d="M0 18 C0 8 8 0 18 0 H106 C116 0 124 8 124 18 V34 C112 34 112 50 124 50 V66 C124 76 116 84 106 84 H18 C8 84 0 76 0 66 V50 C12 50 12 34 0 34 Z"
          fill="var(--surface-elevated)"
          stroke="var(--border)"
          strokeWidth="2.5"
        />
        <path
          d="M28 22 H88"
          stroke="color-mix(in srgb, var(--ink) 18%, transparent)"
          strokeWidth="4"
          strokeLinecap="round"
        />
        <path
          d="M28 42 H72"
          stroke="color-mix(in srgb, var(--ink) 12%, transparent)"
          strokeWidth="4"
          strokeLinecap="round"
        />
        <path
          d="M28 58 H64"
          stroke="color-mix(in srgb, var(--brand) 35%, transparent)"
          strokeWidth="4"
          strokeLinecap="round"
        />
      </g>

      {/* Full badge */}
      <g transform="translate(168 44)">
        <circle cx="28" cy="28" r="26" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2.5" />
        <circle cx="28" cy="28" r="18" fill="color-mix(in srgb, var(--danger, #dc2626) 12%, var(--surface))" />
        <path
          d="M18 28 H38"
          stroke="var(--danger, #dc2626)"
          strokeWidth="4"
          strokeLinecap="round"
        />
      </g>
    </svg>
  )
}
