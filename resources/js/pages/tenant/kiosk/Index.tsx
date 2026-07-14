import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import { Link2, Power } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { HeartbeatIndicator } from '@/components/kiosk/HeartbeatIndicator'
import { HealthTable } from '@/components/kiosk/HealthTable'
import { PairingDialog } from '@/components/kiosk/PairingDialog'
import { EmptyState } from '@/components/feedback'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import Pagination from '@/components/tables/Pagination'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { apiFetch } from '@/lib/apiFetch'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'
import type { Kiosk } from '@/types/phase3'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
  kiosks: Kiosk[]
  pagination?: PaginationMeta
}

export default function KioskIndex({
  event,
  tenantId,
  kiosks: initialKiosks,
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const localizedRouter = useLocalizedRouter()
  const [kiosks, setKiosks] = useState(initialKiosks)
  const [selectedKiosk, setSelectedKiosk] = useState<Kiosk | null>(null)
  const [retireTarget, setRetireTarget] = useState<Kiosk | null>(null)
  const [pairingSecret, setPairingSecret] = useState<string | null>(null)

  useEffect(() => {
    setKiosks(initialKiosks)
  }, [initialKiosks])

  function changePage(page: number) {
    localizedRouter.get(`/tenant/events/${event.id}/kiosks`, withPage({}, page), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  async function handlePair(kioskId: string) {
    try {
      const data = await apiFetch<{ session_secret?: string }>(
        `/api/v1/tenant/events/${event.id}/kiosks/${kioskId}/pair`,
        {
          method: 'POST',
          tenantId,
          idempotency: true,
        },
      )
      setPairingSecret(data.session_secret ?? null)
    } finally {
      setSelectedKiosk(null)
    }
  }

  async function handleRetire() {
    if (!retireTarget) return

    await apiFetch(`/api/v1/tenant/events/${event.id}/kiosks/${retireTarget.id}/retire`, {
      method: 'POST',
      tenantId,
      idempotency: true,
    })
    setKiosks((prev) => prev.map((kiosk) => (
      kiosk.id === retireTarget.id ? { ...kiosk, status: 'retired' } : kiosk
    )))
    setRetireTarget(null)
  }

  return (
    <DashboardLayout title={ar ? 'إدارة الكشك' : 'Kiosk management'}>
      <PageHeader
        title={ar ? 'إدارة الكشك' : 'Kiosk management'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: ar ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: ar ? 'الكشكات' : 'Kiosks' },
        ]}
      />
      <PageContent>
        {kiosks.length === 0 ? (
          <EmptyState
            title={ar ? 'لا توجد كشكات مسجلة' : 'No kiosks registered for this event'}
            detail={ar
              ? 'سجّل جهازاً ثم أقرنه لإظهاره هنا.'
              : 'Register a device and pair it to see it listed here.'}
          />
        ) : (
          <>
            <DataTable
              title={ar ? 'الكشكات' : 'Kiosks'}
              rows={kiosks as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'device_name',
                  header: ar ? 'الجهاز' : 'Device',
                  render: (row) => {
                    const kiosk = row as unknown as Kiosk
                    return (
                      <LocalizedLink
                        href={`/tenant/events/${event.id}/kiosks/${kiosk.id}`}
                        className="font-medium text-[var(--brand)] hover:underline"
                      >
                        {kiosk.device_name}
                      </LocalizedLink>
                    )
                  },
                },
                {
                  key: 'device_code',
                  header: ar ? 'الرمز' : 'Code',
                  render: (row) => <span className="font-mono text-sm">{String(row.device_code)}</span>,
                },
                {
                  key: 'status',
                  header: ar ? 'الحالة' : 'Status',
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'printer_status',
                  header: ar ? 'الطابعة' : 'Printer',
                  render: (row) => (
                    row.printer_status
                      ? <StatusBadge status={String(row.printer_status)} />
                      : '—'
                  ),
                },
                {
                  key: 'last_heartbeat_at',
                  header: ar ? 'النبض' : 'Heartbeat',
                  render: (row) => <HeartbeatIndicator kiosk={row as unknown as Kiosk} />,
                },
                {
                  key: 'actions',
                  header: ar ? 'إجراءات' : 'Actions',
                  render: (row) => {
                    const kiosk = row as unknown as Kiosk
                    return (
                      <PermissionGate permission="kiosk.manage">
                        <div className="ta-table-actions">
                          <button
                            type="button"
                            className="ta-table-action inline-flex items-center gap-1.5"
                            onClick={() => setSelectedKiosk(kiosk)}
                          >
                            <Link2 className="h-3.5 w-3.5" aria-hidden />
                            {ar ? 'إقران' : 'Pair'}
                          </button>
                          {kiosk.status !== 'retired' && (
                            <button
                              type="button"
                              className="ta-table-action inline-flex items-center gap-1.5"
                              onClick={() => setRetireTarget(kiosk)}
                            >
                              <Power className="h-3.5 w-3.5" aria-hidden />
                              {ar ? 'إيقاف' : 'Retire'}
                            </button>
                          )}
                        </div>
                      </PermissionGate>
                    )
                  },
                },
              ]}
            />
            <Pagination
              page={pagination.page}
              totalPages={pagination.last_page}
              onPageChange={changePage}
              previousLabel={t('previousPage')}
              nextLabel={t('nextPage')}
              pageLabel={t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
            />
          </>
        )}

        <section className="mt-8 space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">
              {ar ? 'الصحة المباشرة' : 'Live health'}
            </h2>
            <p className="text-sm text-[var(--muted)]">
              {ar ? 'يتم التحديث تلقائياً كل بضع ثوانٍ.' : 'Updates automatically every few seconds.'}
            </p>
          </div>
          <HealthTable eventId={event.id} tenantId={tenantId} pollIntervalMs={15000} />
        </section>
      </PageContent>

      {selectedKiosk && (
        <PairingDialog
          kiosk={selectedKiosk}
          onConfirm={handlePair}
          onCancel={() => setSelectedKiosk(null)}
        />
      )}

      <ConfirmModal
        open={retireTarget !== null}
        title={ar ? 'إيقاف الكشك' : 'Retire kiosk'}
        message={ar ? 'هل أنت متأكد من إيقاف هذا الكشك؟' : 'Are you sure you want to retire this kiosk?'}
        confirmLabel={ar ? 'إيقاف' : 'Retire'}
        cancelLabel={ar ? 'إلغاء' : 'Cancel'}
        onConfirm={() => void handleRetire()}
        onCancel={() => setRetireTarget(null)}
      />

      <ConfirmModal
        open={pairingSecret !== null}
        title={ar ? 'تم الإقران' : 'Kiosk paired'}
        message={ar
          ? 'انسخ رمز الجلسة وأدخله على الكشك فوراً. لن يظهر مرة أخرى.'
          : 'Copy this session secret into the kiosk now. It will not be shown again.'}
        confirmLabel={ar ? 'تم' : 'Done'}
        cancelLabel={ar ? 'إغلاق' : 'Close'}
        onConfirm={() => setPairingSecret(null)}
        onCancel={() => setPairingSecret(null)}
      >
        {pairingSecret ? (
          <p className="rounded-xl border border-[var(--border)] bg-[var(--surface)] px-3 py-2 font-mono text-sm break-all">
            {pairingSecret}
          </p>
        ) : null}
      </ConfirmModal>
    </DashboardLayout>
  )
}
