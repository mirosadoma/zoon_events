import { ScanResultCard } from '@/components/checkin/ScanResultCard'
import type { ScanResult } from '@/types/phase2'

interface DeskScanResult {
  scan_event_id: string
  result: string
  reason_code: string | null
  attendee_display_name: string | null
  ticket_type_label: string | null
}

interface CheckInResultPanelProps {
  result: DeskScanResult | null
}

export function CheckInResultPanel({ result }: CheckInResultPanelProps) {
  if (result === null) {
    return null
  }

  return (
    <ScanResultCard
      result={{
        scan_event_id: result.scan_event_id,
        result: result.result as ScanResult,
        reason_code: result.reason_code ?? 'unknown',
        attendee_display_name: result.attendee_display_name,
        ticket_type_label: result.ticket_type_label,
      }}
    />
  )
}
