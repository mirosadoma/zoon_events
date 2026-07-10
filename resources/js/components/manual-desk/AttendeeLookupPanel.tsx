interface LookupMatch {
  attendee_id: string | null
  credential_id: string | null
  display_name: string | null
  ticket_type_label: string | null
  checkin_status: string
}

interface AttendeeLookupResult {
  too_many: boolean
  matches: LookupMatch[]
}

interface AttendeeLookupPanelProps {
  result: AttendeeLookupResult | null
  loading: boolean
  onSelect: (match: LookupMatch) => void
}

export function AttendeeLookupPanel({ result, loading, onSelect }: AttendeeLookupPanelProps) {
  if (loading) {
    return <div>Searching…</div>
  }

  if (result === null) {
    return null
  }

  if (result.too_many) {
    return <div>Too many matches. Please refine your search.</div>
  }

  if (result.matches.length === 0) {
    return <div>No attendees found.</div>
  }

  return (
    <ul>
      {result.matches.map((match, i) => (
        <li key={match.attendee_id ?? i}>
          <button type="button" onClick={() => onSelect(match)}>
            {match.display_name ?? 'Unknown'} — {match.ticket_type_label ?? ''} — {match.checkin_status}
          </button>
        </li>
      ))}
    </ul>
  )
}
