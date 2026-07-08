import { Link } from '@inertiajs/react'
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
  const { locale } = useLocale()
  const [activeTemplate, setActiveTemplate] = useState<BadgeTemplate | undefined>(templates[0])

  return (
    <DashboardLayout title={locale === 'ar' ? 'قوالب الشارات' : 'Badge templates'}>
      <PageHeader
        title={locale === 'ar' ? 'قوالب الشارات' : 'Badge templates'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'قوالب الشارات' : 'Badge templates' },
        ]}
        actions={<Link className="button-secondary" href={`/tenant/events/${event.id}/badge-print-jobs`}>{locale === 'ar' ? 'مهام الطباعة' : 'Print jobs'}</Link>}
      />
      <PageContent>
        {templates.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-2">
            {templates.map((template) => (
              <button
                key={template.id}
                type="button"
                className={activeTemplate?.id === template.id ? 'button-primary' : 'button-secondary'}
                onClick={() => setActiveTemplate(template)}
              >
                {template.name}
              </button>
            ))}
          </div>
        )}
        <BadgeTemplateDesigner
          eventId={event.id}
          tenantId={tenantId}
          template={activeTemplate}
          onSaved={setActiveTemplate}
        />
      </PageContent>
    </DashboardLayout>
  )
}
