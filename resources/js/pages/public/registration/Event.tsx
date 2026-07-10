import { FormEvent, useMemo, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { RegistrationField, type PublicFormField } from '@/components/registration/RegistrationField'
import StatusBadge from '@/components/status/StatusBadge'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

type TicketTypeOption = {
  id: string
  code: string
  name: LocalizedText
  price_minor: number
  currency: string
}

type Props = {
  locale: 'en' | 'ar'
  tenantId?: string
  isPreview?: boolean
  event: {
    id?: string
    slug?: string
    name: LocalizedText
    description: LocalizedText
    start_at?: string | null
    end_at?: string | null
    branding: { brand_reference: string | null; domain_reference?: string | null }
  }
  form: {
    version_id?: string | null
    fields: PublicFormField[]
    privacy_notice_version: string
    terms_version: string
  }
  ticketTypes?: TicketTypeOption[]
}

type SuccessState = {
  reference: string
  accessToken?: string | null
  credentialToken?: string | null
}

function splitName(value: string): { first_name: string; last_name: string } {
  const trimmed = value.trim()
  if (trimmed === '') {
    return { first_name: 'Guest', last_name: 'Attendee' }
  }

  const parts = trimmed.split(/\s+/)
  const first = parts.shift() ?? 'Guest'
  const last = parts.join(' ') || first

  return { first_name: first, last_name: last }
}

export default function PublicRegistrationEvent({
  locale,
  tenantId,
  isPreview = false,
  event,
  form,
  ticketTypes = [],
}: Props) {
  const rtl = locale === 'ar'
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<SuccessState | null>(null)
  const [ticketTypeId, setTicketTypeId] = useState(String(ticketTypes[0]?.id ?? ''))
  const [acceptedTerms, setAcceptedTerms] = useState(false)

  const selectedTicket = useMemo(
    () => ticketTypes.find((ticket) => ticket.id === ticketTypeId) ?? ticketTypes[0] ?? null,
    [ticketTypeId, ticketTypes],
  )

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    setError(null)

    if (!isPreview || !tenantId || !event.id || !form.version_id) {
      setError(rtl ? 'معاينة التسجيل غير مهيأة بالكامل.' : 'Registration preview is not fully configured.')
      return
    }

    if (!ticketTypeId) {
      setError(rtl ? 'اختر نوع التذكرة أولاً.' : 'Select a ticket type first.')
      return
    }

    if (!acceptedTerms) {
      setError(rtl ? 'يجب الموافقة على الشروط وسياسة الخصوصية.' : 'You must accept the terms and privacy notice.')
      return
    }

    const formData = new FormData(submitEvent.currentTarget)
    const answers: Record<string, string> = {}
    form.fields.forEach((field) => {
      const value = String(formData.get(field.key) ?? '').trim()
      if (value !== '') {
        answers[field.key] = value
      }
    })

    const fullName = answers.full_name ?? answers.name ?? `${answers.first_name ?? ''} ${answers.last_name ?? ''}`.trim()
    const email = answers.email ?? ''
    const phone = answers.phone ?? undefined
    const person = { ...splitName(fullName), email, phone }

    setSubmitting(true)

    try {
      const result = await apiFetch<{
        public_reference: string
        access_token?: string | null
        credential_token?: string | null
      }>(`/api/v1/tenant/events/${event.id}/registration-preview`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          form_version_id: String(form.version_id),
          ticket_type_id: String(ticketTypeId),
          buyer: person,
          attendee: person,
          answers,
          consents: { terms: true, privacy: true, marketing: false },
        },
      })

      setSuccess({
        reference: result.public_reference,
        accessToken: result.access_token,
        credentialToken: result.credential_token,
      })
    } catch (caught) {
      const message = caught instanceof ApiFetchError
        ? caught.message
        : (rtl ? 'تعذر إكمال التسجيل.' : 'Registration could not be completed.')
      setError(message)
    } finally {
      setSubmitting(false)
    }
  }

  if (success) {
    return (
      <main className="registration-invite registration-invite-success" lang={locale} dir={rtl ? 'rtl' : 'ltr'}>
        <div className="registration-invite-card">
          <p className="registration-invite-kicker">{rtl ? 'تم التسجيل بنجاح' : 'You are registered'}</p>
          <h1>{rtl ? 'أهلاً بك في الفعالية' : 'Welcome to the event'}</h1>
          <p className="registration-invite-lead">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>
          <div className="registration-success-meta">
            <div>
              <span className="registration-success-label">{rtl ? 'مرجع الطلب' : 'Order reference'}</span>
              <strong>{success.reference}</strong>
            </div>
            {selectedTicket ? (
              <div>
                <span className="registration-success-label">{rtl ? 'التذكرة' : 'Ticket'}</span>
                <strong><LocalizedEventContent value={selectedTicket.name} locale={locale} /></strong>
              </div>
            ) : null}
          </div>
          {success.credentialToken ? (
            <div className="registration-qr-panel">
              <p>{rtl ? 'امسح رمز QR عند البوابة' : 'Scan this QR code at the gate'}</p>
              <QRCodeSVG value={success.credentialToken} size={220} className="registration-qr-code" />
            </div>
          ) : null}
          <p className="registration-invite-footnote">
            {rtl
              ? 'تم إرسال تأكيد بالبريد الإلكتروني إن كان البريد صالحاً ومُفعَّلاً في بيئة العرض.'
              : 'A confirmation email was queued when the address is valid in this demo environment.'}
          </p>
        </div>
      </main>
    )
  }

  return (
    <main className="registration-invite" lang={locale} dir={rtl ? 'rtl' : 'ltr'}>
      <div className="registration-invite-hero">
        <div className="registration-invite-card">
          <header className="registration-invite-header">
            {event.branding.brand_reference ? (
              <p className="registration-invite-brand">{event.branding.brand_reference}</p>
            ) : null}
            <p className="registration-invite-kicker">{rtl ? 'دعوة للتسجيل' : 'You are invited'}</p>
            <h1><LocalizedEventContent value={event.name} locale={locale} /></h1>
            <p className="registration-invite-lead"><LocalizedEventContent value={event.description} locale={locale} /></p>
            {event.start_at ? (
              <p className="registration-invite-schedule">
                {new Date(event.start_at).toLocaleString(rtl ? 'ar-EG' : 'en-US')}
                {event.end_at ? ` — ${new Date(event.end_at).toLocaleString(rtl ? 'ar-EG' : 'en-US')}` : ''}
              </p>
            ) : null}
          </header>

          {ticketTypes.length > 0 ? (
            <section className="registration-ticket-picker" aria-label={rtl ? 'اختيار التذكرة' : 'Ticket selection'}>
              <h2>{rtl ? 'اختر تذكرتك' : 'Choose your ticket'}</h2>
              <div className="registration-ticket-options">
                {ticketTypes.map((ticket) => {
                  const selected = ticket.id === ticketTypeId
                  const price = (ticket.price_minor / 100).toLocaleString(rtl ? 'ar-EG' : 'en-US', {
                    style: 'currency',
                    currency: ticket.currency,
                  })

                  return (
                    <button
                      key={ticket.id}
                      type="button"
                      className={selected ? 'registration-ticket-option registration-ticket-option-active' : 'registration-ticket-option'}
                      onClick={() => setTicketTypeId(String(ticket.id))}
                    >
                      <span className="registration-ticket-code">{ticket.code}</span>
                      <span className="registration-ticket-name"><LocalizedEventContent value={ticket.name} locale={locale} /></span>
                      <span className="registration-ticket-price">{ticket.price_minor === 0 ? (rtl ? 'مجاني' : 'Free') : price}</span>
                    </button>
                  )
                })}
              </div>
            </section>
          ) : (
            <p className="registration-invite-warning">
              {rtl ? 'لا توجد تذاكر نشطة لهذه المعاينة.' : 'No active ticket types are available for this preview.'}
            </p>
          )}

          <form className="registration-invite-form" aria-label={rtl ? 'نموذج التسجيل' : 'Registration form'} onSubmit={handleSubmit}>
            {form.fields.map((field) => <RegistrationField key={field.key} field={field} locale={locale} />)}

            <label className="registration-consent">
              <input
                type="checkbox"
                name="consent"
                checked={acceptedTerms}
                onChange={(changeEvent) => setAcceptedTerms(changeEvent.target.checked)}
                required
              />
              <span>
                {rtl ? 'أوافق على' : 'I accept the'}
                {' '}
                {rtl ? 'الشروط' : 'terms'}
                {' '}
                {form.terms_version}
                {' '}
                {rtl ? 'وسياسة الخصوصية' : 'and privacy notice'}
                {' '}
                {form.privacy_notice_version}
              </span>
            </label>

            {error ? <p role="alert" className="registration-invite-error">{error}</p> : null}

            <button type="submit" className="button-primary registration-invite-submit" disabled={submitting || ticketTypes.length === 0}>
              {submitting ? (rtl ? 'جارٍ التسجيل…' : 'Registering…') : (rtl ? 'إتمام التسجيل' : 'Complete registration')}
            </button>

            {isPreview ? (
              <p className="registration-invite-footnote">
                {rtl ? 'هذه معاينة للمنظم — سيتم إنشاء تسجيل حقيقي لاختبار التدفق.' : 'Organizer preview — this creates a real registration to test the flow.'}
              </p>
            ) : null}
          </form>
        </div>
      </div>
    </main>
  )
}
