import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { WalkUpFormPanel } from '@/components/manual-desk/WalkUpFormPanel'
import SelectInput from '@/components/forms/SelectInput'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type TicketTypeRow = {
  id: string
  code: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
  ticketTypes: TicketTypeRow[]
}

export default function WalkUpRegistration({ event, tenantId, ticketTypes }: Props) {
  const { locale, t } = useLocale()
  const [ticketTypeId, setTicketTypeId] = useState(ticketTypes[0]?.id ?? '')
  const [registeredId, setRegisteredId] = useState<string | null>(null)

  return (
    <DashboardLayout title={locale === 'ar' ? 'تسجيل مباشر' : 'Walk-up registration'}>
      <PageHeader
        title={locale === 'ar' ? 'تسجيل مباشر' : 'Walk-up registration'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'مكتب الاستقبال' : 'Manual desk', href: `/tenant/events/${event.id}/manual-desk` },
          { label: locale === 'ar' ? 'تسجيل مباشر' : 'Walk-up' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/manual-desk`}>{locale === 'ar' ? 'العودة للمكتب' : 'Back to desk'}</LocalizedLink>}
      />
      <PageContent>
        {ticketTypes.length === 0 ? (
          <EmptyState title={locale === 'ar' ? 'لا توجد أنواع تذاكر نشطة' : 'No active ticket types available'} />
        ) : (
          <>
            <SelectInput
              label={locale === 'ar' ? 'نوع التذكرة' : 'Ticket type'}
              name="ticket_type_id"
              value={ticketTypeId}
              onChange={(changeEvent) => setTicketTypeId(changeEvent.target.value)}
              options={ticketTypes.map((ticket) => ({
                value: ticket.id,
                label: ticket.name[locale] ?? ticket.code,
              }))}
            />
            {registeredId ? (
              <p className="mt-4 flex flex-wrap items-center gap-2">
                <StatusBadge status="active" label={locale === 'ar' ? 'تم التسجيل' : 'Registered'} />
                <span>{registeredId}</span>
              </p>
            ) : (
              <WalkUpFormPanel
                eventId={event.id}
                tenantId={tenantId}
                ticketTypeId={ticketTypeId}
                onSuccess={setRegisteredId}
                onCancel={() => undefined}
              />
            )}
          </>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
