import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type RecentCheckin = {
  id: string
  result: string
  reason: string | null
  scanned_at: string | null
}

type RecentPrintJob = {
  id: string
  status: string
  is_reprint: boolean
  reprint_reason: string | null
  printed_at: string | null
}

type KioskDetail = {
  id: string
  device_name: string
  device_code: string
  status: string
  printer_status: string
  last_heartbeat_at: string | null
  confirmation_required: boolean
  location_label: string | null
  recent_checkins: RecentCheckin[]
  recent_print_jobs: RecentPrintJob[]
}

type Props = {
  event: EventRow
  tenantId: string
  kiosk: KioskDetail
}

export default function KioskDetailPage({ event, kiosk }: Props) {
  const { locale } = useLocale()

  return (
    <DashboardLayout title={kiosk.device_name}>
      <PageHeader
        title={kiosk.device_name}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الكشكات' : 'Kiosks', href: `/tenant/events/${event.id}/kiosks` },
          { label: kiosk.device_name },
        ]}
        actions={
          <LocalizedLink className="button-secondary" href={`/kiosk/${kiosk.device_code}`}>
            {locale === 'ar' ? 'وضع الكشك' : 'Kiosk mode'}
          </LocalizedLink>
        }
      />
      <PageContent>
        <dl className="grid gap-4 sm:grid-cols-2">
          <div>
            <dt className="text-sm text-slate-500">{locale === 'ar' ? 'رمز الجهاز' : 'Device code'}</dt>
            <dd className="font-mono">{kiosk.device_code}</dd>
          </div>
          <div>
            <dt className="text-sm text-slate-500">{locale === 'ar' ? 'الحالة' : 'Status'}</dt>
            <dd><StatusBadge status={kiosk.status} /></dd>
          </div>
          <div>
            <dt className="text-sm text-slate-500">{locale === 'ar' ? 'الطابعة' : 'Printer'}</dt>
            <dd>{kiosk.printer_status}</dd>
          </div>
          <div>
            <dt className="text-sm text-slate-500">{locale === 'ar' ? 'الموقع' : 'Location'}</dt>
            <dd>{kiosk.location_label ?? '—'}</dd>
          </div>
        </dl>

        <section className="mt-8">
          <h2 className="mb-4 text-lg font-semibold">{locale === 'ar' ? 'عمليات تسجيل الحضور الأخيرة' : 'Recent check-ins'}</h2>
          {kiosk.recent_checkins.length === 0 ? (
            <p>{locale === 'ar' ? 'لا توجد عمليات بعد.' : 'No check-ins yet.'}</p>
          ) : (
            <DataTable
              rows={kiosk.recent_checkins as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                { key: 'result', header: locale === 'ar' ? 'النتيجة' : 'Result' },
                { key: 'reason', header: locale === 'ar' ? 'السبب' : 'Reason' },
                {
                  key: 'scanned_at',
                  header: locale === 'ar' ? 'الوقت' : 'Time',
                  render: (row) => (row.scanned_at ? new Date(String(row.scanned_at)).toLocaleString() : '—'),
                },
              ]}
            />
          )}
        </section>

        <section className="mt-8">
          <h2 className="mb-4 text-lg font-semibold">{locale === 'ar' ? 'طباعة الشارات الأخيرة' : 'Recent print jobs'}</h2>
          {kiosk.recent_print_jobs.length === 0 ? (
            <p>{locale === 'ar' ? 'لا توجد مهام طباعة بعد.' : 'No print jobs yet.'}</p>
          ) : (
            <DataTable
              rows={kiosk.recent_print_jobs as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                { key: 'status', header: locale === 'ar' ? 'الحالة' : 'Status' },
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
              ]}
            />
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
