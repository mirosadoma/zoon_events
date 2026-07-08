import { Link } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialSummary = {
  id: string
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
}

type AttendeeDetail = {
  id: string
  label: string
  status: string
  locale: string
  order_id?: string | null
  ticket_type_id?: string | null
  registered_at?: string | null
  first_checked_in_at?: string | null
  origin?: string | null
  credential?: CredentialSummary | null
}

type Props = {
  event: EventRow
  attendee: AttendeeDetail
}

export default function AttendeeDetailPage({ event, attendee }: Props) {
  const { locale } = useLocale()

  return (
    <DashboardLayout title={attendee.label}>
      <PageHeader
        title={attendee.label}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الحضور' : 'Attendees', href: `/tenant/events/${event.id}/attendees` },
          { label: attendee.label },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'ملف الحاضر' : 'Attendee profile'}
          items={[
            { label: locale === 'ar' ? 'تسجيل الحضور' : 'Check-in status', value: <StatusBadge status={attendee.status} /> },
            { label: locale === 'ar' ? 'اللغة' : 'Locale', value: attendee.locale },
            { label: locale === 'ar' ? 'المصدر' : 'Origin', value: attendee.origin ?? '—' },
            { label: locale === 'ar' ? 'تاريخ التسجيل' : 'Registered', value: attendee.registered_at ?? '—' },
            { label: locale === 'ar' ? 'أول تسجيل حضور' : 'First check-in', value: attendee.first_checked_in_at ?? '—' },
            {
              label: locale === 'ar' ? 'الطلب' : 'Order',
              value: attendee.order_id
                ? (
                  <Link href={`/tenant/events/${event.id}/orders/${attendee.order_id}`} className="text-sky-700 hover:underline">
                    {attendee.order_id.slice(-8)}
                  </Link>
                )
                : '—',
            },
          ]}
        />

        {attendee.credential && (
          <section className="state-panel mt-6">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'بيانات الدخول' : 'Credential'}</h2>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الحالة' : 'Status'}</dt>
                <dd className="mt-1"><StatusBadge status={attendee.credential.status} /></dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الرمز' : 'Credential'}</dt>
                <dd className="mt-1">
                  <Link href={`/tenant/events/${event.id}/credentials/${attendee.credential.id}`} className="text-sky-700 hover:underline">
                    {attendee.credential.id.slice(-8)}
                  </Link>
                </dd>
              </div>
            </dl>
          </section>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
