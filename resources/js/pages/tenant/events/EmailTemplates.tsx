import LocalizedLink from '@/components/routing/LocalizedLink'
import { Mail, ChevronRight } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import SetupCompleteMark from '@/components/events/SetupCompleteMark'

type EmailTemplate = {
  id: string
  type: string
  subject_en: string
  subject_ar: string
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
    status?: string
  }
  templates: EmailTemplate[]
  configuredCount?: number
  requiredCount?: number
}

const TEMPLATE_TYPES = [
  { type: 'invitation', label_en: 'Invitation Email', label_ar: 'بريد الدعوة' },
  { type: 'otp', label_en: 'OTP Verification', label_ar: 'رمز التحقق' },
  { type: 'confirmation', label_en: 'Confirmation Email', label_ar: 'بريد التأكيد' },
]

export default function EmailTemplates({
  event,
  templates,
  configuredCount,
  requiredCount = TEMPLATE_TYPES.length,
}: Props) {
  const { locale, t } = useLocale()

  const getTemplate = (type: string) => templates.find((template) => template.type === type)
  const done = configuredCount ?? templates.filter((template) =>
    TEMPLATE_TYPES.some((typeConfig) => typeConfig.type === template.type),
  ).length
  const remaining = Math.max(requiredCount - done, 0)
  const complete = remaining === 0
  const isPrePublish = !event.status || event.status === 'draft' || event.status === 'configured'

  return (
    <DashboardLayout title={t('emailTemplates')}>
      <PageHeader
        title={t('emailTemplates')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: locale === 'ar' ? (event.name.ar || event.name.en) : (event.name.en || event.name.ar), href: `/tenant/events/${event.id}` },
          { label: t('emailTemplates') },
        ]}
      />

      <div className="space-y-4">
        <p className="text-sm text-[var(--muted)]">
          {t('emailTemplatesDescription')}
        </p>

        {isPrePublish ? (
          <div className={`rounded-xl border p-4 ${complete ? 'border-[var(--success)]/30 bg-[var(--success)]/5' : 'border-[var(--warning)]/40 bg-[var(--warning)]/5'}`}>
            <div className="flex flex-wrap items-center gap-2">
              <p className="text-sm font-medium text-[var(--ink)]">
                {t('emailTemplatesProgress', { done, total: requiredCount })}
              </p>
              <SetupCompleteMark completed={complete} />
            </div>
            <p className="mt-1 text-sm text-[var(--muted)]">
              {complete
                ? t('emailTemplatesComplete')
                : t('emailTemplatesRemaining', { remaining })}
            </p>
          </div>
        ) : null}

        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {TEMPLATE_TYPES.map((typeConfig) => {
            const template = getTemplate(typeConfig.type)
            const label = locale === 'ar' ? typeConfig.label_ar : typeConfig.label_en

            return (
              <LocalizedLink
                key={typeConfig.type}
                href={`/tenant/events/${event.id}/email-templates/${typeConfig.type}`}
                className={`block rounded-xl border bg-[var(--surface-elevated)] p-5 transition hover:border-[var(--brand)]/30 hover:shadow-sm ${
                  template ? 'border-[var(--border)]' : 'border-[var(--warning)]/40'
                }`}
              >
                <div className="flex items-start justify-between">
                  <div className="flex items-center gap-3">
                    <div className="rounded-lg bg-[var(--brand-soft)] p-2.5">
                      <Mail size={20} className="text-[var(--brand)]" />
                    </div>
                    <div>
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-[var(--ink)]">{label}</h3>
                        <SetupCompleteMark completed={Boolean(template)} />
                      </div>
                      {template ? (
                        <p className="mt-0.5 text-xs text-[var(--success)]">
                          {t('emailTemplatesCustomized')}
                        </p>
                      ) : (
                        <p className="mt-0.5 text-xs text-[var(--warning)]">
                          {t('emailTemplatesRequired')}
                        </p>
                      )}
                    </div>
                  </div>
                  <ChevronRight size={16} className="text-[var(--muted)] mt-1" />
                </div>
              </LocalizedLink>
            )
          })}
        </div>

        <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
          <h4 className="mb-3 text-sm font-semibold text-[var(--ink)]">{t('emailTemplatesAvailablePlaceholders')}</h4>
          <div className="space-y-3 text-sm">
            {[
              { type: 'invitation', keys: ['{{name}}', '{{event}}', '{{email}}', '{{registration_url}}'] },
              { type: 'otp', keys: ['{{otp}}'] },
              { type: 'confirmation', keys: ['{{name}}', '{{event}}', '{{email}}', '{{phone}}', '{{qr}}', '{{entry_card_url}}'] },
            ].map((row) => (
              <div key={row.type}>
                <p className="mb-1.5 text-xs font-medium text-[var(--muted)]">
                  {locale === 'ar'
                    ? (row.type === 'invitation' ? 'دعوة' : row.type === 'otp' ? 'رمز التحقق' : 'تأكيد')
                    : (row.type === 'invitation' ? 'Invitation' : row.type === 'otp' ? 'OTP' : 'Confirmation')}
                </p>
                <div className="flex flex-wrap gap-2">
                  {row.keys.map((placeholder) => (
                    <code key={placeholder} className="rounded bg-[var(--surface)] px-2 py-1 font-mono text-xs text-[var(--brand)]">
                      {placeholder}
                    </code>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </DashboardLayout>
  )
}
