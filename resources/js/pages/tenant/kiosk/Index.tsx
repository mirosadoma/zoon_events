import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useEffect, useState } from 'react'
import { Link2, Plus, Power } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { HeartbeatIndicator } from '@/components/kiosk/HeartbeatIndicator'
import { HealthTable } from '@/components/kiosk/HealthTable'
import { PairingDialog } from '@/components/kiosk/PairingDialog'
import { EmptyState } from '@/components/feedback'
import TextInput from '@/components/forms/TextInput'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import Pagination from '@/components/tables/Pagination'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { localizedPath } from '@/lib/localePath'
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
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const [kiosks, setKiosks] = useState(initialKiosks)
  const [selectedKiosk, setSelectedKiosk] = useState<Kiosk | null>(null)
  const [retireTarget, setRetireTarget] = useState<Kiosk | null>(null)
  const [pairingSecret, setPairingSecret] = useState<string | null>(null)
  const [registerOpen, setRegisterOpen] = useState(false)
  const [registering, setRegistering] = useState(false)
  const [deviceName, setDeviceName] = useState('')
  const [locationLabel, setLocationLabel] = useState('')
  const [confirmationRequired, setConfirmationRequired] = useState(false)
  const [confirmationCode, setConfirmationCode] = useState('')
  const [registerError, setRegisterError] = useState<string | null>(null)

  useEffect(() => {
    setKiosks(initialKiosks)
  }, [initialKiosks])

  function changePage(page: number) {
    localizedRouter.get(`/tenant/events/${event.id}/kiosks`, withPage({}, page), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  function resetRegisterForm() {
    setDeviceName('')
    setLocationLabel('')
    setConfirmationRequired(false)
    setConfirmationCode('')
    setRegisterError(null)
  }

  async function handleRegister(formEvent: FormEvent) {
    formEvent.preventDefault()
    setRegistering(true)
    setRegisterError(null)

    try {
      const created = await apiFetch<Kiosk>(`/api/v1/tenant/events/${event.id}/kiosks`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          device_name: deviceName.trim(),
          location_label: locationLabel.trim() || null,
          confirmation_required: confirmationRequired,
          confirmation_code: confirmationRequired ? confirmationCode.trim() : null,
        },
      })

      setKiosks((prev) => [created, ...prev])
      setRegisterOpen(false)
      resetRegisterForm()
      toast(t('kioskPageRegistered'), 'success')
    } catch (error) {
      setRegisterError(error instanceof ApiFetchError ? error.message : t('requestFailed'))
    } finally {
      setRegistering(false)
    }
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
    <DashboardLayout title={t('kioskPageManagement')}>
      <PageHeader
        title={t('kioskPageManagement')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('kioskPageKiosks') },
        ]}
        actions={(
          <PermissionGate permission="kiosk.manage">
            <button
              type="button"
              className="button-primary inline-flex items-center gap-1.5"
              onClick={() => {
                resetRegisterForm()
                setRegisterOpen(true)
              }}
            >
              <Plus className="h-4 w-4" aria-hidden />
              {t('kioskPageAdd')}
            </button>
          </PermissionGate>
        )}
      />
      <PageContent>
        {kiosks.length === 0 ? (
          <EmptyState
            title={t('kioskPageNoKiosks')}
            detail={t('kioskPageNoKiosksDescription')}
            action={(
              <PermissionGate permission="kiosk.manage">
                <button
                  type="button"
                  className="button-primary"
                  onClick={() => {
                    resetRegisterForm()
                    setRegisterOpen(true)
                  }}
                >
                  {t('kioskPageAdd')}
                </button>
              </PermissionGate>
            )}
          />
        ) : (
          <>
            <DataTable
              title={t('kioskPageKiosks')}
              rows={kiosks as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'device_name',
                  header: t('kioskPageDevice'),
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
                  header: t('kioskPageCode'),
                  render: (row) => {
                    const code = String(row.device_code ?? '')
                    if (!code) return '—'

                    return (
                      <a
                        href={localizedPath(locale, `/kiosk/${code}/unlock`)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="font-mono text-sm font-medium text-[var(--brand)] hover:underline"
                      >
                        {code}
                      </a>
                    )
                  },
                },
                {
                  key: 'status',
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'printer_status',
                  header: t('kioskPagePrinter'),
                  render: (row) => (
                    row.printer_status
                      ? <StatusBadge status={String(row.printer_status)} />
                      : '—'
                  ),
                },
                {
                  key: 'last_heartbeat_at',
                  header: t('kioskPageHeartbeat'),
                  render: (row) => <HeartbeatIndicator kiosk={row as unknown as Kiosk} />,
                },
                {
                  key: 'actions',
                  header: t('kioskPageActions'),
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
                            {t('kioskPagePair')}
                          </button>
                          {kiosk.status !== 'retired' && (
                            <button
                              type="button"
                              className="ta-table-action inline-flex items-center gap-1.5"
                              onClick={() => setRetireTarget(kiosk)}
                            >
                              <Power className="h-3.5 w-3.5" aria-hidden />
                              {t('kioskPageRetire')}
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

        <section className="mt-8">
          <h2 className="mb-3 text-base font-semibold text-slate-800 dark:text-slate-100">
            {t('kioskPageLiveHealth')}
          </h2>
          <HealthTable eventId={event.id} tenantId={tenantId} />
        </section>
      </PageContent>

      {selectedKiosk ? (
        <PairingDialog
          kiosk={selectedKiosk}
          onConfirm={(kioskId) => void handlePair(kioskId)}
          onCancel={() => setSelectedKiosk(null)}
        />
      ) : null}

      <ConfirmModal
        open={retireTarget !== null}
        title={t('kioskPageRetireKiosk')}
        message={t('kioskPageRetireConfirm')}
        confirmLabel={t('kioskPageRetire')}
        cancelLabel={t('cancel')}
        onConfirm={() => void handleRetire()}
        onCancel={() => setRetireTarget(null)}
      />

      <ConfirmModal
        open={pairingSecret !== null}
        title={t('kioskPagePaired')}
        message={t('kioskPagePairedMessage')}
        confirmLabel={t('kioskPageDone')}
        cancelLabel={t('kioskPageClose')}
        onConfirm={() => setPairingSecret(null)}
        onCancel={() => setPairingSecret(null)}
      >
        {pairingSecret ? (
          <p className="mt-3 break-all rounded bg-slate-100 p-3 font-mono text-sm dark:bg-slate-800">
            {pairingSecret}
          </p>
        ) : null}
      </ConfirmModal>

      {registerOpen ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="kiosk-register-title">
          <form className="ta-card w-full max-w-md shadow-xl" onSubmit={(event) => void handleRegister(event)}>
            <h2 id="kiosk-register-title" className="text-lg font-semibold">{t('kioskPageAddTitle')}</h2>
            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">{t('kioskPageAddMessage')}</p>

            <div className="mt-4 space-y-3">
              <TextInput
                label={t('kioskPageDeviceName')}
                name="device_name"
                value={deviceName}
                required
                onChange={(event) => setDeviceName(event.target.value)}
                placeholder={t('kioskPageDeviceNamePlaceholder')}
              />
              <TextInput
                label={t('kioskPageLocation')}
                name="location_label"
                value={locationLabel}
                onChange={(event) => setLocationLabel(event.target.value)}
                placeholder={t('kioskPageLocationPlaceholder')}
              />
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={confirmationRequired}
                  onChange={(event) => setConfirmationRequired(event.target.checked)}
                />
                <span>{t('kioskPageConfirmationRequired')}</span>
              </label>
              {confirmationRequired ? (
                <TextInput
                  label={t('kioskPageConfirmationCode')}
                  name="confirmation_code"
                  value={confirmationCode}
                  required
                  onChange={(event) => setConfirmationCode(event.target.value)}
                />
              ) : null}
              {registerError ? <p className="text-sm text-red-600">{registerError}</p> : null}
            </div>

            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                className="button-secondary"
                disabled={registering}
                onClick={() => {
                  setRegisterOpen(false)
                  resetRegisterForm()
                }}
              >
                {t('cancel')}
              </button>
              <button type="submit" className="button-primary" disabled={registering || deviceName.trim() === ''}>
                {registering ? t('kioskPageRegistering') : t('kioskPageAdd')}
              </button>
            </div>
          </form>
        </div>
      ) : null}
    </DashboardLayout>
  )
}
