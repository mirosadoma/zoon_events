import { Link } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialDetail = {
  id: string
  code: string
  attendee_id: string
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  superseded_by_id?: string | null
}

type Props = {
  event: EventRow
  credential: CredentialDetail
}

export default function CredentialDetailPage({ event, credential }: Props) {
  const { locale } = useLocale()
  const [localStatus, setLocalStatus] = useState(credential.status)

  return (
    <DashboardLayout title={credential.code}>
      <PageHeader
        title={credential.code}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'بيانات الدخول' : 'Credentials', href: `/tenant/events/${event.id}/credentials` },
          { label: credential.code },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'تفاصيل بيانات الدخول' : 'Credential details'}
          items={[
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={localStatus} /> },
            {
              label: locale === 'ar' ? 'الحاضر' : 'Attendee',
              value: (
                <Link href={`/tenant/events/${event.id}/attendees/${credential.attendee_id}`} className="text-sky-700 hover:underline">
                  {credential.attendee_id.slice(-8)}
                </Link>
              ),
            },
            { label: locale === 'ar' ? 'تاريخ الإصدار' : 'Issued', value: credential.issued_at ?? '—' },
            { label: locale === 'ar' ? 'تاريخ الانتهاء' : 'Expires', value: credential.expires_at ?? '—' },
            { label: locale === 'ar' ? 'تاريخ الإلغاء' : 'Revoked', value: credential.revoked_at ?? '—' },
            { label: locale === 'ar' ? 'سبب الإلغاء' : 'Revoke reason', value: credential.revocation_reason ?? '—' },
          ]}
        />

        <CredentialDialog
          status={localStatus}
          onRevoked={() => setLocalStatus('revoked')}
          onReissued={() => setLocalStatus('active')}
        />
      </PageContent>
    </DashboardLayout>
  )
}
