import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import BadgeTemplateDesigner from '@/components/badges/BadgeTemplateDesigner'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import type { BadgeTemplate } from '@/types/phase3'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
  templates: BadgeTemplate[]
}

export default function BadgeTemplatesPage({ event, tenantId, templates }: Props) {
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

  return (
    <DashboardLayout title={t('badgeTemplates')}>
      <PageHeader
        title={t('badgeTemplates')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('badgeTemplates') },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/badge-print-jobs`}>{t('badgePrintJobs')}</LocalizedLink>}
      />
      <PageContent>
        <div className="space-y-4">
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
          template={activeTemplate}
          onSaved={handleSaved}
        />
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
