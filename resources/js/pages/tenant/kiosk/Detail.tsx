import LocalizedLink from '@/components/routing/LocalizedLink'
import { MapPin, MonitorSmartphone, Printer } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { HeartbeatIndicator } from '@/components/kiosk/HeartbeatIndicator'
import { DetailsCard, EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

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

function formatDateTime(value: string | null, locale: string): string {
  if (!value) return '—'
  return new Date(value).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US')
}

export default function KioskDetailPage({ event, kiosk }: Props) {
  const { locale, t } = useLocale()
  const heartbeatKiosk: Kiosk = {
    id: String(kiosk.id),
    device_name: kiosk.device_name,
    device_code: kiosk.device_code,
    status: kiosk.status as Kiosk['status'],
    printer_status: kiosk.printer_status as Kiosk['printer_status'],
    last_heartbeat_at: kiosk.last_heartbeat_at,
    confirmation_required: kiosk.confirmation_required,
  }

  return (
    <DashboardLayout title={kiosk.device_name}>
      <PageHeader
        title={kiosk.device_name}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('kioskPageKiosks'), href: `/tenant/events/${event.id}/kiosks` },
          { label: kiosk.device_name },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/kiosks`}>
              {t('kioskPageBackToList')}
            </LocalizedLink>
            <LocalizedLink className="button-primary" href={`/kiosk/${kiosk.device_code}/unlock`}>
              {t('kioskPageMode')}
            </LocalizedLink>
          </div>
        )}
      />
      <PageContent>
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
          <div className="ta-card ta-stat-card ta-stat-card-sky">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="ta-stat-label">{t('status')}</p>
                <div className="mt-2">
                  <StatusBadge status={kiosk.status} size="md" />
                </div>
              </div>
              <div className="ta-stat-icon">
                <MonitorSmartphone className="h-5 w-5" aria-hidden />
              </div>
            </div>
          </div>

          <div className="ta-card ta-stat-card ta-stat-card-violet">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="ta-stat-label">{t('kioskPagePrinter')}</p>
                <div className="mt-2">
                  <StatusBadge status={kiosk.printer_status} size="md" />
                </div>
              </div>
              <div className="ta-stat-icon">
                <Printer className="h-5 w-5" aria-hidden />
              </div>
            </div>
          </div>

          <div className="ta-card ta-stat-card ta-stat-card-emerald">
            <p className="ta-stat-label">{t('kioskPageLastHeartbeat')}</p>
            <div className="mt-2">
              <HeartbeatIndicator kiosk={heartbeatKiosk} />
            </div>
          </div>

          <div className="ta-card ta-stat-card ta-stat-card-amber">
            <div className="flex items-start justify-between gap-3">
              <div>
                <p className="ta-stat-label">{t('kioskPageLocation')}</p>
                <p className="mt-2 font-semibold text-[var(--ink)]">
                  {kiosk.location_label ?? '—'}
                </p>
              </div>
              <div className="ta-stat-icon">
                <MapPin className="h-5 w-5" aria-hidden />
              </div>
            </div>
          </div>
        </div>

        <div className="mt-6">
          <DetailsCard
            title={t('kioskPageDeviceDetails')}
            items={[
              {
                label: t('kioskPageDeviceCode'),
                value: <span className="font-mono text-sm">{kiosk.device_code}</span>,
              },
              {
                label: t('kioskPageConfirmationRequired'),
                value: kiosk.confirmation_required
                  ? t('yes')
                  : t('no'),
              },
              {
                label: t('kioskPageKioskId'),
                value: <span className="font-mono text-sm">{String(kiosk.id)}</span>,
              },
              {
                label: t('kioskPageLocation'),
                value: kiosk.location_label ?? '—',
              },
            ]}
          />
        </div>

        <section className="mt-8 space-y-3">
          {kiosk.recent_checkins.length === 0 ? (
            <EmptyState
              title={t('kioskPageNoCheckIns')}
              detail={t('kioskPageNoCheckInsDescription')}
            />
          ) : (
            <DataTable
              title={t('kioskPageRecentCheckIns')}
              rows={kiosk.recent_checkins as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'result',
                  header: t('kioskPageResult'),
                  render: (row) => <StatusBadge status={String(row.result)} />,
                },
                {
                  key: 'reason',
                  header: t('reason'),
                  render: (row) => (
                    <span className="font-mono text-xs text-[var(--muted)]">
                      {row.reason ? String(row.reason) : '—'}
                    </span>
                  ),
                },
                {
                  key: 'scanned_at',
                  header: t('time'),
                  render: (row) => formatDateTime(
                    row.scanned_at ? String(row.scanned_at) : null,
                    locale,
                  ),
                },
              ]}
            />
          )}
        </section>

        <section className="mt-8 space-y-3">
          {kiosk.recent_print_jobs.length === 0 ? (
            <EmptyState
              title={t('kioskPageNoPrintJobs')}
              detail={t('kioskPageNoPrintJobsDescription')}
            />
          ) : (
            <DataTable
              title={t('kioskPageRecentPrintJobs')}
              rows={kiosk.recent_print_jobs as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'status',
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'is_reprint',
                  header: t('kioskPageReprint'),
                  render: (row) => (
                    row.is_reprint
                      ? <StatusBadge status="reissued" label={t('yes')} />
                      : t('no')
                  ),
                },
                {
                  key: 'reprint_reason',
                  header: t('kioskPageReprintReason'),
                  render: (row) => (row.reprint_reason ? String(row.reprint_reason) : '—'),
                },
                {
                  key: 'printed_at',
                  header: t('kioskPagePrintedAt'),
                  render: (row) => formatDateTime(
                    row.printed_at ? String(row.printed_at) : null,
                    locale,
                  ),
                },
              ]}
            />
          )}
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
