import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ReasonModal from '@/components/modals/ReasonModal'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type PrintJobRow = {
  id: string
  attendee_id: string | null
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
  const [statusFilter, setStatusFilter] = useState(filters.status)
  const [reprintTarget, setReprintTarget] = useState<PrintJobRow | null>(null)
  const [reprinting, setReprinting] = useState(false)

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

  async function handleReprint(reason: string) {
    if (!reprintTarget) return

    setReprinting(true)
    try {
      await fetch(`/api/v1/tenant/events/${event.id}/badge-print-jobs/${reprintTarget.id}/reprint`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify({ reprint_reason: reason }),
      })
    } finally {
      setReprinting(false)
      setReprintTarget(null)
    }
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'مهام طباعة الشارات' : 'Badge print jobs'}>
      <PageHeader
        title={locale === 'ar' ? 'مهام طباعة الشارات' : 'Badge print jobs'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'مهام الطباعة' : 'Print jobs' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/badge-templates`}>{locale === 'ar' ? 'قوالب الشارات' : 'Badge templates'}</LocalizedLink>}
      />
      <PageContent>
        <FiltersBar>
          <SelectInput
            label={locale === 'ar' ? 'الحالة' : 'Status'}
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
          <EmptyState title={locale === 'ar' ? 'لا توجد مهام طباعة' : 'No print jobs yet'} />
        ) : (
          <>
            <DataTable
              rows={printJobs as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'status',
                  header: locale === 'ar' ? 'الحالة' : 'Status',
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'is_reprint',
                  header: locale === 'ar' ? 'إعادة طباعة' : 'Reprint',
                  render: (row) => (row.is_reprint ? (locale === 'ar' ? 'نعم' : 'Yes') : (locale === 'ar' ? 'لا' : 'No')),
                },
                {
                  key: 'printed_at',
                  header: locale === 'ar' ? 'وقت الطباعة' : 'Printed at',
                  render: (row) => (row.printed_at ? new Date(String(row.printed_at)).toLocaleString() : '—'),
                },
                {
                  key: 'actions',
                  header: locale === 'ar' ? 'إجراءات' : 'Actions',
                  render: (row) => (
                    <PermissionGate permission="badge.reprint">
                      <button type="button" className="button-secondary" onClick={() => setReprintTarget(printJobs.find((job) => job.id === row.id) ?? null)}>
                        {locale === 'ar' ? 'إعادة طباعة' : 'Reprint'}
                      </button>
                    </PermissionGate>
                  ),
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

      <ReasonModal
        open={reprintTarget !== null}
        title={locale === 'ar' ? 'إعادة طباعة الشارة' : 'Reprint badge'}
        message={locale === 'ar' ? 'يرجى تقديم سبب لإعادة الطباعة.' : 'Please provide a reason for this reprint.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'إعادة طباعة' : 'Reprint'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        loading={reprinting}
        onConfirm={handleReprint}
        onCancel={() => setReprintTarget(null)}
      />
    </DashboardLayout>
  )
}
