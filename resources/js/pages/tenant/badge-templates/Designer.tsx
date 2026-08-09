import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import BadgeTemplateDesigner from '@/components/badges/BadgeTemplateDesigner'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { formatDate } from '@/lib/formatters'
import type { BadgeTemplate } from '@/types/phase3'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  description?: { en: string; ar: string }
  timezone?: string | null
  start_at?: string | null
  end_at?: string | null
  main_image?: string | null
  logo_url?: string | null
  sponsor_logo_url?: string | null
  venues?: Array<{ id: string; name: { en: string; ar: string } }>
  tier?: string
}

type RegistrationField = {
  key: string
  label_en: string
  label_ar: string
  type: string
}

type Props = {
  event: EventRow
  tenantId: string
  templates: BadgeTemplate[]
  registrationFields?: RegistrationField[]
}

export default function BadgeTemplatesPage({ event, tenantId, templates, registrationFields }: Props) {
  const { locale, t } = useLocale()
  const [items, setItems] = useState<BadgeTemplate[]>(templates)
  const [activeTemplate, setActiveTemplate] = useState<BadgeTemplate | undefined>(templates[0])

  const handleSaved = (template: BadgeTemplate) => {
    setActiveTemplate(template)
    setItems((prev) => {
      const index = prev.findIndex((item) => String(item.id) === String(template.id))
      if (index >= 0) {
        const next = [...prev]
        next[index] = template
        return next
      }
      return [...prev, template]
    })
  }

  const eventName = event.name[locale] || event.name.en || event.name.ar
  const eventDescription = event.description
    ? (locale === 'ar' ? (event.description.ar || event.description.en) : (event.description.en || event.description.ar))
    : ''
  const venueLabels = (event.venues ?? [])
    .map((venue) => (locale === 'ar' ? (venue.name.ar || venue.name.en) : (venue.name.en || venue.name.ar)))
    .filter(Boolean)
  const dateLabel = [
    event.start_at ? formatDate(event.start_at, locale, event.timezone ?? undefined) : null,
    event.end_at ? formatDate(event.end_at, locale, event.timezone ?? undefined) : null,
  ].filter(Boolean).join(' — ')

  return (
    <DashboardLayout title={t('badgeTemplates')}>
      <PageHeader
        title={t('badgeTemplates')}
        description={eventName}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: eventName, href: `/tenant/events/${event.id}` },
          { label: t('badgeTemplates') },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/badge-print-jobs`}>{t('badgePrintJobs')}</LocalizedLink>}
      />
      <PageContent>
        <div className="space-y-4">
          <section className="ta-card overflow-hidden p-0">
            <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start">
              {event.main_image ? (
                <img
                  src={event.main_image}
                  alt=""
                  className="h-28 w-full shrink-0 rounded-xl object-cover sm:h-24 sm:w-40"
                />
              ) : (
                <div className="flex h-28 w-full shrink-0 items-center justify-center rounded-xl bg-[var(--surface)] text-xs text-[var(--muted)] sm:h-24 sm:w-40">
                  {t('badgeEventNoImage')}
                </div>
              )}
              <div className="min-w-0 flex-1 space-y-1.5">
                <p className="text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
                  {t('badgeEventDetails')}
                </p>
                <h2 className="text-lg font-semibold text-[var(--ink)]">{eventName}</h2>
                {eventDescription ? (
                  <p className="line-clamp-2 text-sm text-[var(--muted)]">{eventDescription}</p>
                ) : null}
                <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-[var(--muted)]">
                  {dateLabel ? <span>{dateLabel}</span> : null}
                  {venueLabels.length > 0 ? <span>{venueLabels.join(' · ')}</span> : null}
                  {event.tier ? <span className="uppercase">{event.tier}</span> : null}
                </div>
              </div>
            </div>
          </section>

          {items.length > 0 && (
            <div className="ta-card flex flex-wrap gap-2 p-3">
              {items.map((template) => (
                <button
                  key={template.id}
                  type="button"
                  className={activeTemplate?.id === template.id ? 'button-primary' : 'button-secondary'}
                  onClick={() => setActiveTemplate(template)}
                >
                  {template.name}
                  <span className="ms-2 text-xs opacity-80">
                    ({template.status === 'active' ? t('badgeTemplateStatusActive') : t('badgeTemplateStatusDraft')})
                  </span>
                </button>
              ))}
            </div>
          )}
          <BadgeTemplateDesigner
            key={activeTemplate?.id ?? 'new'}
            eventId={event.id}
            tenantId={tenantId}
            event={event}
            template={activeTemplate}
            registrationFields={registrationFields}
            onSaved={handleSaved}
          />
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
