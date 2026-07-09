import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { GateEventRow } from '@/components/gate-events/GateEventRow'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { ACS_HEALTH_POLL_INTERVAL_MS } from '@/lib/acs-polling'
import type { AccessEvent } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  accessEvents: AccessEvent[]
}

export default function AcsAccessLogs({ event, tenantId, accessEvents: initialEvents }: Props) {
  const { locale } = useLocale()
  const [events, setEvents] = useState(initialEvents)

  useEffect(() => {
    let active = true

    async function poll() {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/gate-events?limit=50`, {
        credentials: 'include',
        headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
      })
      const body = await response.json()
      if (active && response.ok) {
        setEvents(body.data ?? [])
      }
    }

    const timer = window.setInterval(() => void poll(), ACS_HEALTH_POLL_INTERVAL_MS)
    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [event.id, tenantId])

  return (
    <DashboardLayout title={locale === 'ar' ? 'سجلات الوصول' : 'Access logs'}>
      <PageHeader
        title={locale === 'ar' ? 'سجلات الوصول' : 'Access logs'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: locale === 'ar' ? 'سجلات الوصول' : 'Access logs' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>{locale === 'ar' ? 'صحة البوابة' : 'Gate health'}</LocalizedLink>}
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState title={locale === 'ar' ? 'لا توجد أحداث وصول' : 'No access events yet'} />
        ) : (
          <div className="overflow-x-auto">
            <table className="min-w-full text-sm">
              <thead>
                <tr>
                  <th>{locale === 'ar' ? 'الوقت' : 'Occurred'}</th>
                  <th>{locale === 'ar' ? 'النوع' : 'Type'}</th>
                  <th>{locale === 'ar' ? 'الاتجاه' : 'Direction'}</th>
                  <th>{locale === 'ar' ? 'القرار' : 'Decision'}</th>
                  <th>{locale === 'ar' ? 'السبب' : 'Reason'}</th>
                </tr>
              </thead>
              <tbody>
                {events.map((accessEvent) => (
                  <GateEventRow key={accessEvent.id} event={accessEvent} />
                ))}
              </tbody>
            </table>
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
