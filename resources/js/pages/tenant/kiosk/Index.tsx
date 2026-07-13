import LocalizedLink from '@/components/routing/LocalizedLink'
import { useCallback, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { HeartbeatIndicator } from '@/components/kiosk/HeartbeatIndicator'
import { HealthTable } from '@/components/kiosk/HealthTable'
import { PairingDialog } from '@/components/kiosk/PairingDialog'
import { EmptyState } from '@/components/feedback'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
  kiosks: Kiosk[]
}

export default function KioskIndex({ event, tenantId, kiosks: initialKiosks }: Props) {
  const { locale, t } = useLocale()
  const [kiosks, setKiosks] = useState(initialKiosks)
  const [selectedKiosk, setSelectedKiosk] = useState<Kiosk | null>(null)
  const [retireTarget, setRetireTarget] = useState<Kiosk | null>(null)
  const [pairingSecret, setPairingSecret] = useState<string | null>(null)

  const apiHeaders = useCallback(() => ({
    Accept: 'application/json',
    'X-Tenant-ID': tenantId,
  }), [tenantId])

  async function handlePair(kioskId: string) {
    const response = await fetch(`/api/v1/tenant/events/${event.id}/kiosks/${kioskId}/pair`, {
      method: 'POST',
      credentials: 'include',
      headers: { ...apiHeaders(), 'Idempotency-Key': crypto.randomUUID() },
    })
    const body = await response.json()
    setSelectedKiosk(null)
    if (response.ok) {
      setPairingSecret(body.data?.session_secret ?? null)
    }
  }

  async function handleRetire() {
    if (!retireTarget) return

    await fetch(`/api/v1/tenant/events/${event.id}/kiosks/${retireTarget.id}/retire`, {
      method: 'POST',
      credentials: 'include',
      headers: { ...apiHeaders(), 'Idempotency-Key': crypto.randomUUID() },
    })
    setKiosks((prev) => prev.map((k) => (k.id === retireTarget.id ? { ...k, status: 'retired' } : k)))
    setRetireTarget(null)
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'إدارة الكشك' : 'Kiosk management'}>
      <PageHeader
        title={locale === 'ar' ? 'إدارة الكشك' : 'Kiosk management'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الكشكات' : 'Kiosks' },
        ]}
      />
      <PageContent>
        {kiosks.length === 0 ? (
          <EmptyState title={locale === 'ar' ? 'لا توجد كشكات مسجلة' : 'No kiosks registered for this event'} />
        ) : (
          <ul className="space-y-3">
            {kiosks.map((kiosk) => (
              <li key={kiosk.id} className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 p-4 dark:border-slate-700">
                <LocalizedLink className="font-medium hover:underline" href={`/tenant/events/${event.id}/kiosks/${kiosk.id}`}>
                  {kiosk.device_name}
                </LocalizedLink>
                <HeartbeatIndicator kiosk={kiosk} />
                <StatusBadge status={kiosk.status} />
                <span className="text-sm text-slate-500">{kiosk.device_code}</span>
                <PermissionGate permission="kiosk.manage">
                  <button type="button" className="button-secondary" onClick={() => setSelectedKiosk(kiosk)}>
                    {locale === 'ar' ? 'إقران' : 'Pair'}
                  </button>
                  {kiosk.status !== 'retired' && (
                    <button type="button" className="button-secondary" onClick={() => setRetireTarget(kiosk)}>
                      {locale === 'ar' ? 'إيقاف' : 'Retire'}
                    </button>
                  )}
                </PermissionGate>
              </li>
            ))}
          </ul>
        )}

        <section className="mt-8">
          <h2 className="text-lg font-semibold">{locale === 'ar' ? 'الصحة المباشرة' : 'Live health'}</h2>
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
        title={locale === 'ar' ? 'إيقاف الكشك' : 'Retire kiosk'}
        message={locale === 'ar' ? 'هل أنت متأكد من إيقاف هذا الكشك؟' : 'Are you sure you want to retire this kiosk?'}
        confirmLabel={locale === 'ar' ? 'إيقاف' : 'Retire'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        onConfirm={handleRetire}
        onCancel={() => setRetireTarget(null)}
      />

      {pairingSecret && (
        <ConfirmModal
          open
          title={locale === 'ar' ? 'تم الإقران' : 'Kiosk paired'}
          message={`${locale === 'ar' ? 'رمز الجلسة:' : 'Session secret:'} ${pairingSecret}`}
          confirmLabel={locale === 'ar' ? 'تم' : 'Done'}
          cancelLabel={locale === 'ar' ? 'إغلاق' : 'Close'}
          onConfirm={() => setPairingSecret(null)}
          onCancel={() => setPairingSecret(null)}
        />
      )}
    </DashboardLayout>
  )
}
