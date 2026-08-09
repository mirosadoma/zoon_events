import { FormEvent, useEffect, useLayoutEffect, useMemo, useRef, useState } from 'react'
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
import {
  hasRegistrationCardBackground,
  isDocumentDark,
  normalizeRegistrationTheme,
  registrationCardBackgroundStyle,
  registrationThemeCssVars,
  resolveRegistrationFontFamily,
  type RegistrationThemeConfig,
} from '@/lib/registrationThemeBackground'

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

type ThemeConfig = RegistrationThemeConfig

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
  requiresVenueSelection?: boolean
  ticketTypes?: TicketTypeOption[]
  requiresTicketSelection?: boolean
  theme?: ThemeConfig | null
  inviteCode?: string | null
  lockedEmail?: string | null
  prefillName?: string | null
}

const NON_ANSWER_TYPES = new Set([
  'heading',
  'divider',
  'paragraph',
  'consent',
  'hidden',
  'event_logo',
  'event_name',
  'event_venue',
  'event_dates',
  'event_description',
  'event_categories',
  'event_venue_select',
])

function fieldSlotClass(width?: string): string {
  if (width === 'half') return 'registration-form-slot registration-form-slot--half'
  if (width === 'third') return 'registration-form-slot registration-form-slot--third'
  return 'registration-form-slot registration-form-slot--full'
}

function EventLogoMedia({
  mainImage,
  images,
  className,
}: {
  mainImage?: string | null
  images?: string[]
  className: string
}) {
  const gallery = images ?? []
  const initialMain = mainImage || gallery[0] || null
  const extraImages = mainImage ? gallery : gallery.slice(1)
  const thumbnails = initialMain && extraImages.length > 0
    ? [initialMain, ...extraImages.filter((url) => url !== initialMain)]
    : []

  const [activeUrl, setActiveUrl] = useState(initialMain)

  if (!initialMain) {
    return null
  }

  return (
    <div className={`${className} registration-event-display-block`}>
      <div className="registration-event-logo">
        <img
          src={activeUrl || initialMain}
          alt=""
          className="registration-event-logo-main"
        />
        {thumbnails.length > 0 ? (
          <div className="registration-event-logo-gallery" role="list">
            {thumbnails.map((url) => {
              const isActive = (activeUrl || initialMain) === url
              return (
                <button
                  key={url}
                  type="button"
                  role="listitem"
                  className={`registration-event-logo-gallery-thumb${isActive ? ' is-active' : ''}`}
                  aria-pressed={isActive}
                  onClick={() => setActiveUrl(url)}
                >
                  <img
                    src={url}
                    alt=""
                    className="registration-event-logo-gallery-image"
                    loading="lazy"
                  />
                </button>
              )
            })}
          </div>
        ) : null}
      </div>
    </div>
  )
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
  requiresVenueSelection = false,
  ticketTypes = [],
  requiresTicketSelection = false,
  theme,
  inviteCode = null,
  lockedEmail = null,
  prefillName = null,
}: Props) {
  const { t } = useLocale()
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const [isDark, setIsDark] = useState(() => isDocumentDark())
  const normalizedTheme = useMemo(() => normalizeRegistrationTheme(theme), [theme])

  useEffect(() => {
    const syncDark = () => setIsDark(isDocumentDark())
    syncDark()

    const observer = new MutationObserver(syncDark)
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

    const media = window.matchMedia('(prefers-color-scheme: dark)')
    const onMedia = () => syncDark()
    media.addEventListener?.('change', onMedia)

    return () => {
      observer.disconnect()
      media.removeEventListener?.('change', onMedia)
    }
  }, [])

  const themeVars = useMemo(
    () => registrationThemeCssVars(normalizedTheme, { isDark, locale }),
    [normalizedTheme, isDark, locale],
  )
  const cardBackgroundStyle = useMemo(() => {
    const background = registrationCardBackgroundStyle(normalizedTheme, { isDark })
    const fontFamily = resolveRegistrationFontFamily(normalizedTheme, locale)
    if (!background && !fontFamily) return undefined
    return {
      ...(background ?? {}),
      ...(fontFamily ? { fontFamily } : {}),
    }
  }, [normalizedTheme, isDark, locale])
  const hasCustomCardBackground = hasRegistrationCardBackground(normalizedTheme, { isDark })
  const registrationFields = useMemo(
    () => form.fields.filter((field) => field.type !== 'consent' && field.type !== 'hidden'),
    [form.fields],
  )
  const hasCategoriesBlock = useMemo(
    () => registrationFields.some((field) => field.type === 'event_categories'),
    [registrationFields],
  )
  const hasVenueSelectBlock = useMemo(
    () => registrationFields.some((field) => field.type === 'event_venue_select'),
    [registrationFields],
  )
  const venues = event.venues ?? []
  const requireCategory = !inviteCode && (requiresCategorySelection || hasCategoriesBlock)
  // Location - Date is always required whenever the event has venues.
  const requireVenue = venues.length > 0 || requiresVenueSelection || hasVenueSelectBlock
  const actualInputFields = useMemo(
    () => registrationFields.filter((field) => !NON_ANSWER_TYPES.has(field.type)),
    [registrationFields],
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

  const canSubmit = requireCategory
    ? availableCategories.length > 0
    : !requiresTicketSelection || ticketTypes.length > 0

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
    actualInputFields.forEach((field) => {
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

    const clientErrors = collectPublicRegistrationClientErrors(actualInputFields, answers, {
      ticketTypeId,
      categoryId,
      requireCategory,
      requireTicket: requiresTicketSelection,
      venueRequired: requireVenue && venues.length > 0,
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
          event_category_id: requireCategory ? Number(categoryId) : undefined,
          ticket_type_id: requiresTicketSelection ? String(ticketTypeId) : undefined,
          event_venue_id: requireVenue && venues.length > 0 ? String(venueId) : null,
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
      <main
        className={`registration-invite${isPreview ? ' registration-invite-preview' : ''}`}
        lang={locale}
        dir={direction}
        style={themeVars}
      >
        <RegistrationEventHero
          locale={locale}
          event={event}
          isPreview={isPreview}
          cardStyle={cardBackgroundStyle}
          hasCustomBackground={hasCustomCardBackground}
        >
          <form
            ref={formRef}
            noValidate
            className="registration-invite-form form-saving-scope-root"
            aria-label={t('publicRegistrationFormAria')}
            onSubmit={handleSubmit}
          >
            <FormSavingOverlay active={submitting} target={formTarget} label={t('publicRegistrationRegistering')} />

            {registrationFields.map((field) => {
              const isLockedEmail = Boolean(lockedEmail) && (field.type === 'email' || field.key === 'email')
              const isPrefillName = Boolean(prefillName) && (
                field.key === 'full_name'
                || field.type === 'full_name'
                || field.key === 'name'
              )
              const slotClass = fieldSlotClass(field.width)

              if (field.type === 'heading') {
                return (
                  <div key={field.key} className={`${slotClass} registration-event-display-block`}>
                    <h2 className="text-lg font-semibold text-[var(--ink)]">
                      {field.content || (locale === 'ar' ? field.label_ar : field.label_en)}
                    </h2>
                  </div>
                )
              }

              if (field.type === 'divider') {
                return <hr key={field.key} className={`${slotClass} registration-event-divider border-[var(--border)]`} />
              }

              if (field.type === 'paragraph') {
                return (
                  <p key={field.key} className={`${slotClass} registration-event-display-block text-sm text-[var(--muted)] whitespace-pre-wrap`}>
                    {field.content || (locale === 'ar' ? field.label_ar : field.label_en)}
                  </p>
                )
              }

              if (field.type === 'event_logo') {
                return (
                  <EventLogoMedia
                    key={field.key}
                    mainImage={event.main_image}
                    images={event.images}
                    className={slotClass}
                  />
                )
              }

              if (field.type === 'event_name') {
                return (
                  <div key={field.key} className={`${slotClass} registration-event-display-block`}>
                    <h1 className="text-2xl font-semibold text-[var(--ink)]">
                      <LocalizedEventContent value={event.name} locale={locale} />
                    </h1>
                  </div>
                )
              }

              if (field.type === 'event_venue') {
                return venues.length > 0 ? (
                  <div key={field.key} className={`${slotClass} registration-event-display-block`}>
                    <p className="text-sm text-[var(--muted)]">
                      {venues.map((v) => (locale === 'ar' ? (v.name.ar || v.name.en) : (v.name.en || v.name.ar))).join(', ')}
                    </p>
                  </div>
                ) : null
              }

              if (field.type === 'event_dates') {
                const startDate = event.start_at
                  ? new Date(event.start_at).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US')
                  : null
                const endDate = event.end_at
                  ? new Date(event.end_at).toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US')
                  : null
                return (startDate || endDate) ? (
                  <div key={field.key} className={`${slotClass} registration-event-display-block`}>
                    <p className="text-sm text-[var(--muted)]">
                      {startDate && endDate ? `${startDate} - ${endDate}` : startDate || endDate}
                    </p>
                  </div>
                ) : null
              }

              if (field.type === 'event_description') {
                const hasDescription = Boolean(event.description?.en || event.description?.ar)
                return hasDescription ? (
                  <div key={field.key} className={`${slotClass} registration-event-display-block`}>
                    <p className="text-sm text-[var(--muted)] whitespace-pre-wrap">
                      <LocalizedEventContent value={event.description} locale={locale} />
                    </p>
                  </div>
                ) : null
              }

              if (field.type === 'event_categories') {
                if (inviteCode) {
                  return null
                }

                if (categories.length === 0) {
                  return (
                    <p key={field.key} className={`${slotClass} registration-invite-warning`}>
                      {t('publicRegistrationNoCategories')}
                    </p>
                  )
                }

                return (
                  <section
                    key={field.key}
                    className={`${slotClass} registration-ticket-picker${validation.fieldError('event_category_id') ? ' form-field-invalid' : ''}`}
                    aria-label={t('publicRegistrationCategorySelection')}
                    data-form-field="event_category_id"
                  >
                    <h2>{locale === 'ar' ? field.label_ar : field.label_en}</h2>
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
                                validation.clearField('event_category_id')
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
                )
              }

              if (field.type === 'event_venue_select') {
                if (venues.length === 0) {
                  return null
                }

                return (
                  <div key={field.key} className={slotClass}>
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
                  </div>
                )
              }

              return (
                <div key={field.key} className={slotClass}>
                  <RegistrationField
                    field={field}
                    locale={locale}
                    disabled={isPreview && field.type !== 'checkbox' && field.type !== 'radio'}
                    readOnly={isLockedEmail}
                    value={isLockedEmail ? (lockedEmail ?? '') : undefined}
                    defaultValue={isPrefillName ? (prefillName ?? undefined) : undefined}
                    error={validation.fieldError(field.key)}
                    data-form-field={field.key}
                  />
                </div>
              )
            })}

            {!hasVenueSelectBlock && venues.length > 0 ? (
              <div className="registration-form-slot registration-form-slot--full">
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
              </div>
            ) : null}

            <div className="registration-form-slot registration-form-slot--full flex flex-col gap-4">
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
            </div>
          </form>
        </RegistrationEventHero>
        <ValidationHintPopover {...validation.hintProps} />
      </main>
    </>
  )
}
