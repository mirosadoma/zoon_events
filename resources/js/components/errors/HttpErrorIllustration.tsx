import type { ErrorPageTone } from '@/lib/errorPageCatalog'

type Props = {
  statusCode: number
  tone: ErrorPageTone
  className?: string
}

function toneColors(tone: ErrorPageTone) {
  switch (tone) {
    case 'auth':
      return { fill: 'var(--brand-soft)', accent: 'var(--brand)', glow: 'var(--brand)' }
    case 'forbidden':
      return { fill: 'color-mix(in srgb, var(--danger) 10%, var(--surface-elevated))', accent: 'var(--danger)', glow: 'var(--danger)' }
    case 'method':
      return { fill: 'color-mix(in srgb, var(--info) 12%, var(--surface-elevated))', accent: 'var(--info)', glow: 'var(--info)' }
    case 'session':
      return { fill: 'color-mix(in srgb, var(--warning) 12%, var(--surface-elevated))', accent: 'var(--warning)', glow: 'var(--warning)' }
    case 'rate':
      return { fill: 'color-mix(in srgb, var(--warning) 14%, var(--surface-elevated))', accent: 'var(--warning)', glow: 'var(--warning)' }
    case 'unavailable':
      return { fill: 'color-mix(in srgb, var(--danger) 12%, var(--surface-elevated))', accent: 'var(--danger)', glow: 'var(--brand)' }
    case 'notfound':
      return { fill: 'var(--brand-soft)', accent: 'var(--brand)', glow: 'var(--brand)' }
    default:
      return { fill: 'color-mix(in srgb, var(--danger) 10%, var(--surface-elevated))', accent: 'var(--danger)', glow: 'var(--danger)' }
  }
}

function ToneMotif({ tone }: { tone: ErrorPageTone }) {
  switch (tone) {
    case 'auth':
      return (
        <g transform="translate(118 78)">
          <rect x="18" y="34" width="48" height="40" rx="10" fill="var(--surface-elevated)" stroke="var(--brand)" strokeWidth="3" />
          <path d="M42 18v22" stroke="var(--brand)" strokeWidth="4" strokeLinecap="round" />
          <circle cx="42" cy="16" r="12" fill="var(--surface-elevated)" stroke="var(--brand)" strokeWidth="3" />
        </g>
      )
    case 'forbidden':
      return (
        <g transform="translate(118 72)">
          <path d="M42 8 L72 20 V44 C72 58 58 72 42 72 C26 72 12 58 12 44 V20 Z" fill="var(--surface-elevated)" stroke="var(--danger)" strokeWidth="3" />
          <path d="M30 36 L54 60 M54 36 L30 60" stroke="var(--danger)" strokeWidth="4" strokeLinecap="round" />
        </g>
      )
    case 'method':
      return (
        <g transform="translate(108 78)">
          <rect x="0" y="18" width="84" height="48" rx="12" fill="var(--surface-elevated)" stroke="var(--info)" strokeWidth="3" />
          <path d="M18 18 L42 0 L66 18" fill="var(--surface-elevated)" stroke="var(--info)" strokeWidth="3" strokeLinejoin="round" />
          <path d="M24 42 H60" stroke="var(--info)" strokeWidth="4" strokeLinecap="round" />
          <path d="M42 34 V50" stroke="var(--info)" strokeWidth="4" strokeLinecap="round" />
        </g>
      )
    case 'session':
      return (
        <g transform="translate(118 74)">
          <circle cx="42" cy="42" r="34" fill="var(--surface-elevated)" stroke="var(--warning)" strokeWidth="3" />
          <path d="M42 24 V42 L54 48" fill="none" stroke="var(--warning)" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" />
          <circle cx="42" cy="42" r="4" fill="var(--warning)" />
        </g>
      )
    case 'rate':
      return (
        <g transform="translate(112 78)">
          <path d="M8 52 H76" stroke="var(--warning)" strokeWidth="3" strokeLinecap="round" />
          <path d="M16 52 V28 M32 52 V18 M48 52 V34 M64 52 V12" stroke="var(--warning)" strokeWidth="6" strokeLinecap="round" />
          <circle cx="64" cy="12" r="8" fill="color-mix(in srgb, var(--warning) 20%, var(--surface-elevated))" stroke="var(--warning)" strokeWidth="2" />
        </g>
      )
    case 'unavailable':
      return (
        <g transform="translate(92 56)">
          <rect x="0" y="0" width="136" height="96" rx="14" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
          <path d="M48 44 H88" stroke="color-mix(in srgb, var(--danger) 40%, var(--border))" strokeWidth="4" strokeLinecap="round" />
          <circle cx="68" cy="62" r="12" fill="color-mix(in srgb, var(--danger) 16%, var(--surface))" />
          <path d="M63 62 H73 M68 57 V67" stroke="var(--danger)" strokeWidth="2.5" strokeLinecap="round" />
        </g>
      )
    case 'notfound':
      return (
        <g transform="translate(118 78)">
          <circle cx="42" cy="42" r="34" fill="var(--surface-elevated)" stroke="var(--brand)" strokeWidth="4" />
          <path d="M58 58 L76 76" fill="none" stroke="var(--brand)" strokeWidth="5" strokeLinecap="round" />
          <text x="42" y="49" textAnchor="middle" fontSize="22" fontWeight="700" fill="var(--brand)" fontFamily="system-ui, sans-serif">?</text>
        </g>
      )
    default:
      return (
        <g transform="translate(92 56)">
          <rect x="0" y="0" width="136" height="96" rx="14" fill="var(--surface-elevated)" stroke="var(--border)" strokeWidth="2" />
          <circle cx="68" cy="48" r="14" fill="color-mix(in srgb, var(--danger) 16%, var(--surface))" />
          <path d="M63 48 H73 M68 43 V53" stroke="var(--danger)" strokeWidth="2.5" strokeLinecap="round" />
        </g>
      )
  }
}

export default function HttpErrorIllustration({ statusCode, tone, className = '' }: Props) {
  const colors = toneColors(tone)
  const gradientId = `he-${tone}-${statusCode}`

  return (
    <svg
      viewBox="0 0 320 240"
      role="img"
      aria-hidden="true"
      className={className}
      xmlns="http://www.w3.org/2000/svg"
    >
      <defs>
        <linearGradient id={gradientId} x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" stopColor={colors.fill} />
          <stop offset="100%" stopColor="color-mix(in srgb, var(--surface-elevated) 88%, transparent)" />
        </linearGradient>
      </defs>

      <rect x="24" y="28" width="272" height="184" rx="24" fill={`url(#${gradientId})`} />
      <circle cx="248" cy="52" r="18" fill={`color-mix(in srgb, ${colors.glow} 18%, transparent)`} />
      <circle cx="68" cy="188" r="28" fill={`color-mix(in srgb, ${colors.glow} 10%, transparent)`} />

      <ToneMotif tone={tone} />

      <text
        x="160"
        y="214"
        textAnchor="middle"
        fontSize="34"
        fontWeight="800"
        fill={colors.accent}
        fontFamily="system-ui, sans-serif"
        letterSpacing="3"
      >
        {statusCode}
      </text>
    </svg>
  )
}
