import { FormEvent, useLayoutEffect, useMemo, useRef, useState } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import RegistrationEventHero, { type RegistrationHeroEvent } from '@/components/registration/RegistrationEventHero'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import RegistrationVenueSelect from '@/components/registration/RegistrationVenueSelect'
import { RegistrationField, type PublicFormField } from '@/components/registration/RegistrationField'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import FormSavingOverlay from '@/components/loaders/FormSavingOverlay'
import { useFormValidation } from '@/hooks/useFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import {
  buildPublicRegistrationFieldLabels,
  publicRegistrationFieldSelector,
  remapPublicRegistrationApiErrors,
} from '@/lib/publicRegistrationValidation'

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
  submitUrl?: string
  event: RegistrationHeroEvent & {
    id?: string
    slug?: string
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
  identityVerifyUrl?: string | null
  credentialStatus?: 'issued' | 'pending_identity' | 'unavailable'
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

function answerText(value: string | boolean | string[] | undefined): string {
  if (typeof value === 'string') {
    return value
  }

  if (Array.isArray(value)) {
    return value.join(' ').trim()
  }

  return ''
}

export default function PublicRegistrationEvent({
  locale,
  isPreview = false,
  submitUrl,
  event,
  form,
  ticketTypes = [],
}: Props) {
  const { t } = useLocale()
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const fieldLabels = useMemo(
    () => buildPublicRegistrationFieldLabels(form.fields, {
      en: 'Location - Date',
      ar: 'الموقع - التاريخ',
    }, {
      en: t('publicRegistrationAcceptTerms'),
      ar: t('publicRegistrationAcceptTerms'),
    }),
    [form.fields, t],
  )
  const validation = useFormValidation({
    titleKey: 'couldNotCompleteRegistration',
    fieldLabels,
    remapErrors: remapPublicRegistrationApiErrors,
    selectorForKey: publicRegistrationFieldSelector,
  })
  const venues = event.venues ?? []
  const formRef = useRef<HTMLFormElement>(null)
  const [formTarget, setFormTarget] = useState<HTMLElement | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<SuccessState | null>(null)
  const [ticketTypeId, setTicketTypeId] = useState(String(ticketTypes[0]?.id ?? ''))
  const [venueId, setVenueId] = useState(String(venues[0]?.id ?? ''))
  const [acceptedTerms, setAcceptedTerms] = useState(false)

  useLayoutEffect(() => {
    setFormTarget(formRef.current)
  }, [])

  const selectedTicket = useMemo(
    () => ticketTypes.find((ticket) => ticket.id === ticketTypeId) ?? ticketTypes[0] ?? null,
    [ticketTypeId, ticketTypes],
  )

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    if (isPreview) {
      return
    }

    setError(null)
    validation.clearValidation()

    if (!form.version_id) {
      setError(t('publicRegistrationFormUnavailable'))
      return
    }

    if (!submitUrl) {
      setError(t('publicRegistrationLinkUnavailable'))
      return
    }

    if (!ticketTypeId) {
      setError(t('publicRegistrationSelectTicketFirst'))
      return
    }

    if (venues.length > 0 && !venueId) {
      setError(t('publicRegistrationSelectLocationDate'))
      return
    }

    if (!acceptedTerms) {
      setError(t('publicRegistrationAcceptTermsRequired'))
      return
    }

    const formData = new FormData(submitEvent.currentTarget)
    const answers: Record<string, string | boolean | string[]> = {}
    form.fields.forEach((field) => {
      if (field.type === 'multi_select' || field.type === 'checkbox') {
        const values = formData.getAll(field.key).map(String).filter(Boolean)
        if (values.length > 0) {
          answers[field.key] = values
        }
        return
      }

      if (field.type === 'consent') {
        answers[field.key] = formData.get(field.key) === 'true'
        return
      }

      const value = String(formData.get(field.key) ?? '').trim()
      if (value !== '') {
        answers[field.key] = value
      }
    })

    const fullName = answerText(answers.full_name)
      || answerText(answers.name)
      || `${answerText(answers.first_name)} ${answerText(answers.last_name)}`.trim()
    const email = answerText(answers.email)
    const phone = answerText(answers.phone) || undefined
    const person = { ...splitName(fullName), email, phone }

    setSubmitting(true)

    try {
      const result = await apiFetch<{
        public_reference: string
        access_token?: string | null
        credential_token?: string | null
        identity_verify_url?: string | null
        credential_status?: 'issued' | 'pending_identity' | 'unavailable'
        credential?: { qr_payload?: string | null } | null
      }>(submitUrl!, {
        method: 'POST',
        idempotency: true,
        body: {
          form_version_id: String(form.version_id),
          ticket_type_id: String(ticketTypeId),
          event_venue_id: venues.length > 0 ? String(venueId) : null,
          buyer: person,
          attendee: person,
          answers,
          consents: { terms: true, privacy: true, marketing: false },
        },
      })

      setSuccess({
        reference: result.public_reference,
        accessToken: result.access_token,
        credentialToken: result.credential_token ?? result.credential?.qr_payload ?? null,
        identityVerifyUrl: result.identity_verify_url ?? null,
        credentialStatus: result.credential_status,
      })
    } catch (caught) {
      if (validation.applyApiError(caught)) {
        setError(null)
      } else {
        const message = caught instanceof ApiFetchError
          ? caught.message
          : t('publicRegistrationFailed')
        setError(message)
      }
    } finally {
      setSubmitting(false)
    }
  }

  if (success) {
    return (
      <>
        <RegistrationPageControls locale={locale} />
        <main className="registration-invite registration-invite-success" lang={locale} dir={direction}>
        <div className="registration-invite-card">
          <p className="registration-invite-kicker">{t('publicRegistrationSuccessKicker')}</p>
          <h1>{t('publicRegistrationWelcomeEvent')}</h1>
          <p className="registration-invite-lead">
            <LocalizedEventContent value={event.name} locale={locale} />
          </p>
          <div className="registration-success-meta">
            <div>
              <span className="registration-success-label">{t('publicRegistrationOrderReference')}</span>
              <strong>{success.reference}</strong>
            </div>
            {selectedTicket ? (
              <div>
                <span className="registration-success-label">{t('publicRegistrationTicket')}</span>
                <strong><LocalizedEventContent value={selectedTicket.name} locale={locale} /></strong>
              </div>
            ) : null}
          </div>
          {success.credentialToken ? (
            <div className="registration-qr-panel">
              <p>{t('publicRegistrationScanQr')}</p>
              <QRCodeSVG value={success.credentialToken} size={220} className="registration-qr-code" />
            </div>
          ) : success.credentialStatus === 'pending_identity' && (success.identityVerifyUrl || (success.accessToken && event.slug)) ? (
            <div className="registration-qr-panel">
              <p>{t('publicRegistrationIdentityPending')}</p>
              <LocalizedLink
                href={success.identityVerifyUrl ?? `/identity/${event.slug}/${success.accessToken}`}
                className="button-primary inline-flex"
              >
                {t('publicRegistrationCompleteIdentity')}
              </LocalizedLink>
            </div>
          ) : null}
          <p className="registration-invite-footnote">
            {t('publicRegistrationEmailFootnote')}
          </p>
        </div>
      </main>
      </>
    )
  }

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className={`registration-invite${isPreview ? ' registration-invite-preview' : ''}`} lang={locale} dir={direction}>
        <RegistrationEventHero locale={locale} event={event} isPreview={isPreview}>
          {ticketTypes.length > 0 ? (
            <section className="registration-ticket-picker" aria-label={t('publicRegistrationTicketSelection')}>
              <h2>{t('publicRegistrationChooseTicket')}</h2>
              <div className="registration-ticket-options">
                {ticketTypes.map((ticket) => {
                  const selected = ticket.id === ticketTypeId
                  const price = (ticket.price_minor / 100).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US', {
                    style: 'currency',
                    currency: ticket.currency,
                  })

                  return (
                    <button
                      key={ticket.id}
                      type="button"
                      className={selected ? 'registration-ticket-option registration-ticket-option-active' : 'registration-ticket-option'}
                      onClick={() => setTicketTypeId(String(ticket.id))}
                      disabled={isPreview}
                      aria-disabled={isPreview}
                    >
                      <span className="registration-ticket-code">{ticket.code}</span>
                      <span className="registration-ticket-name"><LocalizedEventContent value={ticket.name} locale={locale} /></span>
                      <span className="registration-ticket-price">{ticket.price_minor === 0 ? t('publicRegistrationFree') : price}</span>
                    </button>
                  )
                })}
              </div>
            </section>
          ) : (
            <p className="registration-invite-warning">
              {t('publicRegistrationNoTickets')}
            </p>
          )}

          <form
            ref={formRef}
            className="registration-invite-form form-saving-scope-root"
            aria-label={t('publicRegistrationFormAria')}
            onSubmit={handleSubmit}
          >
            <FormSavingOverlay active={submitting} target={formTarget} label={t('publicRegistrationRegistering')} />

            <RegistrationVenueSelect
              locale={locale}
              venues={venues}
              value={venueId}
              onChange={setVenueId}
              disabled={isPreview}
              error={validation.fieldError('event_venue_id')}
            />

            {form.fields.map((field) => (
              <RegistrationField
                key={field.key}
                field={field}
                locale={locale}
                disabled={isPreview}
                error={validation.fieldError(field.key)}
                data-form-field={field.key}
              />
            ))}

            <label className={`registration-consent${validation.fieldError('consent') ? ' form-field-invalid' : ''}`}>
              <input
                type="checkbox"
                name="consent"
                checked={acceptedTerms}
                onChange={(changeEvent) => setAcceptedTerms(changeEvent.target.checked)}
                required={!isPreview}
                disabled={isPreview}
                data-form-field="consent"
                aria-invalid={validation.fieldError('consent') ? 'true' : undefined}
              />
              <span>
                {t('publicRegistrationAcceptTerms')}
                {' '}
                {t('publicRegistrationTerms')}
                {' '}
                {form.terms_version}
                {' '}
                {t('publicRegistrationAndPrivacy')}
                {' '}
                {form.privacy_notice_version}
              </span>
            </label>

            {error ? <p role="alert" className="registration-invite-error">{error}</p> : null}

            {!isPreview ? (
              <button type="submit" className="button-primary registration-invite-submit" disabled={submitting || ticketTypes.length === 0}>
                {submitting ? t('publicRegistrationRegistering') : t('publicRegistrationComplete')}
              </button>
            ) : (
              <p className="registration-invite-footnote">
                {t('publicRegistrationPreviewFootnote')}
              </p>
            )}
          </form>
        </RegistrationEventHero>
        <ValidationHintPopover {...validation.hintProps} />
      </main>
    </>
  )
}
