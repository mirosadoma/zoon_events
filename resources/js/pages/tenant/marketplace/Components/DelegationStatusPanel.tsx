import { useEffect, useState } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { DelegationInfo } from '@/types/phase6'

type Props = {
  delegation?: DelegationInfo | null
}

export default function DelegationStatusPanel({ delegation = null }: Props) {
  const { t } = useLocale()
  const [countdown, setCountdown] = useState<string>('—')

  useEffect(() => {
    if (!delegation?.expires_at) {
      setCountdown('—')
      return undefined
    }

    function tick() {
      const target = new Date(delegation!.expires_at!).getTime()
      const diff = target - Date.now()
      if (diff <= 0) {
        setCountdown('0:00:00')
        return
      }
      const hours = Math.floor(diff / 3600000)
      const minutes = Math.floor((diff % 3600000) / 60000)
      const seconds = Math.floor((diff % 60000) / 1000)
      setCountdown(`${hours}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`)
    }

    tick()
    const id = window.setInterval(tick, 1000)
    return () => window.clearInterval(id)
  }, [delegation?.expires_at])

  if (!delegation) {
    return (
      <section className="ta-card" aria-label={t('delegationStatus')}>
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('delegationStatus')}</h3>
        <p className="text-sm text-[var(--muted)]">{t('noOperationalLinks')}</p>
      </section>
    )
  }

  return (
    <section className="ta-card space-y-2" aria-label={t('delegationStatus')}>
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('delegationStatus')}</h3>
        <StatusBadge status={delegation.status} size="md" />
      </div>
      <p className="text-sm text-[var(--muted)]">
        {t('delegationCountdown')}: <span aria-live="polite">{countdown}</span>
      </p>
      {delegation.server_timestamp ? (
        <p className="text-xs text-[var(--muted)]">
          Server: {delegation.server_timestamp}
        </p>
      ) : null}
    </section>
  )
}
