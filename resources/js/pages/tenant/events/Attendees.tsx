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
  display_name?: string | null
  email?: string | null
  phone?: string | null
  status: string
  locale: string
  credential_status?: string | null
}

type Props = {
  event: EventRow
  attendees: AttendeeRow[]
}

function displayValue(value: string | null | undefined, fallback: string): string {
  return value?.trim() ? value.trim() : fallback
}

export default function Attendees({ event, attendees }: Props) {
  const { locale, t } = useLocale()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')
  const notAvailable = t('notAvailable')

  const filtered = useMemo(() => attendees.filter((attendee) => {
    const needle = search.trim().toLowerCase()
    const matchesSearch = needle === ''
      || attendee.id.toLowerCase().includes(needle)
      || (attendee.display_name ?? '').toLowerCase().includes(needle)
      || (attendee.email ?? '').toLowerCase().includes(needle)
      || (attendee.phone ?? '').toLowerCase().includes(needle)
      || attendee.label.toLowerCase().includes(needle)
    const matchesStatus = statusFilter === '' || attendee.status === statusFilter

    return matchesSearch && matchesStatus
  }), [attendees, search, statusFilter])

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    ...Array.from(new Set(attendees.map((attendee) => attendee.status))).map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={t('attendees')}>
      <PageHeader
        title={t('attendees')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('attendees') },
        ]}
      />
      <PageContent>
        <FiltersBar>
          <SearchInput
            value={search}
            onChange={setSearch}
            label={locale === 'ar' ? 'بحث' : 'Search'}
            placeholder={t('searchAttendee')}
          />
          <SelectInput
            label={t('checkInStatus')}
            name="status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            options={statusOptions}
          />
        </FiltersBar>

        {filtered.length === 0 ? (
          <EmptyState
            title={t('noAttendees')}
            detail={t('noAttendeesDetail')}
          />
        ) : (
          <DataTable
            rows={filtered as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'display_name',
                header: t('attendeeName'),
                render: (row) => {
                  const attendee = row as unknown as AttendeeRow
                  const name = displayValue(attendee.display_name, notAvailable)

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/attendees/${attendee.id}`} className="font-medium text-sky-700 hover:underline">
                      {name}
                    </LocalizedLink>
                  )
                },
              },
              {
                key: 'email',
                header: t('attendeeEmail'),
                render: (row) => displayValue((row as unknown as AttendeeRow).email, notAvailable),
              },
              {
                key: 'phone',
                header: t('attendeePhone'),
                render: (row) => displayValue((row as unknown as AttendeeRow).phone, notAvailable),
              },
              {
                key: 'status',
                header: t('checkIn'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
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
