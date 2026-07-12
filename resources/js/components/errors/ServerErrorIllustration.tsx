type Props = {
  className?: string
}

export default function ServerErrorIllustration({ className = '' }: Props) {
  return (
    <svg
      viewBox="0 0 320 240"
      role="img"
      aria-hidden="true"
      className={className}
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient id="se-bg" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="color-mix(in srgb, var(--danger) 10%, var(--surface-elevated))" />
          <stop offset="100%" stopColor="color-mix(in srgb, var(--brand) 12%, var(--surface-elevated))" />
        </linearGradient>
        <linearGradient id="se-accent" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="var(--danger)" />
          <stop offset="100%" stopColor="color-mix(in srgb, var(--danger) 70%, var(--brand))" />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="272" height="184" rx="24" fill="url(#se-bg)" />
      <circle cx="72" cy="56" r="16" fill="color-mix(in srgb, var(--danger) 16%, transparent)" />
      <circle cx="252" cy="176" r="24" fill="color-mix(in srgb, var(--brand) 12%, transparent)" />

      <g transform="translate(92 56)">
        <rect x="0" y="0" width="136" height="96" rx="14" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <rect x="16" y="18" width="104" height="10" rx="5" fill="color-mix(in srgb, var(--brand) 18%, var(--border))" />
        <rect x="16" y="38" width="72" height="8" rx="4" fill="color-mix(in srgb, var(--brand) 10%, var(--border))" />
        <rect x="16" y="54" width="88" height="8" rx="4" fill="color-mix(in srgb, var(--brand) 10%, var(--border))" />

        <circle cx="68" cy="78" r="10" fill="color-mix(in srgb, var(--danger) 18%, var(--surface))" />
        <path
          d="M63 78h10M68 73v10"
          stroke="var(--danger)"
          strokeWidth="2.5"
          strokeLinecap="round"
        />
      </g>

      <g transform="translate(56 164)">
        <rect x="0" y="0" width="52" height="14" rx="7" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <rect x="68" y="0" width="52" height="14" rx="7" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <rect x="136" y="0" width="72" height="14" rx="7" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <circle cx="26" cy="7" r="3" fill="var(--brand)" />
        <circle cx="94" cy="7" r="3" fill="color-mix(in srgb, var(--brand) 50%, var(--muted))" />
        <circle cx="172" cy="7" r="3" fill="color-mix(in srgb, var(--danger) 70%, var(--muted))" />
      </g>

      <g transform="translate(228 72)">
        <path
          d="M0 24 L16 8 L32 24 L48 8"
          fill="none"
          stroke="url(#se-accent)"
          strokeWidth="4"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        <circle cx="24" cy="40" r="14" fill="var(--surface-elevated)" stroke="url(#se-accent)" strokeWidth="3" />
        <path d="M24 33v8M24 45h.01" stroke="var(--danger)" strokeWidth="2.5" strokeLinecap="round" />
      </g>

      <text
        x="160"
        y="214"
        textAnchor="middle"
        fontSize="34"
        fontWeight="800"
        fill="url(#se-accent)"
        fontFamily="system-ui, sans-serif"
        letterSpacing="3"
      >
        500
      </text>
    </svg>
  )
}
