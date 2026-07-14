import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

interface HeartbeatIndicatorProps {
  kiosk: Kiosk
}

function heartbeatTone(kiosk: Kiosk): 'success' | 'warning' | 'danger' | 'neutral' {
  if (kiosk.status === 'retired') return 'neutral'
  if (kiosk.status === 'online') return 'success'
  if (kiosk.status === 'degraded') return 'warning'
  if (kiosk.status === 'offline') return 'danger'
  return 'neutral'
}

const TONE_DOT: Record<string, string> = {
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  danger: 'bg-rose-500',
  neutral: 'bg-slate-400',
}

export function HeartbeatIndicator({ kiosk }: HeartbeatIndicatorProps) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const tone = heartbeatTone(kiosk)
  const lastSeen = kiosk.last_heartbeat_at
    ? new Date(kiosk.last_heartbeat_at).toLocaleString(ar ? 'ar-EG' : 'en-US')
    : (ar ? 'أبداً' : 'never')

  return (
    <span
      className="inline-flex items-center gap-2 text-sm text-[var(--muted)]"
      title={`${ar ? 'آخر نبضة' : 'Last heartbeat'}: ${lastSeen}`}
    >
      <span
        className={clsx(
          'relative flex h-2.5 w-2.5',
          tone === 'success' && 'motion-safe:animate-pulse',
        )}
        aria-hidden
      >
        <span className={clsx('absolute inline-flex h-full w-full rounded-full opacity-40', TONE_DOT[tone])} />
        <span className={clsx('relative inline-flex h-2.5 w-2.5 rounded-full', TONE_DOT[tone])} />
      </span>
      <span className="sr-only">
        {ar ? 'آخر نبضة' : 'Last heartbeat'}: {lastSeen}
      </span>
      <span>{lastSeen}</span>
    </span>
  )
}
