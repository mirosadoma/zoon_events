import { FormEvent, useState } from 'react'

interface WalkUpFormData {
  first_name: string
  last_name: string
  email: string
  phone: string
}

interface WalkUpFormPanelProps {
  eventId: string
  ticketTypeId: string
  onSuccess: (attendeeId: string) => void
  onCancel: () => void
}

const empty = (): WalkUpFormData => ({ first_name: '', last_name: '', email: '', phone: '' })

export function WalkUpFormPanel({ eventId, ticketTypeId, onSuccess, onCancel }: WalkUpFormPanelProps) {
  const [buyer, setBuyer] = useState<WalkUpFormData>(empty())
  const [attendee, setAttendee] = useState<WalkUpFormData>(empty())
  const [sameAsBuyer, setSameAsBuyer] = useState(true)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const effectiveAttendee = sameAsBuyer ? buyer : attendee

  function handleBuyerChange(field: keyof WalkUpFormData, value: string) {
    setBuyer(prev => ({ ...prev, [field]: value }))
  }

  function handleAttendeeChange(field: keyof WalkUpFormData, value: string) {
    setAttendee(prev => ({ ...prev, [field]: value }))
  }

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const res = await fetch(`/api/v1/tenant/events/${eventId}/walk-up-registrations`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          ticket_type_id: ticketTypeId,
          form_version_id: 'default',
          idempotency_key: `walk-up-${Date.now()}`,
          locale: 'en',
          buyer,
          attendee: effectiveAttendee,
          answers: {},
          consent: {},
        }),
      })

      if (!res.ok) {
        const data = await res.json()
        setError(data.title ?? 'Registration failed')
        return
      }

      const data = await res.json()
      onSuccess(data.data.attendee_id)
    } catch {
      setError('Network error. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div>
      <h2>Walk-Up Registration</h2>
      {error && <p role="alert">{error}</p>}
      <form onSubmit={handleSubmit}>
        <fieldset>
          <legend>Buyer Details</legend>
          <input placeholder="First Name" value={buyer.first_name} onChange={e => handleBuyerChange('first_name', e.target.value)} required />
          <input placeholder="Last Name" value={buyer.last_name} onChange={e => handleBuyerChange('last_name', e.target.value)} required />
          <input type="email" placeholder="Email" value={buyer.email} onChange={e => handleBuyerChange('email', e.target.value)} required />
          <input placeholder="Phone (optional)" value={buyer.phone} onChange={e => handleBuyerChange('phone', e.target.value)} />
        </fieldset>

        <label>
          <input type="checkbox" checked={sameAsBuyer} onChange={e => setSameAsBuyer(e.target.checked)} />
          Attendee same as buyer
        </label>

        {!sameAsBuyer && (
          <fieldset>
            <legend>Attendee Details</legend>
            <input placeholder="First Name" value={attendee.first_name} onChange={e => handleAttendeeChange('first_name', e.target.value)} required />
            <input placeholder="Last Name" value={attendee.last_name} onChange={e => handleAttendeeChange('last_name', e.target.value)} required />
            <input type="email" placeholder="Email" value={attendee.email} onChange={e => handleAttendeeChange('email', e.target.value)} required />
            <input placeholder="Phone (optional)" value={attendee.phone} onChange={e => handleAttendeeChange('phone', e.target.value)} />
          </fieldset>
        )}

        <button type="submit" disabled={loading}>{loading ? 'Registering…' : 'Register'}</button>
        <button type="button" onClick={onCancel}>Cancel</button>
      </form>
    </div>
  )
}
