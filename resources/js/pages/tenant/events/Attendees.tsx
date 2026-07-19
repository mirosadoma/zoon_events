import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'

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
  invite_status?: string
  row_type?: 'attendee' | 'invite'
  locale: string
  credential_status?: string | null
}

type Filters = {
  search: string
  status: string
}

type PaginationMeta = {
  page: number
  per_page: number
  total: number
  last_page: number
}

type Props = {
  event: EventRow
  attendees: AttendeeRow[]
  filters?: Filters
  pagination?: PaginationMeta
}

function displayValue(value: string | null | undefined, fallback: string): string {
  return value?.trim() ? value.trim() : fallback
}

export default function Attendees({
  event,
  attendees,
  filters = { search: '', status: '' },
  pagination = { page: 1, per_page: 25, total: 0, last_page: 1 },
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [search, setSearch] = useState(filters.search)
  const [statusFilter, setStatusFilter] = useState(filters.status)
  const notAvailable = t('notAvailable')

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextSearch = overrides.search ?? search
    const nextStatus = overrides.status ?? statusFilter
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextSearch.trim() !== '') {
      query.search = nextSearch.trim()
    }
    if (nextStatus !== '') {
      query.status = nextStatus
    }
    if (nextPage > 1) {
      query.page = String(nextPage)
    }

    return query
  }

  function applyFilters(overrides: Partial<Filters & { page?: number }> = {}) {
    localizedRouter.get(`/tenant/events/${event.id}/attendees`, queryParams(overrides), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  function submitFilters(eventForm: FormEvent) {
    eventForm.preventDefault()
    applyFilters({ page: 1 })
  }

  function exportHref(): string {
    const params = new URLSearchParams(queryParams({ page: 1 }))
    const query = params.toString()

    return localizedPath(`/tenant/events/${event.id}/attendees/export${query ? `?${query}` : ''}`)
  }

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    { value: 'not_registered', label: t('inviteStatusNotRegistered') },
    { value: 'registered', label: t('inviteStatusRegistered') },
    { value: 'attended', label: t('inviteStatusAttended') },
    { value: 'not_attended', label: t('inviteStatusNotAttended') },
  ]

  return (
    <DashboardLayout title={t('attendees')}>
      <PageHeader
        title={t('attendees')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('attendees') },
        ]}
        actions={(
          <a href={exportHref()} className="button-secondary">
            {t('exportExcel')}
          </a>
        )}
      />
      <PageContent>
        <form onSubmit={submitFilters}>
          <FiltersBar>
            <SearchInput
              value={search}
              onChange={setSearch}
              label={t('search')}
              placeholder={t('searchAttendee')}
            />
            <SelectInput
              label={t('inviteStatus')}
              name="status"
              value={statusFilter}
              onChange={(changeEvent) => {
                const nextStatus = changeEvent.target.value
                setStatusFilter(nextStatus)
                applyFilters({ status: nextStatus, page: 1 })
              }}
              options={statusOptions}
            />
            <button type="submit" className="button-primary">{t('search')}</button>
          </FiltersBar>
        </form>

        {attendees.length === 0 ? (
          <EmptyState
            title={t('noAttendees')}
            detail={t('noAttendeesDetail')}
          />
        ) : (
          <>
            <DataTable
              rows={attendees as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'display_name',
                  header: t('attendeeName'),
                  render: (row) => {
                    const attendee = row as unknown as AttendeeRow
                    const name = displayValue(attendee.display_name, attendee.email ?? notAvailable)

                    if (attendee.row_type === 'invite' || String(attendee.id).startsWith('invite-')) {
                      return <span className="font-medium text-[var(--ink)]">{name}</span>
                    }

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
                  key: 'invite_status',
                  header: t('inviteStatus'),
                  render: (row) => {
                    const attendee = row as unknown as AttendeeRow
                    const inviteStatus = attendee.invite_status
                      ?? (attendee.status === 'checked_in' ? 'attended' : 'registered')

                    return <StatusBadge status={inviteStatus} />
                  },
                },
                {
                  key: 'credential_status',
                  header: t('attendeesCredential'),
                  render: (row) => {
                    const status = row.credential_status as string | null | undefined

                    return status ? <StatusBadge status={status} /> : '—'
                  },
                },
              ]}
            />
            <Pagination
              page={pagination.page}
              totalPages={pagination.last_page}
              onPageChange={(page) => applyFilters({ page })}
              previousLabel={t('previousPage')}
              nextLabel={t('nextPage')}
              pageLabel={t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
            />
          </>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
