import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react'
import type { ReactNode } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { scanReasonLabel } from '@/lib/scanLabels'
import type { ScanResult } from '@/types/phase2'

export interface ScanResultView {
  scan_event_id?: string | null
  result: ScanResult
  reason_code: string
  attendee_id?: string | null
  credential_id?: string | null
  attendee_display_name?: string | null
  ticket_type_label?: string | null
}

interface ScanResultCardProps {
  result: ScanResultView | null
}

type ResultTone = 'success' | 'warning' | 'danger' | 'info'

function resultTone(result: ScanResult): ResultTone {
  switch (result) {
    case 'accepted':
    case 'manual_override':
      return 'success'
    case 'duplicate':
      return 'warning'
    case 'revoked':
    case 'expired':
    case 'rejected':
      return 'danger'
    default:
      return 'info'
  }
}

function toneIcon(tone: ResultTone): ReactNode {
  switch (tone) {
    case 'success':
      return <CheckCircle2 className="h-5 w-5" aria-hidden />
    case 'warning':
      return <AlertTriangle className="h-5 w-5" aria-hidden />
    case 'danger':
      return <XCircle className="h-5 w-5" aria-hidden />
    default:
      return <Info className="h-5 w-5" aria-hidden />
  }
}

export function ScanResultCard({ result }: ScanResultCardProps) {
  const { locale, t } = useLocale()

  if (result === null) {
    return null
  }

  const tone = resultTone(result.result)
  const attendeeName = result.attendee_display_name?.trim() || t('notAvailable')
  const ticketType = result.ticket_type_label?.trim() || t('notAvailable')
  const reason = scanReasonLabel(result.reason_code, locale)

  return (
    <section
      className={`scan-attendee-panel scan-attendee-panel--${tone}`}
      data-testid="scan-result-card"
      aria-live="polite"
    >
      <div className="scan-attendee-panel__hero">
        <div className={`scan-attendee-panel__icon scan-attendee-panel__icon--${tone}`}>
          {toneIcon(tone)}
        </div>

        <div className="min-w-0 flex-1">
          <p className="scan-attendee-panel__eyebrow">{t('scanAttendeeTitle')}</p>
          <h3 className="scan-attendee-panel__name">{attendeeName}</h3>
        </div>

        <StatusBadge status={result.result} size="md" />
      </div>

      <dl className="scan-attendee-panel__meta">
        <div className="scan-attendee-panel__field">
          <dt>{t('scanTicketType')}</dt>
          <dd>{ticketType}</dd>
        </div>
        <div className="scan-attendee-panel__field scan-attendee-panel__field--wide">
          <dt>{t('scanResultReason')}</dt>
          <dd className="scan-attendee-panel__reason">{reason}</dd>
        </div>
      </dl>
    </section>
  )
}
