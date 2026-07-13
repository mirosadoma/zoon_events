import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import PublishReadinessBadge from '@/components/events/PublishReadinessBadge'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { DataTable } from '@/components/tables'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { requiresTicketing } from '@/lib/eventOptions'
import type { PublishReadinessContext } from '@/lib/publishReadinessCatalog'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  tier: string
  event_type?: string
  registration_mode?: string
  timezone: string
  start_at?: string | null
  capacity?: number | null
  registration_url?: string | null
  readiness?: string[]
}

type Props = {
  events: EventRow[]
}

function readinessContextFor(event: EventRow): PublishReadinessContext {
  return {
    status: event.status,
    requiresTicketing: requiresTicketing(event.tier, event.registration_mode ?? 'free_registration'),
  }
}

export default function EventList({ events }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()

  async function copyRegistrationLink(url: string) {
    try {
      await navigator.clipboard.writeText(url)
      toast(locale === 'ar' ? 'تم النسخ' : 'Copied', 'success')
    } catch {
      toast(locale === 'ar' ? 'تعذر نسخ الرابط.' : 'Could not copy the link.', 'error')
    }
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'الفعاليات' : 'Events'}>
      <PageHeader
        title={locale === 'ar' ? 'الفعاليات' : 'Events'}
        description={locale === 'ar' ? 'إدارة فعاليات المستأجر.' : 'Manage tenant events.'}
        breadcrumbs={[{ label: t('overview'), href: '/dashboard' }, { label: locale === 'ar' ? 'الفعاليات' : 'Events' }]}
        actions={
          <LocalizedLink className="button-primary" href="/tenant/events/create">
            {locale === 'ar' ? 'فعالية جديدة' : 'New event'}
          </LocalizedLink>
        }
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState title={locale === 'ar' ? 'لا توجد فعاليات' : 'No events yet'} detail={locale === 'ar' ? 'أنشئ أول فعالية للبدء.' : 'Create the first event to get started.'} />
        ) : (
          <DataTable
            rows={events as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'name',
                header: locale === 'ar' ? 'الاسم' : 'Name',
                render: (row) => {
                  const event = row as unknown as EventRow

                  return <LocalizedLink href={`/tenant/events/${event.id}`} className="font-medium text-sky-700 hover:underline">{event.name[locale]}</LocalizedLink>
                },
              },
              { key: 'tier', header: locale === 'ar' ? 'الفئة' : 'Tier' },
              { key: 'event_type', header: locale === 'ar' ? 'النوع' : 'Type' },
              { key: 'registration_mode', header: locale === 'ar' ? 'التسجيل' : 'Registration' },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'publish_readiness',
                header: locale === 'ar' ? 'جاهزية النشر' : 'Publish readiness',
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <PublishReadinessBadge
                      readiness={event.readiness ?? []}
                      context={readinessContextFor(event)}
                    />
                  )
                },
              },
              { key: 'timezone', header: locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone' },
              { key: 'capacity', header: locale === 'ar' ? 'السعة' : 'Capacity' },
              {
                key: 'actions',
                header: locale === 'ar' ? 'إجراءات' : 'Actions',
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <div className="ta-table-actions">
                      {event.registration_url ? (
                        <CopyRegistrationLinkButton
                          compact
                          onClick={() => void copyRegistrationLink(event.registration_url!)}
                        />
                      ) : null}
                      <LocalizedLink href={`/tenant/events/${event.id}`} className="ta-table-action">
                        {locale === 'ar' ? 'عرض' : 'View'}
                      </LocalizedLink>
                      <LocalizedLink href={`/tenant/events/${event.id}/edit`} className="ta-table-action">
                        {locale === 'ar' ? 'تعديل' : 'Edit'}
                      </LocalizedLink>
                    </div>
                  )
                },
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
