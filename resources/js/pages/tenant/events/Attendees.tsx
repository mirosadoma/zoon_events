import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import { router } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, { SideDetailInfoGrid } from '@/components/layout/SideDetailPane'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import ConfirmModal from '@/components/modals/ConfirmModal'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import SendPrivateInviteModal from '@/components/events/SendPrivateInviteModal'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { ArrowUpRight, Mail, UserRound } from 'lucide-react'

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

function attendeeInitials(name: string): string {
  const parts = name
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)

  if (parts.length === 0) {
    return '?'
  }

  return parts.map((part) => part.charAt(0).toUpperCase()).join('')
}

function resolveDetailId(attendee: AttendeeRow): string | null {
  return attendee.attendee_id
    ?? (attendee.row_type === 'invite' || String(attendee.id).startsWith('invite-')
      ? null
      : attendee.id)
}

function resolveInviteId(attendee: AttendeeRow): string | null {
  if (attendee.row_type !== 'invite' && !String(attendee.id).startsWith('invite-')) {
    return null
  }

  const raw = String(attendee.id).replace(/^invite-/, '')
  return raw !== '' ? raw : null
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
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const notAvailable = t('notAvailable')

  const selected = attendees.find((row) => row.id === selectedId) ?? null
  const selectedDetailId = selected ? resolveDetailId(selected) : null
  const selectedInviteId = selected ? resolveInviteId(selected) : null

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

  function openPane(row: AttendeeRow) {
    setSelectedId(row.id)
  }

  function closePane() {
    setSelectedId(null)
    setDeleteOpen(false)
  }

  function goToEdit() {
    if (!selectedDetailId) {
      toast(t('attendeePaneEditUnavailable'), 'info')
      return
    }

    router.visit(localizedPath(`/tenant/events/${event.id}/attendees/${selectedDetailId}`))
  }

  async function confirmDelete() {
    if (!selected) return

    setDeleting(true)
    try {
      if (selectedInviteId) {
        if (selectedDetailId) {
          await apiFetch(`/api/v1/tenant/events/${event.id}/attendees/${selectedDetailId}`, {
            method: 'DELETE',
            tenantId,
            idempotency: true,
          })
        }

        await apiFetch(`/api/v1/tenant/events/${event.id}/invites/${selectedInviteId}`, {
          method: 'DELETE',
          tenantId,
          idempotency: true,
        })
      } else if (selectedDetailId) {
        await apiFetch(`/api/v1/tenant/events/${event.id}/attendees/${selectedDetailId}`, {
          method: 'DELETE',
          tenantId,
          idempotency: true,
        })
      } else {
        toast(t('attendeePaneDeleteUnavailable'), 'error')
        return
      }

      toast(t('attendeePaneDeleted'), 'success')
      setDeleteOpen(false)
      setSelectedId(null)
      router.reload({ only: ['attendees', 'pagination'] })
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setDeleting(false)
    }
  }

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    { value: 'not_registered', label: t('inviteStatusNotRegistered') },
    { value: 'registered', label: t('inviteStatusRegistered') },
    { value: 'attended', label: t('inviteStatusAttended') },
    { value: 'not_attended', label: t('inviteStatusNotAttended') },
  ]

  const inviteStatus = selected
    ? (selected.invite_status ?? (selected.status === 'checked_in' ? 'attended' : 'registered'))
    : null

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
                closePane()
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
                closePane()
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
              selectedRowKey={selectedId}
              onRowClick={(row) => openPane(row as unknown as AttendeeRow)}
              columns={[
                {
                  key: 'display_name',
                  header: t('attendeeName'),
                  render: (row) => {
                    const attendee = row as unknown as AttendeeRow
                    return (
                      <span className="font-medium text-[var(--ink)]">
                        {displayValue(attendee.display_name, notAvailable)}
                      </span>
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
                    const status = attendee.invite_status
                      ?? (attendee.status === 'checked_in' ? 'attended' : 'registered')

                    return <StatusBadge status={status} />
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

      <SideDetailPane
        open={selected !== null}
        title={selected ? displayValue(selected.display_name, selected.label) : ''}
        subtitle={selected?.email?.trim() || null}
        onClose={closePane}
        onEdit={goToEdit}
        onDelete={selectedDetailId || selectedInviteId ? () => setDeleteOpen(true) : null}
        hero={selected ? (
          <div className="flex items-center gap-4">
            <div className="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-[var(--brand-soft)] text-lg font-bold tracking-wide text-[var(--brand)] ring-1 ring-[color-mix(in_srgb,var(--brand)_18%,transparent)]">
              {attendeeInitials(displayValue(selected.display_name, selected.label))}
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              <p className="truncate text-base font-semibold text-[var(--ink)]">
                {displayValue(selected.display_name, selected.label)}
              </p>
              <div className="flex flex-wrap items-center gap-2">
                {inviteStatus ? <StatusBadge status={inviteStatus} /> : null}
                {selected.credential_status ? <StatusBadge status={selected.credential_status} /> : null}
              </div>
            </div>
          </div>
        ) : null}
        footer={selectedDetailId ? (
          <LocalizedLink
            href={`/tenant/events/${event.id}/attendees/${selectedDetailId}`}
            className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] px-4 py-2.5 text-sm font-semibold text-[var(--ink)] transition hover:border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]"
          >
            {t('showAttendeeDetails')}
            <ArrowUpRight className="h-4 w-4" aria-hidden />
          </LocalizedLink>
        ) : (
          <div className="flex items-start gap-2 rounded-xl border border-dashed border-[var(--border)] bg-[var(--surface)]/60 px-3 py-3 text-sm text-[var(--muted)]">
            <UserRound className="mt-0.5 h-4 w-4 shrink-0" aria-hidden />
            <span>{t('attendeePaneEditUnavailable')}</span>
          </div>
        )}
      >
        {selected ? (
          <SideDetailInfoGrid
            title={t('attendeePaneBasicInfo')}
            items={[
              {
                label: t('attendeeName'),
                value: displayValue(selected.display_name, notAvailable),
              },
              {
                label: t('attendeeEmail'),
                value: displayValue(selected.email, notAvailable),
              },
              {
                label: t('attendeePhone'),
                value: displayValue(selected.phone, notAvailable),
              },
              {
                label: t('inviteStatus'),
                value: inviteStatus ? <StatusBadge status={inviteStatus} /> : notAvailable,
              },
              {
                label: t('attendeesCredential'),
                value: selected.credential_status
                  ? <StatusBadge status={selected.credential_status} />
                  : notAvailable,
              },
              {
                label: t('attendeeDetailLocale'),
                value: (selected.locale || notAvailable).toUpperCase(),
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

      <ConfirmModal
        open={deleteOpen}
        title={t('attendeePaneDeleteTitle')}
        message={t('attendeePaneDeleteMessage', {
          name: selected ? displayValue(selected.display_name, selected.label) : '',
        })}
        confirmLabel={t('delete')}
        cancelLabel={t('cancel')}
        loading={deleting}
        onConfirm={() => void confirmDelete()}
        onCancel={() => setDeleteOpen(false)}
      />

      <SendPrivateInviteModal
        open={inviteOpen}
        eventId={event.id}
        tenantId={tenantId}
        onClose={() => setInviteOpen(false)}
      />
    </DashboardLayout>
  )
}
