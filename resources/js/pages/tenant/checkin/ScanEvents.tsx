import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'
import { scanReasonLabel } from '@/lib/scanLabels'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type ScanEventRow = {
  id: string
  result: string
  scanner_type: string
  gate_id?: string | null
  gate_name?: string | null
  zone_id?: string | null
  zone_name?: string | null
  offline: boolean
  attendee_id?: string | null
  reason?: string | null
  scanned_at?: string | null
}

type Filters = {
  result: string
  scanner_type: string
  offline: boolean
}

type Props = {
  event: EventRow
  scanEvents: ScanEventRow[]
  filters?: Filters
  pagination?: PaginationMeta
}

const SCAN_RESULTS = [
  'accepted', 'manual_override', 'duplicate', 'revoked', 'expired',
  'rejected', 'unauthorized_zone', 'anti_passback_rejected',
]

const SCANNER_TYPES = [
  'staff_phone', 'handheld_scanner', 'kiosk', 'gate', 'acs_lane', 'acs_gate', 'manual_desk',
]

export default function ScanEvents({
  event,
  scanEvents,
  filters = { result: '', scanner_type: '', offline: false },
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [resultFilter, setResultFilter] = useState(filters.result)
  const [scannerFilter, setScannerFilter] = useState(filters.scanner_type)
  const [offlineOnly, setOfflineOnly] = useState(filters.offline)

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextResult = overrides.result ?? resultFilter
    const nextScanner = overrides.scanner_type ?? scannerFilter
    const nextOffline = overrides.offline ?? offlineOnly
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextResult !== '') query.result = nextResult
    if (nextScanner !== '') query.scanner_type = nextScanner
    if (nextOffline) query.offline = '1'

    return withPage(query, nextPage)
  }

  function applyFilters(overrides: Partial<Filters & { page?: number }> = {}) {
    localizedRouter.get(`/tenant/events/${event.id}/scan-events`, queryParams(overrides), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const resultOptions = [
    { value: '', label: locale === 'ar' ? 'كل النتائج' : 'All results' },
    ...SCAN_RESULTS.map((result) => ({ value: result, label: result })),
  ]

  const scannerOptions = [
    { value: '', label: locale === 'ar' ? 'كل الماسحات' : 'All scanners' },
    ...SCANNER_TYPES.map((type) => ({ value: type, label: type })),
  ]

  return (
    <DashboardLayout title={locale === 'ar' ? 'أحداث المسح' : 'Scan events'}>
      <PageHeader
        title={locale === 'ar' ? 'أحداث المسح' : 'Scan events'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'أحداث المسح' : 'Scan events' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/check-in-dashboard`}>{locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard'}</LocalizedLink>}
      />
      <PageContent>
        <FiltersBar>
          <SelectInput
            label={locale === 'ar' ? 'النتيجة' : 'Result'}
            name="result"
            value={resultFilter}
            onChange={(changeEvent) => {
              const next = changeEvent.target.value
              setResultFilter(next)
              applyFilters({ result: next, page: 1 })
            }}
            options={resultOptions}
          />
          <SelectInput
            label={locale === 'ar' ? 'نوع الماسح' : 'Scanner type'}
            name="scanner_type"
            value={scannerFilter}
            onChange={(changeEvent) => {
              const next = changeEvent.target.value
              setScannerFilter(next)
              applyFilters({ scanner_type: next, page: 1 })
            }}
            options={scannerOptions}
          />
          <CheckboxInput
            label={locale === 'ar' ? 'غير متصل فقط' : 'Offline only'}
            name="offline"
            checked={offlineOnly}
            onChange={(changeEvent) => {
              const next = changeEvent.target.checked
              setOfflineOnly(next)
              applyFilters({ offline: next, page: 1 })
            }}
          />
        </FiltersBar>

        {scanEvents.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أحداث مسح' : 'No scan events yet'}
            detail={locale === 'ar' ? 'ستظهر الأحداث بعد المسح.' : 'Events will appear after scans are submitted.'}
          />
        ) : (
          <>
            <DataTable
              rows={scanEvents as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'result',
                  header: locale === 'ar' ? 'النتيجة' : 'Result',
                  render: (row) => <StatusBadge status={String(row.result)} />,
                },
                { key: 'scanner_type', header: locale === 'ar' ? 'الماسح' : 'Scanner' },
                {
                  key: 'gate_id',
                  header: locale === 'ar' ? 'البوابة' : 'Gate',
                  render: (row) => {
                    const scan = row as unknown as ScanEventRow
                    return scan.gate_name ?? scan.gate_id ?? '—'
                  },
                },
                {
                  key: 'zone_id',
                  header: locale === 'ar' ? 'المنطقة' : 'Zone',
                  render: (row) => {
                    const scan = row as unknown as ScanEventRow
                    return scan.zone_name ?? scan.zone_id ?? '—'
                  },
                },
                {
                  key: 'offline',
                  header: locale === 'ar' ? 'غير متصل' : 'Offline',
                  render: (row) => (row.offline ? (locale === 'ar' ? 'نعم' : 'Yes') : '—'),
                },
                {
                  key: 'reason',
                  header: locale === 'ar' ? 'السبب' : 'Reason',
                  render: (row) => scanReasonLabel(String(row.reason ?? ''), locale),
                },
                { key: 'scanned_at', header: locale === 'ar' ? 'وقت المسح' : 'Scanned at' },
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
