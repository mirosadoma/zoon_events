import LocalizedLink from '@/components/routing/LocalizedLink'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import { useLocale } from '@/hooks/useLocale'
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

type Props = {
  event: EventRow
  scanEvents: ScanEventRow[]
}

export default function ScanEvents({ event, scanEvents }: Props) {
  const { locale, t } = useLocale()
  const [resultFilter, setResultFilter] = useState('')
  const [scannerFilter, setScannerFilter] = useState('')
  const [offlineOnly, setOfflineOnly] = useState(false)

  const filtered = useMemo(() => scanEvents.filter((scan) => {
    const matchesResult = resultFilter === '' || scan.result === resultFilter
    const matchesScanner = scannerFilter === '' || scan.scanner_type === scannerFilter
    const matchesOffline = !offlineOnly || scan.offline

    return matchesResult && matchesScanner && matchesOffline
  }), [scanEvents, resultFilter, scannerFilter, offlineOnly])

  const resultOptions = [
    { value: '', label: locale === 'ar' ? 'كل النتائج' : 'All results' },
    ...Array.from(new Set(scanEvents.map((scan) => scan.result))).map((result) => ({ value: result, label: result })),
  ]

  const scannerOptions = [
    { value: '', label: locale === 'ar' ? 'كل الماسحات' : 'All scanners' },
    ...Array.from(new Set(scanEvents.map((scan) => scan.scanner_type))).map((type) => ({ value: type, label: type })),
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
            onChange={(changeEvent) => setResultFilter(changeEvent.target.value)}
            options={resultOptions}
          />
          <SelectInput
            label={locale === 'ar' ? 'نوع الماسح' : 'Scanner type'}
            name="scanner_type"
            value={scannerFilter}
            onChange={(changeEvent) => setScannerFilter(changeEvent.target.value)}
            options={scannerOptions}
          />
          <CheckboxInput
            label={locale === 'ar' ? 'غير متصل فقط' : 'Offline only'}
            name="offline"
            checked={offlineOnly}
            onChange={(changeEvent) => setOfflineOnly(changeEvent.target.checked)}
          />
        </FiltersBar>

        {filtered.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أحداث مسح' : 'No scan events yet'}
            detail={locale === 'ar' ? 'ستظهر الأحداث بعد المسح.' : 'Events will appear after scans are submitted.'}
          />
        ) : (
          <DataTable
            rows={filtered as unknown as Record<string, unknown>[]}
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
        )}
      </PageContent>
    </DashboardLayout>
  )
}
