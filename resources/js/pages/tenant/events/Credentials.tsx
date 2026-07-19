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
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'

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

type Filters = {
  search: string
  status: string
}

type Props = {
  event: EventRow
  credentials: CredentialRow[]
  filters?: Filters
  pagination?: PaginationMeta
}

const CREDENTIAL_STATUSES = ['active', 'revoked', 'expired', 'superseded']

export default function Credentials({
  event,
  credentials,
  filters = { search: '', status: '' },
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [search, setSearch] = useState(filters.search)
  const [statusFilter, setStatusFilter] = useState(filters.status)

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextSearch = overrides.search ?? search
    const nextStatus = overrides.status ?? statusFilter
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextSearch.trim() !== '') query.search = nextSearch.trim()
    if (nextStatus !== '') query.status = nextStatus

    return withPage(query, nextPage)
  }

  function applyFilters(overrides: Partial<Filters & { page?: number }> = {}) {
    localizedRouter.get(`/tenant/events/${event.id}/credentials`, queryParams(overrides), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  function submitFilters(eventForm: FormEvent) {
    eventForm.preventDefault()
    applyFilters({ page: 1 })
  }

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    ...CREDENTIAL_STATUSES.map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={t('credentials')}>
      <PageHeader
        title={t('credentials')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('credentials') },
        ]}
      />
      <PageContent>
        <form onSubmit={submitFilters}>
          <FiltersBar>
            <SearchInput
              value={search}
              onChange={setSearch}
              label={t('search')}
              placeholder={t('credentialsSearchPlaceholder')}
            />
            <SelectInput
              label={t('status')}
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

        {credentials.length === 0 ? (
          <EmptyState title={t('noCredentials')} detail={t('noCredentialsDetail')} />
        ) : (
          <>
            <DataTable
              rows={credentials as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'code',
                  header: t('credentialsCode'),
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
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'attendee_id',
                  header: t('attendees'),
                  render: (row) => {
                    const credential = row as unknown as CredentialRow

                    return (
                      <LocalizedLink href={`/tenant/events/${event.id}/attendees/${credential.attendee_id}`} className="text-sky-700 hover:underline">
                        {credential.attendee_label ?? credential.attendee_id}
                      </LocalizedLink>
                    )
                  },
                },
                { key: 'issued_at', header: t('credentialsIssued') },
                { key: 'expires_at', header: t('credentialsExpires') },
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
