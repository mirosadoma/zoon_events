import { FormEvent, useState } from 'react'
import TextInput from '@/components/forms/TextInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { useLocale } from '@/hooks/useLocale'

interface WalkUpFormData {
  first_name: string
  last_name: string
  email: string
  phone: string
}

interface WalkUpFormPanelProps {
  eventId: string
  tenantId: string
  ticketTypeId: string
  onSuccess: (attendeeId: string) => void
  onCancel: () => void
}

const empty = (): WalkUpFormData => ({ first_name: '', last_name: '', email: '', phone: '' })

export function WalkUpFormPanel({ eventId, tenantId, ticketTypeId, onSuccess, onCancel }: WalkUpFormPanelProps) {
  const { locale } = useLocale()
  const [buyer, setBuyer] = useState<WalkUpFormData>(empty())
  const [attendee, setAttendee] = useState<WalkUpFormData>(empty())
  const [sameAsBuyer, setSameAsBuyer] = useState(true)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const effectiveAttendee = sameAsBuyer ? buyer : attendee
  const ar = locale === 'ar'

  function handleBuyerChange(field: keyof WalkUpFormData, value: string) {
    setBuyer((prev) => ({ ...prev, [field]: value }))
  }

  function handleAttendeeChange(field: keyof WalkUpFormData, value: string) {
    setAttendee((prev) => ({ ...prev, [field]: value }))
  }

  async function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    setLoading(true)
    setError(null)

    try {
      const res = await fetch(`/api/v1/tenant/events/${eventId}/walk-up-registrations`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': `walk-up-${Date.now()}`,
        },
        body: JSON.stringify({
          ticket_type_id: ticketTypeId,
          form_version_id: 'default',
          idempotency_key: `walk-up-${Date.now()}`,
          locale,
          buyer,
          attendee: effectiveAttendee,
          answers: {},
          consent: {},
        }),
      })

      if (!res.ok) {
        const data = await res.json()
        setError(data.title ?? (ar ? 'فشل التسجيل' : 'Registration failed'))
        return
      }

      const data = await res.json()
      onSuccess(data.data.attendee_id)
    } catch {
      setError(ar ? 'خطأ في الشبكة. حاول مرة أخرى.' : 'Network error. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <form className="ta-card space-y-6" onSubmit={handleSubmit}>
      <div>
        <h2 className="text-lg font-semibold text-[var(--ink)]">
          {ar ? 'بيانات المشتري' : 'Buyer details'}
        </h2>
        <p className="mt-1 text-sm text-[var(--muted)]">
          {ar ? 'أدخل بيانات الشخص الذي يدفع أو يسجّل.' : 'Enter details for the person who is registering.'}
        </p>
      </div>

      {error && (
        <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" role="alert">
          {error}
        </p>
      )}

      <div className="grid gap-4 sm:grid-cols-2">
        <TextInput
          label={ar ? 'الاسم الأول' : 'First name'}
          name="buyer_first_name"
          value={buyer.first_name}
          onChange={(e) => handleBuyerChange('first_name', e.target.value)}
          required
        />
        <TextInput
          label={ar ? 'اسم العائلة' : 'Last name'}
          name="buyer_last_name"
          value={buyer.last_name}
          onChange={(e) => handleBuyerChange('last_name', e.target.value)}
          required
        />
        <TextInput
          label={ar ? 'البريد الإلكتروني' : 'Email'}
          name="buyer_email"
          type="email"
          value={buyer.email}
          onChange={(e) => handleBuyerChange('email', e.target.value)}
          required
        />
        <TextInput
          label={ar ? 'الهاتف (اختياري)' : 'Phone (optional)'}
          name="buyer_phone"
          value={buyer.phone}
          onChange={(e) => handleBuyerChange('phone', e.target.value)}
        />
      </div>

      <CheckboxInput
        label={ar ? 'الحاضر هو نفسه المشتري' : 'Attendee same as buyer'}
        name="same_as_buyer"
        checked={sameAsBuyer}
        onChange={(e) => setSameAsBuyer(e.target.checked)}
      />

      {!sameAsBuyer && (
        <div className="space-y-4 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4">
          <h3 className="font-semibold text-[var(--ink)]">{ar ? 'بيانات الحاضر' : 'Attendee details'}</h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <TextInput
              label={ar ? 'الاسم الأول' : 'First name'}
              name="attendee_first_name"
              value={attendee.first_name}
              onChange={(e) => handleAttendeeChange('first_name', e.target.value)}
              required
            />
            <TextInput
              label={ar ? 'اسم العائلة' : 'Last name'}
              name="attendee_last_name"
              value={attendee.last_name}
              onChange={(e) => handleAttendeeChange('last_name', e.target.value)}
              required
            />
            <TextInput
              label={ar ? 'البريد الإلكتروني' : 'Email'}
              name="attendee_email"
              type="email"
              value={attendee.email}
              onChange={(e) => handleAttendeeChange('email', e.target.value)}
              required
            />
            <TextInput
              label={ar ? 'الهاتف (اختياري)' : 'Phone (optional)'}
              name="attendee_phone"
              value={attendee.phone}
              onChange={(e) => handleAttendeeChange('phone', e.target.value)}
            />
          </div>
        </div>
      )}

      <div className="flex flex-wrap gap-3 border-t border-[var(--border)] pt-4">
        <SubmitButtonWithLoader loading={loading} label={ar ? 'تسجيل' : 'Register'} />
        <button type="button" className="button-secondary" onClick={onCancel}>
          {ar ? 'إلغاء' : 'Cancel'}
        </button>
      </div>
    </form>
  )
}
