import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import BadgePrintPreviewModal, { type BadgeFieldOverrides } from '@/components/badges/BadgePrintPreviewModal'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { openBlankPrintWindow, writeBadgePrintDocument } from '@/lib/openBadgePrintWindow'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type PrintJobRow = {
  id: string
  attendee_id: string | null
  credential_id: string | null
  attendee_name: string | null
  status: string
  failure_reason: string | null
  is_reprint: boolean
  reprint_reason: string | null
  original_print_job_id: string | null
  printed_at: string | null
}

type Filters = {
  status: string
}

type Props = {
  event: EventRow
  tenantId: string
  printJobs: PrintJobRow[]
  filters?: Filters
  pagination?: PaginationMeta
}

const PRINT_JOB_STATUSES = ['queued', 'printed', 'failed']

export default function BadgePrintJobs({
  event,
  tenantId,
  printJobs,
  filters = { status: '' },
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const [statusFilter, setStatusFilter] = useState(filters.status)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [reprintTarget, setReprintTarget] = useState<PrintJobRow | null>(null)
  const [reprinting, setReprinting] = useState(false)
  const selected = printJobs.find((job) => job.id === selectedId) ?? null
  const notAvailable = '—'

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextStatus = overrides.status ?? statusFilter
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextStatus !== '') query.status = nextStatus

    return withPage(query, nextPage)
  }

  function applyFilters(overrides: Partial<Filters & { page?: number }> = {}) {
    localizedRouter.get(`/tenant/events/${event.id}/badge-print-jobs`, queryParams(overrides), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    ...PRINT_JOB_STATUSES.map((status) => ({ value: status, label: status })),
  ]

  async function handleReprint(result: { overrides: BadgeFieldOverrides; reason?: string }) {
    if (!reprintTarget || !result.reason) return

    setReprinting(true)
    const printWindow = openBlankPrintWindow()
    try {
      const job = await apiFetch<{ print_html?: string | null }>(
        `/api/v1/tenant/events/${event.id}/badge-print-jobs/${reprintTarget.id}/reprint`,
        {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: {
            reprint_reason: result.reason,
            field_overrides: result.overrides,
          },
        },
      )

      const opened = writeBadgePrintDocument(printWindow, job.print_html)
      toast(
        opened ? t('attendeeDetailBadgePrintOpened') : t('attendeeDetailBadgeJobCreated'),
        opened ? 'success' : 'info',
      )
      setReprintTarget(null)
      applyFilters()
    } catch (error) {
      printWindow?.close()
      const message = error instanceof ApiFetchError
        ? error.message
        : t('attendeeDetailBadgeFailed')
      toast(message, 'error')
    } finally {
      setReprinting(false)
    }
  }

  function openReprint(job: PrintJobRow) {
    if (!job.attendee_id || !job.credential_id) {
      toast(t('badgePrintReprintMissingLinks'), 'error')
      return
    }
    setReprintTarget(job)
  }

  return (
    <DashboardLayout title={t('badgePrintJobs')}>
      <PageHeader
        title={t('badgePrintJobs')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('badgePrintJobs') },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/badge-templates`}>{t('badgeTemplates')}</LocalizedLink>}
      />
      <PageContent>
        <FiltersBar>
          <SelectInput
            label={t('status')}
            name="status"
            value={statusFilter}
            onChange={(changeEvent) => {
              const next = changeEvent.target.value
              setStatusFilter(next)
              applyFilters({ status: next, page: 1 })
            }}
            options={statusOptions}
          />
        </FiltersBar>

        {printJobs.length === 0 ? (
          <EmptyState title={t('badgePrintNoJobs')} />
        ) : (
          <>
            <DataTable
              rows={printJobs as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              selectedRowKey={selectedId}
              onRowClick={(row) => setSelectedId(String(row.id))}
              columns={[
                {
                  key: 'attendee_name',
                  header: t('badgePrintAttendee'),
                  render: (row) => {
                    const name = row.attendee_name ? String(row.attendee_name) : null
                    const attendeeId = row.attendee_id ? String(row.attendee_id) : null
                    return name || attendeeId || '—'
                  },
                },
                {
                  key: 'status',
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'is_reprint',
                  header: t('badgePrintReprint'),
                  render: (row) => (row.is_reprint ? t('yes') : t('no')),
                },
                {
                  key: 'reprint_reason',
                  header: t('badgePrintReason'),
                  render: (row) => (row.reprint_reason ? String(row.reprint_reason) : '—'),
                },
                {
                  key: 'printed_at',
                  header: t('badgePrintPrintedAt'),
                  render: (row) => (row.printed_at ? new Date(String(row.printed_at)).toLocaleString() : '—'),
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
        title={selected?.attendee_name?.trim() || selected?.id || t('badgePrintJobs')}
        subtitle={selected ? selected.status : null}
        onClose={() => setSelectedId(null)}
        footer={selected?.attendee_id && selected.credential_id ? (
          <PermissionGate permission="badge.reprint">
            <SideDetailActions>
              <button
                type="button"
                className={sideDetailActionClassName()}
                onClick={() => openReprint(selected)}
              >
                {t('badgePrintReprintAction')}
              </button>
            </SideDetailActions>
          </PermissionGate>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('badgePrintAttendee'),
                value: selected.attendee_name || selected.attendee_id || notAvailable,
              },
              {
                label: t('status'),
                value: <StatusBadge status={selected.status} />,
              },
              {
                label: t('badgePrintReprint'),
                value: selected.is_reprint ? t('yes') : t('no'),
              },
              {
                label: t('badgePrintReason'),
                value: selected.reprint_reason || notAvailable,
              },
              {
                label: t('badgePrintPrintedAt'),
                value: selected.printed_at
                  ? new Date(selected.printed_at).toLocaleString()
                  : notAvailable,
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

      {reprintTarget?.attendee_id && reprintTarget.credential_id ? (
        <BadgePrintPreviewModal
          open
          mode="reprint"
          eventId={event.id}
          tenantId={tenantId}
          attendeeId={reprintTarget.attendee_id}
          credentialId={reprintTarget.credential_id}
          attendeeName={reprintTarget.attendee_name}
          loading={reprinting}
          onCancel={() => setReprintTarget(null)}
          onConfirm={(result) => void handleReprint(result)}
        />
      ) : null}
    </DashboardLayout>
  )
}
