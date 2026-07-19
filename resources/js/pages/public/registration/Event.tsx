import { FormEvent, useLayoutEffect, useMemo, useRef, useState } from 'react'
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
import { normalizeRegistrationPhone } from '@/lib/normalizeRegistrationPhone'
import {
  buildPublicRegistrationFieldLabels,
  collectPublicRegistrationClientErrors,
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

type CategoryOption = {
  id: string
  name: LocalizedText
  color: string | null
  is_paid: boolean
  price_minor: number
  currency: string
  capacity?: number | null
  remaining?: number | null
  is_full?: boolean
}

type ThemeConfig = {
  primary_color?: string
  accent_color?: string
  background_color?: string
  font_family?: string
  logo_path?: string
  header_image_path?: string
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
  categories?: CategoryOption[]
  requiresCategorySelection?: boolean
  ticketTypes?: TicketTypeOption[]
  requiresTicketSelection?: boolean
  theme?: ThemeConfig | null
  inviteCode?: string | null
  lockedEmail?: string | null
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
  categories = [],
  requiresCategorySelection = false,
  ticketTypes = [],
  requiresTicketSelection = true,
  theme,
  inviteCode = null,
  lockedEmail = null,
}: Props) {
  const { t } = useLocale()
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const themeStyle = useMemo(() => {
    if (!theme) return undefined
    const vars: Record<string, string> = {}
    if (theme.primary_color) vars['--reg-primary'] = theme.primary_color
    if (theme.accent_color) vars['--reg-accent'] = theme.accent_color
    if (theme.background_color) vars['--reg-bg'] = theme.background_color
    if (theme.font_family) vars['--reg-font'] = theme.font_family
    return Object.keys(vars).length > 0 ? vars as React.CSSProperties : undefined
  }, [theme])
  const registrationFields = useMemo(
    () => form.fields.filter((field) => field.type !== 'consent'),
    [form.fields],
  )
  const fieldLabels = useMemo(
    () => ({
      ...buildPublicRegistrationFieldLabels(registrationFields, {
        en: 'Location - Date',
        ar: 'الموقع - التاريخ',
      }, {
        en: t('publicRegistrationConsentLabel'),
        ar: t('publicRegistrationConsentLabel'),
      }, {
        en: t('publicRegistrationChooseTicket'),
        ar: t('publicRegistrationChooseTicket'),
      }),
      event_category_id: {
        en: t('publicRegistrationChooseCategory'),
        ar: t('publicRegistrationChooseCategory'),
      },
    }),
    [registrationFields, t],
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
  const [ticketTypeId, setTicketTypeId] = useState(String(ticketTypes[0]?.id ?? ''))
  const availableCategories = useMemo(
    () => categories.filter((category) => !category.is_full),
    [categories],
  )
  const [categoryId, setCategoryId] = useState(String(availableCategories[0]?.id ?? categories[0]?.id ?? ''))
  const [venueId, setVenueId] = useState(String(venues[0]?.id ?? ''))
  const [acceptedTerms, setAcceptedTerms] = useState(false)

  useLayoutEffect(() => {
    setFormTarget(formRef.current)
  }, [])

  useLayoutEffect(() => {
    if (categoryId && availableCategories.some((category) => category.id === categoryId)) {
      return
    }
    setCategoryId(String(availableCategories[0]?.id ?? ''))
  }, [availableCategories, categoryId])

  const canSubmit = requiresCategorySelection
    ? availableCategories.length > 0
    : !requiresTicketSelection || ticketTypes.length > 0

  // Private invite links skip category/ticket pickers entirely.
  const showCategoryPicker = requiresCategorySelection
  const showTicketPicker = !requiresCategorySelection && requiresTicketSelection
  const showSelectionWarning = showCategoryPicker
    ? categories.length === 0 || availableCategories.length === 0
    : showTicketPicker && ticketTypes.length === 0

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

    const formData = new FormData(submitEvent.currentTarget)
    const answers: Record<string, string | boolean | string[]> = {}
    registrationFields.forEach((field) => {
      if (field.type === 'multi_select' || field.type === 'checkbox') {
        const values = formData.getAll(field.key).map(String).filter(Boolean)
        if (values.length > 0) {
          answers[field.key] = values
        }
        return
      }

      const value = String(formData.get(field.key) ?? '').trim()
      if (value !== '') {
        answers[field.key] = value
      }
    })

    if (typeof answers.phone === 'string') {
      answers.phone = normalizeRegistrationPhone(answers.phone)
    }

    if (lockedEmail) {
      const locked = lockedEmail.trim().toLowerCase()
      const emailFieldKeys = registrationFields
        .filter((field) => field.type === 'email' || field.key === 'email')
        .map((field) => field.key)

      let mismatchedKey: string | null = null
      for (const key of emailFieldKeys) {
        const submitted = String(answers[key] ?? '').trim().toLowerCase()
        if (submitted !== '' && submitted !== locked) {
          mismatchedKey = key
          break
        }
      }

      if (mismatchedKey === null) {
        const submitted = answerText(answers.email).toLowerCase()
        if (submitted !== '' && submitted !== locked) {
          mismatchedKey = 'email'
        }
      }

      if (mismatchedKey !== null) {
        validation.applyErrors({
          [mismatchedKey]: t('publicRegistrationInviteEmailLocked'),
        })
        setError(t('publicRegistrationInviteEmailLocked'))
        return
      }

      answers.email = locked
      emailFieldKeys.forEach((key) => {
        answers[key] = locked
      })
    }

    const clientErrors = collectPublicRegistrationClientErrors(registrationFields, answers, {
      ticketTypeId,
      categoryId,
      requireCategory: requiresCategorySelection,
      requireTicket: requiresTicketSelection,
      venueRequired: venues.length > 0,
      venueId,
      acceptedTerms,
    })

    if (validation.applyErrors(clientErrors)) {
      return
    }

    const fullName = answerText(answers.full_name)
      || answerText(answers.name)
      || `${answerText(answers.first_name)} ${answerText(answers.last_name)}`.trim()
    const email = (lockedEmail?.trim() || answerText(answers.email)).toLowerCase()
    const phone = answerText(answers.phone) ? normalizeRegistrationPhone(answerText(answers.phone)) : undefined
    const person = { ...splitName(fullName), email, phone }
    answers.email = email

    setSubmitting(true)

    try {
      const result = await apiFetch<{
        next?: string
        otp_url?: string
        public_reference?: string
        access_token?: string | null
      }>(submitUrl!, {
        method: 'POST',
        idempotency: true,
        body: {
          form_version_id: String(form.version_id),
          event_category_id: requiresCategorySelection ? Number(categoryId) : undefined,
          ticket_type_id: requiresTicketSelection ? String(ticketTypeId) : undefined,
          event_venue_id: venues.length > 0 ? String(venueId) : null,
          invite_code: inviteCode || undefined,
          buyer: person,
          attendee: person,
          answers,
          consents: { terms: true, privacy: true, marketing: false },
        },
      })

      if (result.otp_url) {
        window.location.assign(result.otp_url)
        return
      }

      setError(t('publicRegistrationFailed'))
    } catch (caught) {
      if (validation.applyApiError(caught)) {
        setError(null)
      } else if (caught instanceof ApiFetchError) {
        const fieldMessages = Object.values(caught.errors).map((message) => message.trim()).filter(Boolean)
        setError(fieldMessages.length > 0 ? fieldMessages.join(' ') : caught.message)
      } else {
        setError(t('publicRegistrationFailed'))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className={`registration-invite${isPreview ? ' registration-invite-preview' : ''}`} lang={locale} dir={direction} style={themeStyle}>
        <RegistrationEventHero locale={locale} event={event} isPreview={isPreview}>
          {showCategoryPicker ? (
            categories.length > 0 ? (
              <section
                className={`registration-ticket-picker${validation.fieldError('event_category_id') ? ' form-field-invalid' : ''}`}
                aria-label={t('publicRegistrationCategorySelection')}
                data-form-field="event_category_id"
              >
                <h2>{t('publicRegistrationChooseCategory')}</h2>
                <div className="registration-ticket-options">
                  {categories.map((category) => {
                    const selected = category.id === categoryId
                    const isFull = Boolean(category.is_full)
                    const price = (category.price_minor / 100).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US', {
                      style: 'currency',
                      currency: category.currency || 'SAR',
                    })
                    const remainingLabel = category.remaining !== null && category.remaining !== undefined
                      ? t('publicRegistrationCategoryRemaining').replace(':count', String(category.remaining))
                      : null

                    return (
                      <button
                        key={category.id}
                        type="button"
                        className={[
                          'registration-ticket-option',
                          selected && !isFull ? 'registration-ticket-option-active' : '',
                          isFull ? 'registration-ticket-option-full' : '',
                        ].filter(Boolean).join(' ')}
                        onClick={() => {
                          if (!isFull) {
                            setCategoryId(String(category.id))
                          }
                        }}
                        disabled={isPreview || isFull}
                        aria-disabled={isPreview || isFull}
                      >
                        <span className="registration-ticket-option-top">
                          <span
                            className="registration-ticket-code"
                            style={category.color ? { color: category.color } : undefined}
                          >
                            {category.is_paid ? price : t('publicRegistrationFree')}
                          </span>
                          {isFull ? (
                            <span className="registration-ticket-badge registration-ticket-badge-full">
                              {t('publicRegistrationCategoryFull')}
                            </span>
                          ) : remainingLabel ? (
                            <span className="registration-ticket-badge">
                              {remainingLabel}
                            </span>
                          ) : null}
                        </span>
                        <span className="registration-ticket-name">
                          <LocalizedEventContent value={category.name} locale={locale} />
                        </span>
                      </button>
                    )
                  })}
                </div>
              </section>
            ) : (
              <p className="registration-invite-warning">
                {t('publicRegistrationNoCategories')}
              </p>
            )
          ) : showTicketPicker && ticketTypes.length > 0 ? (
            <section
              className={`registration-ticket-picker${validation.fieldError('ticket_type') ? ' form-field-invalid' : ''}`}
              aria-label={t('publicRegistrationTicketSelection')}
              data-form-field="ticket_type"
            >
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
          ) : showSelectionWarning ? (
            <p className="registration-invite-warning">
              {showCategoryPicker
                ? (categories.length > 0
                  ? t('publicRegistrationAllCategoriesFull')
                  : t('publicRegistrationNoCategories'))
                : t('publicRegistrationNoTickets')}
            </p>
          ) : null}

          <form
            ref={formRef}
            noValidate
            className="registration-invite-form form-saving-scope-root"
            aria-label={t('publicRegistrationFormAria')}
            onSubmit={handleSubmit}
          >
            <FormSavingOverlay active={submitting} target={formTarget} label={t('publicRegistrationRegistering')} />

            <RegistrationVenueSelect
              locale={locale}
              venues={venues}
              value={venueId}
              onChange={(nextVenueId) => {
                setVenueId(nextVenueId)
                validation.clearField('event_venue_id')
              }}
              disabled={isPreview}
              error={validation.fieldError('event_venue_id')}
            />

            {registrationFields.map((field) => {
              const isLockedEmail = Boolean(lockedEmail) && (field.type === 'email' || field.key === 'email')

              return (
                <RegistrationField
                  key={field.key}
                  field={field}
                  locale={locale}
                  disabled={isPreview}
                  readOnly={isLockedEmail}
                  value={isLockedEmail ? (lockedEmail ?? '') : undefined}
                  error={validation.fieldError(field.key)}
                  data-form-field={field.key}
                />
              )
            })}

            <label className={`registration-consent${validation.fieldError('consent') ? ' form-field-invalid' : ''}`}>
              <input
                type="checkbox"
                name="consent"
                checked={acceptedTerms}
                onChange={(changeEvent) => {
                  setAcceptedTerms(changeEvent.target.checked)
                  validation.clearField('consent')
                }}
                required={!isPreview}
                disabled={isPreview}
                data-form-field="consent"
                aria-invalid={validation.fieldError('consent') ? 'true' : undefined}
              />
              <span>{t('publicRegistrationConsentLabel')}</span>
            </label>

            {error ? <p role="alert" className="registration-invite-error">{error}</p> : null}

            {!isPreview ? (
              <button type="submit" className="button-primary registration-invite-submit" disabled={submitting || !canSubmit}>
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
