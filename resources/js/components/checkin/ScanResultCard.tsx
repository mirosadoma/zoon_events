import type { ScanResult } from '@/types/phase2'

export interface ScanResultView {
  result: ScanResult
  reason_code: string
  attendee_display_name?: string | null
  ticket_type_label?: string | null
}

interface ScanResultCardProps {
  result: ScanResultView | null
}

export function ScanResultCard({ result }: ScanResultCardProps) {
  if (result === null) {
    return null
  }

  return (
    <div className="scan-result-card" data-testid="scan-result-card">
      <p>{result.result}</p>
      <p>{result.reason_code}</p>
      {result.attendee_display_name ? <p>{result.attendee_display_name}</p> : null}
      {result.ticket_type_label ? <p>{result.ticket_type_label}</p> : null}
    </div>
  )
}
