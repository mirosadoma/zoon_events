import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

interface LaneHealthCardProps {
  laneId: string
  healthStatus: 'online' | 'degraded' | 'offline'
  lastSeenAt: string | null
  activeEmergency?: boolean
  laneName?: string
}

export function LaneHealthCard({
  laneId,
  healthStatus,
  lastSeenAt,
  activeEmergency = false,
  laneName,
}: LaneHealthCardProps) {
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const title = laneName ?? (ar ? `مسار ${laneId}` : `Lane ${laneId}`)

  return (
    <article className="ta-card space-y-3">
      {activeEmergency && (
        <p className="rounded-lg bg-amber-100 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/50 dark:text-amber-200" role="status">
          {t('laneHealthEmergencyActive')}
        </p>
      )}
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="font-semibold text-[var(--ink)]">{title}</h3>
          <p className="mt-0.5 font-mono text-xs text-[var(--muted)]">{laneId}</p>
        </div>
        <StatusBadge status={healthStatus} />
      </div>
      <p className="text-sm text-[var(--muted)]">
        {t('laneHealthLastSeen')}:{' '}
        <span className="text-[var(--ink)]">{lastSeenAt ?? t('laneHealthNever')}</span>
      </p>
    </article>
  )
}
