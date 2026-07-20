import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { DisputeInfo } from '@/types/phase6'

type Props = {
  dispute?: DisputeInfo | null
  onOpenDispute?: () => void
  canOpen?: boolean
  participantView?: boolean
}

export default function DisputePanel({
  dispute = null,
  onOpenDispute,
  canOpen = false,
  participantView = true,
}: Props) {
  const { t } = useLocale()

  return (
    <section className="ta-card space-y-3" aria-label={t('disputePanel')}>
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('disputePanel')}</h3>
        {dispute ? <StatusBadge status={dispute.status} size="md" /> : null}
      </div>

      {!dispute ? (
        <>
          <p className="text-sm text-[var(--muted)]">{t('noDispute')}</p>
          {canOpen && participantView ? (
            <button type="button" className="button-secondary" onClick={onOpenDispute}>
              {t('openDispute')}
            </button>
          ) : null}
        </>
      ) : (
        <>
          {dispute.reason_category ? (
            <p className="text-sm">
              <span className="text-[var(--muted)]">{t('disputeReasonCategory')}: </span>
              {dispute.reason_category}
            </p>
          ) : null}
          {dispute.reason ? (
            <p className="text-sm">
              <span className="text-[var(--muted)]">{t('disputeReason')}: </span>
              {dispute.reason}
            </p>
          ) : null}
          {dispute.timeline && dispute.timeline.length > 0 ? (
            <ul className="space-y-2" aria-label={t('rentalTimeline')}>
              {dispute.timeline.map((event) => (
                <li key={event.id} className="rounded-xl border border-[var(--border)] px-3 py-2 text-sm">
                  <span className="text-[var(--muted)]">{event.occurred_at}</span>
                  <span className="mx-2">—</span>
                  <span>{event.summary ?? event.kind}</span>
                </li>
              ))}
            </ul>
          ) : null}
        </>
      )}
    </section>
  )
}
