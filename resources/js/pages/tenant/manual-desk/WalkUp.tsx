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
    <DashboardLayout title={t('manualDeskPageWalkUp')}>
      <PageHeader
        title={t('manualDeskPageWalkUp')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('manualDeskPageTitle'), href: `/tenant/events/${event.id}/manual-desk` },
          { label: t('manualDeskPageWalkUp') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/manual-desk`}>
            {t('manualDeskPageBackToDesk')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        {ticketTypes.length === 0 ? (
          <EmptyState
            title={t('manualDeskPageNoTicketTypes')}
            detail={t('manualDeskPageNoTicketTypesDescription')}
          />
        ) : registeredId ? (
          <div className="ta-card space-y-4">
            <div className="flex flex-wrap items-center gap-3">
              <StatusBadge status="active" label={t('manualDeskPageRegistered')} />
              <p className="text-sm text-[var(--muted)]">{t('manualDeskPageAttendeeId')}</p>
            </div>
            <p className="font-mono text-sm text-[var(--ink)]">{registeredId}</p>
            <div className="flex flex-wrap gap-3 border-t border-[var(--border)] pt-4">
              <button type="button" className="button-primary" onClick={() => setRegisteredId(null)}>
                {t('manualDeskPageRegisterAnother')}
              </button>
              <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/attendees/${registeredId}`}>
                {t('manualDeskPageViewAttendee')}
              </LocalizedLink>
            </div>
          </div>
        ) : (
          <div className="space-y-4">
            <div className="ta-card">
              <SelectInput
                label={t('manualDeskPageTicketType')}
                name="ticket_type_id"
                value={ticketTypeId}
                onChange={(changeEvent) => setTicketTypeId(changeEvent.target.value)}
                options={ticketTypes.map((ticket) => ({
                  value: ticket.id,
                  label: ticket.name[locale] ?? ticket.code,
                }))}
              />
            </div>
            <WalkUpFormPanel
              eventId={event.id}
              tenantId={tenantId}
              ticketTypeId={ticketTypeId}
              onSuccess={setRegisteredId}
              onCancel={() => undefined}
            />
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
