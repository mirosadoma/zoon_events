import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import SendPrivateInviteModal from '@/components/events/SendPrivateInviteModal'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import { Mail } from 'lucide-react'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  tier?: string
  registration_url?: string | null
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
  attendee_id?: string | null
  locale: string
  credential_status?: string | null
}

type Filters = {
  search: string
  status: string
  registration_type?: string
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
  tenantId?: string
  canSendPrivateInvites?: boolean
}

function displayValue(value: string | null | undefined, fallback: string): string {
  return value?.trim() ? value.trim() : fallback
}

export default function Attendees({
  event,
  attendees,
  filters = { search: '', status: '', registration_type: 'public' },
  pagination = { page: 1, per_page: 25, total: 0, last_page: 1 },
  tenantId = '',
  canSendPrivateInvites = false,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const [search, setSearch] = useState(filters.search)
  const [statusFilter, setStatusFilter] = useState(filters.status)
  const [registrationType, setRegistrationType] = useState(filters.registration_type ?? 'public')
  const [inviteOpen, setInviteOpen] = useState(false)
  const notAvailable = t('notAvailable')

  async function copyRegistrationLink(url: string) {
    try {
      await navigator.clipboard.writeText(url)
      toast(t('copied'), 'success')
    } catch {
      toast(t('eventDetailCouldNotCopyLink'), 'error')
    }
  }

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextSearch = overrides.search ?? search
    const nextStatus = overrides.status ?? statusFilter
    const nextRegistrationType = overrides.registration_type ?? registrationType
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextSearch.trim() !== '') {
      query.search = nextSearch.trim()
    }
    if (nextStatus !== '') {
      query.status = nextStatus
    }
    if (nextRegistrationType && nextRegistrationType !== 'public') {
      query.registration_type = nextRegistrationType
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
        <div className="mb-4 border-b border-[var(--border)]">
          <div className="flex gap-4">
            <button
              type="button"
              className={`px-4 py-2 text-sm font-medium transition-colors ${
                registrationType === 'public'
                  ? 'border-b-2 border-[var(--brand)] text-[var(--brand)]'
                  : 'text-[var(--muted)] hover:text-[var(--ink)]'
              }`}
              onClick={() => {
                setRegistrationType('public')
                applyFilters({ registration_type: 'public', page: 1 })
              }}
            >
              {t('attendeesPublicRegistration')}
            </button>
            <button
              type="button"
              className={`px-4 py-2 text-sm font-medium transition-colors ${
                registrationType === 'private'
                  ? 'border-b-2 border-[var(--brand)] text-[var(--brand)]'
                  : 'text-[var(--muted)] hover:text-[var(--ink)]'
              }`}
              onClick={() => {
                setRegistrationType('private')
                applyFilters({ registration_type: 'private', page: 1 })
              }}
            >
              {t('attendeesPrivateRegistration')}
            </button>
          </div>
        </div>

        {registrationType === 'public' && event.registration_url ? (
          <div className="mb-4">
            <CopyRegistrationLinkButton
              className="button-secondary"
              onClick={() => void copyRegistrationLink(event.registration_url!)}
            />
          </div>
        ) : null}

        {registrationType === 'private' && canSendPrivateInvites ? (
          <div className="mb-4">
            <PermissionGate permission="event.invite.manage">
              <button
                type="button"
                className="button-primary inline-flex items-center gap-2"
                onClick={() => setInviteOpen(true)}
              >
                <Mail className="h-4 w-4" aria-hidden="true" />
                {t('sendPrivateLink')}
              </button>
            </PermissionGate>
          </div>
        ) : null}

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
                    const name = displayValue(attendee.display_name, notAvailable)
                    const detailId = attendee.attendee_id
                      ?? (attendee.row_type === 'invite' || String(attendee.id).startsWith('invite-')
                        ? null
                        : attendee.id)

                    if (!detailId) {
                      return <span className="font-medium text-[var(--ink)]">{name}</span>
                    }

                    return (
                      <LocalizedLink href={`/tenant/events/${event.id}/attendees/${detailId}`} className="font-medium text-sky-700 hover:underline">
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
                {
                  key: 'actions',
                  header: t('actions'),
                  render: (row) => {
                    const attendee = row as unknown as AttendeeRow
                    const detailId = attendee.attendee_id
                      ?? (attendee.row_type === 'invite' || String(attendee.id).startsWith('invite-')
                        ? null
                        : attendee.id)

                    if (!detailId) {
                      return '—'
                    }

                    return (
                      <LocalizedLink
                        href={`/tenant/events/${event.id}/attendees/${detailId}`}
                        className="button-secondary"
                      >
                        {t('showAttendeeDetails')}
                      </LocalizedLink>
                    )
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

      <SendPrivateInviteModal
        open={inviteOpen}
        eventId={event.id}
        tenantId={tenantId}
        onClose={() => setInviteOpen(false)}
      />
    </DashboardLayout>
  )
}
