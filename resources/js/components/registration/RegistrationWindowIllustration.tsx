type Props = {
  className?: string
  variant: 'not_open' | 'closed'
}

/** Soft illustration for registration not-yet-open / closed states. */
export default function RegistrationWindowIllustration({ className, variant }: Props) {
  const isClosed = variant === 'closed'

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
        <linearGradient id={`reg-window-sky-${variant}`} x1="40" y1="20" x2="240" y2="180" gradientUnits="userSpaceOnUse">
          <stop stopColor={isClosed
            ? 'color-mix(in srgb, var(--danger, #dc2626) 14%, var(--surface))'
            : 'color-mix(in srgb, var(--brand) 16%, var(--surface))'}
          />
          <stop
            offset="1"
            stopColor={isClosed
              ? 'color-mix(in srgb, var(--ink) 8%, var(--surface-elevated))'
              : 'color-mix(in srgb, var(--accent, #0ea5e9) 12%, var(--surface-elevated))'}
          />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="232" height="144" rx="28" fill={`url(#reg-window-sky-${variant})`} />
      <circle cx="64" cy="56" r="16" fill="color-mix(in srgb, var(--brand) 18%, transparent)" />
      <circle cx="224" cy="150" r="22" fill="color-mix(in srgb, var(--ink) 6%, transparent)" />

      {/* Door / gate */}
      <g transform="translate(98 52)">
        <rect
          x="0"
          y="0"
          width="84"
          height="112"
          rx="14"
          fill="var(--surface-elevated)"
          stroke="var(--border)"
          strokeWidth="2.5"
        />
        <rect x="14" y="18" width="56" height="76" rx="8" fill="color-mix(in srgb, var(--ink) 5%, var(--surface))" />
        {isClosed ? (
          <>
            <path
              d="M28 56 H56"
              stroke="var(--danger, #dc2626)"
              strokeWidth="4"
              strokeLinecap="round"
            />
            <circle cx="62" cy="56" r="5" fill="var(--danger, #dc2626)" />
            <path
              d="M20 28 L64 84 M64 28 L20 84"
              stroke="color-mix(in srgb, var(--danger, #dc2626) 55%, transparent)"
              strokeWidth="3"
              strokeLinecap="round"
            />
          </>
        ) : (
          <>
            <path
              d="M32 40 C32 28 42 22 52 28"
              stroke="var(--brand)"
              strokeWidth="3.5"
              strokeLinecap="round"
              fill="none"
            />
            <circle cx="52" cy="30" r="4" fill="var(--brand)" />
            <rect x="34" y="52" width="28" height="8" rx="4" fill="color-mix(in srgb, var(--brand) 35%, transparent)" />
            <rect x="38" y="66" width="20" height="6" rx="3" fill="color-mix(in srgb, var(--ink) 12%, transparent)" />
          </>
        )}
      </g>

      {/* Calendar chip */}
      <g transform="translate(38 118)">
        <rect width="52" height="40" rx="10" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <rect y="0" width="52" height="12" rx="6" fill="color-mix(in srgb, var(--brand) 40%, var(--surface))" />
        <circle cx="18" cy="26" r="3" fill="color-mix(in srgb, var(--ink) 35%, transparent)" />
        <circle cx="34" cy="26" r="3" fill="color-mix(in srgb, var(--ink) 35%, transparent)" />
      </g>
    </svg>
  )
}
