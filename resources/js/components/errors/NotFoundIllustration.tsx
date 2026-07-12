type Props = {
  className?: string
}

export default function NotFoundIllustration({ className = '' }: Props) {
  return (
    <svg
      viewBox="0 0 320 240"
      role="img"
      aria-hidden="true"
      className={className}
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient id="nf-sky" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor="var(--brand-soft)" />
          <stop offset="100%" stopColor="color-mix(in srgb, var(--brand) 12%, var(--surface-elevated))" />
        </linearGradient>
        <linearGradient id="nf-accent" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%" stopColor="var(--brand)" />
          <stop offset="100%" stopColor="color-mix(in srgb, var(--brand) 70%, #6366f1)" />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="272" height="184" rx="24" fill="url(#nf-sky)" />
      <circle cx="248" cy="52" r="18" fill="color-mix(in srgb, var(--brand) 18%, transparent)" />
      <circle cx="68" cy="188" r="28" fill="color-mix(in srgb, var(--brand) 10%, transparent)" />

      <path
        d="M96 164c18-34 52-54 88-54 28 0 54 12 72 34"
        fill="none"
        stroke="color-mix(in srgb, var(--brand) 35%, var(--border))"
        strokeWidth="3"
        strokeLinecap="round"
        strokeDasharray="8 10"
      />

      <g transform="translate(118 78)">
        <circle cx="42" cy="42" r="34" fill="var(--surface-elevated)" stroke="url(#nf-accent)" strokeWidth="4" />
        <circle cx="42" cy="42" r="22" fill="none" stroke="color-mix(in srgb, var(--brand) 35%, transparent)" strokeWidth="3" />
        <path
          d="M58 58l18 18"
          fill="none"
          stroke="url(#nf-accent)"
          strokeWidth="5"
          strokeLinecap="round"
        />
        <text
          x="42"
          y="49"
          textAnchor="middle"
          fontSize="22"
          fontWeight="700"
          fill="var(--brand)"
          fontFamily="system-ui, sans-serif"
        >
          ?
        </text>
      </g>

      <g transform="translate(188 118)">
        <rect x="0" y="18" width="72" height="54" rx="10" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
        <path d="M12 18 L36 0 L60 18" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" strokeLinejoin="round" />
        <rect x="14" y="34" width="44" height="6" rx="3" fill="color-mix(in srgb, var(--brand) 20%, var(--border))" />
        <rect x="14" y="48" width="30" height="6" rx="3" fill="color-mix(in srgb, var(--brand) 12%, var(--border))" />
      </g>

      <text
        x="160"
        y="214"
        textAnchor="middle"
        fontSize="34"
        fontWeight="800"
        fill="url(#nf-accent)"
        fontFamily="system-ui, sans-serif"
        letterSpacing="4"
      >
        404
      </text>
    </svg>
  )
}
