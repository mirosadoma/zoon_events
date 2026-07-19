import { ArrowLeft, CheckCircle2, CreditCard, Shield, Sparkles } from 'lucide-react'
import { FormEvent, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'

type Plan = {
  id: string
  name: string
  name_ar: string | null
  description: string | null
  description_ar: string | null
  is_trial: boolean
  duration_days: number
  price: string
  currency: string
  max_events: number | null
  max_attendees: number | null
  max_devices: number | null
}

type Props = {
  plan: Plan
}

export default function Subscribe({ plan }: Props) {
  const { locale, direction, t } = useLocale()
  const isFree = Number(plan.price) === 0
  const planName = locale === 'ar' ? plan.name_ar || plan.name : plan.name
  const planDesc = locale === 'ar' ? plan.description_ar || plan.description : plan.description

  const [form, setForm] = useState({
    name: '',
    email: '',
    phone: '',
    organization_name: '',
    password: '',
    password_confirmation: '',
    card_number: '',
    card_expiry: '',
    card_cvv: '',
  })
  const [submitting, setSubmitting] = useState(false)
  const [success, setSuccess] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})

  const handleChange = (field: string) => (e: React.ChangeEvent<HTMLInputElement>) => {
    setForm((prev) => ({ ...prev, [field]: e.target.value }))
    setErrors((prev) => {
      const next = { ...prev }
      delete next[field]
      return next
    })
  }

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault()
    setSubmitting(true)
    setErrors({})

    try {
      const paymentReference = isFree ? null : `FAKE-${Date.now()}`
      const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? ''

      const res = await fetch('/api/v1/subscribe', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          name: form.name,
          email: form.email,
          phone: form.phone || null,
          organization_name: form.organization_name,
          password: form.password,
          password_confirmation: form.password_confirmation,
          plan_id: plan.id,
          payment_reference: paymentReference,
          payment_method: isFree ? 'free' : 'credit_card',
          locale,
        }),
      })

      const data = await res.json()

      if (!res.ok) {
        if (data.errors) {
          setErrors(data.errors)
        } else if (data.message) {
          setErrors({ _general: [data.message] })
        }
        return
      }

      setSuccess(true)
    } catch {
      setErrors({ _general: [t('subscribePageErrorGeneral')] })
    } finally {
      setSubmitting(false)
    }
  }

  if (success) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-[var(--surface)] p-4" dir={direction}>
        <div className="w-full max-w-lg text-center">
          <div className="ta-card p-10">
            <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/30">
              <CheckCircle2 className="h-10 w-10 text-emerald-600 dark:text-emerald-400" />
            </div>
            <h2 className="mt-6 text-2xl font-bold text-[var(--ink)]">{t('subscribePageSuccessTitle')}</h2>
            <p className="mx-auto mt-3 max-w-sm text-[var(--muted)]">{t('subscribePageSuccessMessage')}</p>
            <a href={localizedPath(locale, '/login')} className="button-primary mt-8 inline-flex items-center gap-2">
              {t('subscribePageGoToLogin')}
            </a>
          </div>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-[var(--surface)]" dir={direction}>
      {/* Header */}
      <header className="border-b border-[var(--border)] bg-[var(--surface-elevated)]">
        <div className="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
          <a
            href={localizedPath(locale, '/')}
            className="inline-flex items-center gap-2 text-sm font-medium text-[var(--muted)] transition-colors hover:text-[var(--ink)]"
          >
            <ArrowLeft className="h-4 w-4" />
            {t('subscribePageBack')}
          </a>
          <div className="flex items-center gap-2 text-xs text-[var(--muted)]">
            <Shield className="h-3.5 w-3.5" />
            {t('subscribePageSecurePayment')}
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-5xl px-6 py-10">
        {/* Title */}
        <div className="mb-8 text-center">
          <h1 className="text-3xl font-bold text-[var(--ink)]">{t('subscribePageTitle')}</h1>
          <p className="mt-2 text-[var(--muted)]">{t('subscribePageSubtitle')}</p>
        </div>

        <div className="grid gap-8 lg:grid-cols-[1fr_340px]">
          {/* Form */}
          <form onSubmit={handleSubmit} className="space-y-6">
            {errors._general && (
              <div className="rounded-[var(--radius-control)] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {errors._general.map((err, i) => <p key={i}>{err}</p>)}
              </div>
            )}

            {/* Account Info */}
            <fieldset className="ta-card space-y-5 p-6">
              <legend className="flex items-center gap-2 text-base font-semibold text-[var(--ink)]">
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-soft)] text-xs font-bold text-[var(--brand)]">1</span>
                {t('subscribePageAccountInfo')}
              </legend>

              <div className="grid gap-5 sm:grid-cols-2">
                <InputField label={t('subscribePageNameLabel')} required>
                  <input type="text" value={form.name} onChange={handleChange('name')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" required />
                  {errors.name && <FieldError msg={errors.name[0]} />}
                </InputField>
                <InputField label={t('subscribePageEmailLabel')} required>
                  <input type="email" value={form.email} onChange={handleChange('email')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" required />
                  {errors.email && <FieldError msg={errors.email[0]} />}
                </InputField>
              </div>

              <div className="grid gap-5 sm:grid-cols-2">
                <InputField label={t('subscribePagePasswordLabel')} required>
                  <input type="password" value={form.password} onChange={handleChange('password')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" required minLength={8} />
                  {errors.password && <FieldError msg={errors.password[0]} />}
                </InputField>
                <InputField label={t('subscribePageConfirmPasswordLabel')} required>
                  <input type="password" value={form.password_confirmation} onChange={handleChange('password_confirmation')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" required minLength={8} />
                </InputField>
              </div>
            </fieldset>

            {/* Organization */}
            <fieldset className="ta-card space-y-5 p-6">
              <legend className="flex items-center gap-2 text-base font-semibold text-[var(--ink)]">
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-soft)] text-xs font-bold text-[var(--brand)]">2</span>
                {t('subscribePageOrgInfo')}
              </legend>

              <div className="grid gap-5 sm:grid-cols-2">
                <InputField label={t('subscribePageOrgLabel')} required>
                  <input type="text" value={form.organization_name} onChange={handleChange('organization_name')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" required />
                  {errors.organization_name && <FieldError msg={errors.organization_name[0]} />}
                </InputField>
                <InputField label={t('subscribePagePhoneLabel')}>
                  <input type="tel" value={form.phone} onChange={handleChange('phone')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" />
                  {errors.phone && <FieldError msg={errors.phone[0]} />}
                </InputField>
              </div>
            </fieldset>

            {/* Payment */}
            {!isFree && (
              <fieldset className="ta-card space-y-5 p-6">
                <legend className="flex items-center gap-2 text-base font-semibold text-[var(--ink)]">
                  <span className="flex h-7 w-7 items-center justify-center rounded-full bg-[var(--brand-soft)] text-xs font-bold text-[var(--brand)]">3</span>
                  <CreditCard className="h-4 w-4 text-[var(--brand)]" />
                  {t('subscribePagePaymentTitle')}
                </legend>
                <p className="text-xs text-[var(--muted)]">{t('subscribePagePaymentSubtitle')}</p>

                <InputField label={t('subscribePageCardNumberLabel')} required>
                  <input type="text" value={form.card_number} onChange={handleChange('card_number')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" placeholder="4242 4242 4242 4242" required />
                </InputField>

                <div className="grid grid-cols-2 gap-4">
                  <InputField label={t('subscribePageExpiryLabel')} required>
                    <input type="text" value={form.card_expiry} onChange={handleChange('card_expiry')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" placeholder="MM/YY" required />
                  </InputField>
                  <InputField label={t('subscribePageCvvLabel')} required>
                    <input type="text" value={form.card_cvv} onChange={handleChange('card_cvv')} className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20" placeholder="123" required />
                  </InputField>
                </div>
              </fieldset>
            )}

            <button type="submit" disabled={submitting} className="button-primary w-full py-3 text-base">
              {submitting ? t('subscribePageSubmitting') : t('subscribePageSubmitBtn')}
            </button>
          </form>

          {/* Sidebar — Plan Summary */}
          <aside className="lg:sticky lg:top-6 lg:self-start">
            <div className="ta-card overflow-hidden p-0">
              {/* Plan Header */}
              <div className="relative overflow-hidden bg-gradient-to-br from-[var(--brand-soft)] to-[var(--surface-elevated)] px-6 py-5">
                <div className="relative z-10">
                  <div className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-[var(--brand)]" />
                    <span className="text-xs font-semibold uppercase tracking-wide text-[var(--brand)]">{t('subscribePagePlanLabel')}</span>
                  </div>
                  <h3 className="mt-2 text-xl font-bold text-[var(--ink)]">{planName}</h3>
                  {planDesc && <p className="mt-1 text-sm text-[var(--muted)]">{planDesc}</p>}
                </div>
              </div>

              {/* Price */}
              <div className="border-b border-[var(--border)] px-6 py-4">
                <p className="text-3xl font-bold text-[var(--brand)]">
                  {isFree ? t('subscribePageFree') : `${plan.price} ${plan.currency}`}
                </p>
                {!isFree && <p className="mt-0.5 text-xs text-[var(--muted)]">/ {plan.duration_days} {t('subscribePageDays')}</p>}
              </div>

              {/* Features */}
              <div className="space-y-3 px-6 py-5">
                <PlanFeature label={t('subscribePageEvents')} value={plan.max_events != null ? String(plan.max_events) : t('subscribePageUnlimited')} />
                <PlanFeature label={t('subscribePageAttendees')} value={plan.max_attendees != null ? String(plan.max_attendees) : t('subscribePageUnlimited')} />
                <PlanFeature label={t('subscribePageDevices')} value={plan.max_devices != null ? String(plan.max_devices) : t('subscribePageUnlimited')} />
                <PlanFeature label={t('subscribePageDuration')} value={`${plan.duration_days} ${t('subscribePageDays')}`} />
              </div>
            </div>
          </aside>
        </div>
      </div>
    </div>
  )
}

function InputField({ label, required, children }: { label: string; required?: boolean; children: React.ReactNode }) {
  return (
    <label className="grid gap-1.5 text-sm">
      <span className="font-medium text-[var(--ink)]">
        {label}
        {required && <span className="ms-0.5 text-red-500">*</span>}
      </span>
      {children}
    </label>
  )
}

function FieldError({ msg }: { msg: string }) {
  return <span className="text-xs text-red-600 dark:text-red-400">{msg}</span>
}

function PlanFeature({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between text-sm">
      <span className="text-[var(--muted)]">{label}</span>
      <span className="font-medium text-[var(--ink)]">{value}</span>
    </div>
  )
}
