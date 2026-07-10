interface ScanResult {
  scan_event_id: string
  result: string
  reason_code: string | null
  attendee_display_name: string | null
  ticket_type_label: string | null
}

interface CheckInResultPanelProps {
  result: ScanResult | null
}

export function CheckInResultPanel({ result }: CheckInResultPanelProps) {
  if (result === null) {
    return null
  }

  return (
    <div>
      <p>Result: {result.result}</p>
      {result.attendee_display_name && <p>Attendee: {result.attendee_display_name}</p>}
      {result.ticket_type_label && <p>Ticket: {result.ticket_type_label}</p>}
      {result.reason_code && <p>Reason: {result.reason_code}</p>}
    </div>
  )
}
