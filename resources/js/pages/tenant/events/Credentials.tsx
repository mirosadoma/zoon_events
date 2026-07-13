import LocalizedLink from '@/components/routing/LocalizedLink'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialRow = {
  id: string
  code: string
  attendee_id: string
  attendee_label?: string | null
  status: string
  issued_at?: string | null
  expires_at?: string | null
}

type Props = {
  event: EventRow
  credentials: CredentialRow[]
}

export default function Credentials({ event, credentials }: Props) {
  const { locale, t } = useLocale()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const filtered = useMemo(() => credentials.filter((credential) => {
    const matchesSearch = search.trim() === ''
      || credential.code.toLowerCase().includes(search.trim().toLowerCase())
      || credential.attendee_id.toLowerCase().includes(search.trim().toLowerCase())
      || (credential.attendee_label ?? '').toLowerCase().includes(search.trim().toLowerCase())
    const matchesStatus = statusFilter === '' || credential.status === statusFilter

    return matchesSearch && matchesStatus
  }), [credentials, search, statusFilter])

  const statusOptions = [
    { value: '', label: locale === 'ar' ? 'كل الحالات' : 'All statuses' },
    ...Array.from(new Set(credentials.map((credential) => credential.status))).map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={locale === 'ar' ? 'بيانات الدخول' : 'Credentials'}>
      <PageHeader
        title={locale === 'ar' ? 'بيانات الدخول' : 'Credentials'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'بيانات الدخول' : 'Credentials' },
        ]}
      />
      <PageContent>
        <FiltersBar>
          <SearchInput
            value={search}
            onChange={setSearch}
            label={locale === 'ar' ? 'بحث' : 'Search'}
            placeholder={locale === 'ar' ? 'رمز أو حاضر' : 'Code or attendee'}
          />
          <SelectInput
            label={locale === 'ar' ? 'الحالة' : 'Status'}
            name="status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            options={statusOptions}
          />
        </FiltersBar>

        {filtered.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد بيانات دخول' : 'No credentials yet'}
            detail={locale === 'ar' ? 'ستظهر بيانات الدخول بعد الإصدار.' : 'Credentials will appear after issuance.'}
          />
        ) : (
          <DataTable
            rows={filtered as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'code',
                header: locale === 'ar' ? 'الرمز' : 'Code',
                render: (row) => {
                  const credential = row as unknown as CredentialRow

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/credentials/${credential.id}`} className="font-medium text-sky-700 hover:underline">
                      {credential.code}
                    </LocalizedLink>
                  )
                },
              },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'attendee_id',
                header: locale === 'ar' ? 'الحاضر' : 'Attendee',
                render: (row) => {
                  const credential = row as unknown as CredentialRow

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/attendees/${credential.attendee_id}`} className="text-sky-700 hover:underline">
                      {credential.attendee_label ?? credential.attendee_id}
                    </LocalizedLink>
                  )
                },
              },
              { key: 'issued_at', header: locale === 'ar' ? 'تاريخ الإصدار' : 'Issued' },
              { key: 'expires_at', header: locale === 'ar' ? 'تاريخ الانتهاء' : 'Expires' },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
