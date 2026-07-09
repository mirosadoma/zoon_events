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

type AttendeeRow = {
  id: string
  label: string
  status: string
  locale: string
  credential_status?: string | null
}

type Props = {
  event: EventRow
  attendees: AttendeeRow[]
}

export default function Attendees({ event, attendees }: Props) {
  const { locale } = useLocale()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const filtered = useMemo(() => attendees.filter((attendee) => {
    const matchesSearch = search.trim() === ''
      || attendee.id.toLowerCase().includes(search.trim().toLowerCase())
      || attendee.label.toLowerCase().includes(search.trim().toLowerCase())
    const matchesStatus = statusFilter === '' || attendee.status === statusFilter

    return matchesSearch && matchesStatus
  }), [attendees, search, statusFilter])

  const statusOptions = [
    { value: '', label: locale === 'ar' ? 'كل الحالات' : 'All statuses' },
    ...Array.from(new Set(attendees.map((attendee) => attendee.status))).map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={locale === 'ar' ? 'الحضور' : 'Attendees'}>
      <PageHeader
        title={locale === 'ar' ? 'الحضور' : 'Attendees'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الحضور' : 'Attendees' },
        ]}
      />
      <PageContent>
        <FiltersBar>
          <SearchInput
            value={search}
            onChange={setSearch}
            label={locale === 'ar' ? 'بحث' : 'Search'}
            placeholder={locale === 'ar' ? 'معرف الحاضر' : 'Attendee id'}
          />
          <SelectInput
            label={locale === 'ar' ? 'حالة الحضور' : 'Check-in status'}
            name="status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            options={statusOptions}
          />
        </FiltersBar>

        {filtered.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا يوجد حضور' : 'No attendees yet'}
            detail={locale === 'ar' ? 'سيظهر الحضور بعد التسجيل.' : 'Attendees will appear after registration.'}
          />
        ) : (
          <DataTable
            rows={filtered as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'label',
                header: locale === 'ar' ? 'الحاضر' : 'Attendee',
                render: (row) => {
                  const attendee = row as unknown as AttendeeRow

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/attendees/${attendee.id}`} className="font-medium text-sky-700 hover:underline">
                      {attendee.label}
                    </LocalizedLink>
                  )
                },
              },
              {
                key: 'status',
                header: locale === 'ar' ? 'تسجيل الحضور' : 'Check-in',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              { key: 'locale', header: locale === 'ar' ? 'اللغة' : 'Locale' },
              {
                key: 'credential_status',
                header: locale === 'ar' ? 'الاعتماد' : 'Credential',
                render: (row) => {
                  const status = row.credential_status as string | null | undefined

                  return status ? <StatusBadge status={status} /> : '—'
                },
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
