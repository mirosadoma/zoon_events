import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import BadgePrintPreviewModal from '@/components/badges/BadgePrintPreviewModal'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useTenantId } from '@/hooks/useTenantId'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { openBlankPrintWindow, writeBadgePrintDocument } from '@/lib/openBadgePrintWindow'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialSummary = {
  id: string
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
}

type AttendeeDetail = {
  id: string
  label: string
  display_name?: string | null
  email?: string | null
  phone?: string | null
  status: string
  locale: string
  order_id?: string | null
  ticket_type_id?: string | null
  registered_at?: string | null
  first_checked_in_at?: string | null
  origin?: string | null
  credential?: CredentialSummary | null
}

type IdentityState = {
  status: string
  pending: boolean
  reason_code?: string | null
  requirement_level: string
}

type Props = {
  event: EventRow
  attendee: AttendeeDetail
  tenantId: string
  identity?: IdentityState | null
}

export default function AttendeeDetailPage({ event, attendee, tenantId: pageTenantId, identity }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const tenantId = useTenantId(pageTenantId)
  const [busyAction, setBusyAction] = useState<'revoke' | 'reissue' | 'print' | 'checkin' | null>(null)
  const [printPreviewOpen, setPrintPreviewOpen] = useState(false)

  function extractError(error: unknown, fallback: string): string {
    if (error instanceof ApiFetchError) {
      return error.message
    }

    return fallback
  }

  function ensureTenantId(): boolean {
    if (tenantId) {
      return true
    }

    toast(t('attendeeDetailTenantUnavailable'), 'error')

    return false
  }

  async function handleRevoke(reason: string) {
    if (!attendee.credential || !ensureTenantId()) return false
    setBusyAction('revoke')
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/credentials/${attendee.credential.id}/revoke`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { reason },
      })
      toast(t('attendeeDetailCredentialRevoked'), 'success')
      router.reload({ only: ['attendee'] })
      return true
    } catch (error) {
      toast(extractError(error, t('attendeeDetailCredentialRevokeFailed')), 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  async function handleReissue(reason: string) {
    if (!attendee.credential || !ensureTenantId()) return false
    setBusyAction('reissue')
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/credentials/${attendee.credential.id}/reissue`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { reason },
      })
      toast(t('attendeeDetailCredentialReissued'), 'success')
      router.reload({ only: ['attendee'] })
      return true
    } catch (error) {
      toast(extractError(error, t('attendeeDetailCredentialReissueFailed')), 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  function openPrintPreview() {
    if (!attendee.credential || !ensureTenantId()) return
    setPrintPreviewOpen(true)
  }

  async function handlePrintBadge(overrides: {
    job_title?: string
    custom_text?: string
    company?: string
  }) {
    if (!attendee.credential || !ensureTenantId()) return
    setBusyAction('print')
    const printWindow = openBlankPrintWindow()
    try {
      const job = await apiFetch<{
        id: string
        status: string
        print_html?: string | null
      }>(`/api/v1/tenant/events/${event.id}/badge-print-jobs`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          attendee_id: attendee.id,
          credential_id: attendee.credential.id,
          field_overrides: overrides,
        },
      })

      const opened = writeBadgePrintDocument(printWindow, job.print_html)
      toast(
        opened ? t('attendeeDetailBadgePrintOpened') : t('attendeeDetailBadgeJobCreated'),
        opened ? 'success' : 'info',
      )
      setPrintPreviewOpen(false)
    } catch (error) {
      printWindow?.close()
      toast(extractError(error, t('attendeeDetailBadgeFailed')), 'error')
    } finally {
      setBusyAction(null)
    }
  }

  async function handleManualCheckIn() {
    if (!attendee.credential || !ensureTenantId()) return
    setBusyAction('checkin')
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/scans`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          scanner_type: 'manual_desk',
          credential_id: attendee.credential.id,
          override: false,
        },
      })
      toast(t('attendeeDetailCheckedIn'), 'success')
      router.reload({ only: ['attendee'] })
    } catch (error) {
      toast(extractError(error, t('attendeeDetailCheckinFailed')), 'error')
    } finally {
      setBusyAction(null)
    }
  }

  const notAvailable = t('notAvailable')

  function displayValue(value: string | null | undefined): string {
    return value?.trim() ? value.trim() : notAvailable
  }

  return (
    <DashboardLayout title={attendee.label}>
      <PageHeader
        title={attendee.label}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('attendees'), href: `/tenant/events/${event.id}/attendees` },
          { label: attendee.label },
        ]}
      />
      <PageContent>
        {identity?.pending ? (
          <section className="state-panel mb-6 border-amber-200 bg-amber-50" role="status">
            <div className="flex flex-col gap-3">
              <div className="flex flex-wrap items-center gap-3">
                <StatusBadge status={identity.status} />
                <p className="text-sm text-amber-900">{t('identityPendingIssuanceBanner')}</p>
              </div>
              <p className="text-sm text-amber-900">{t('identityPendingIssuanceHelp')}</p>
              <LocalizedLink
                href={`/tenant/events/${event.id}/identity/review`}
                className="text-sm font-medium text-sky-800 hover:underline"
              >
                {t('openIdentityReviewQueue')}
              </LocalizedLink>
            </div>
          </section>
        ) : null}

        <DetailsCard
          title={t('attendeeDetail')}
          items={[
            { label: t('attendeeName'), value: displayValue(attendee.display_name) },
            { label: t('attendeeEmail'), value: displayValue(attendee.email) },
            { label: t('attendeePhone'), value: displayValue(attendee.phone) },
            { label: t('checkInStatus'), value: <StatusBadge status={attendee.status} /> },
            { label: t('attendeeDetailLocale'), value: attendee.locale },
            { label: t('attendeeDetailOrigin'), value: attendee.origin ?? '—' },
            { label: t('attendeeDetailRegistered'), value: attendee.registered_at ?? '—' },
            { label: t('attendeeDetailFirstCheckIn'), value: attendee.first_checked_in_at ?? '—' },
            // {
            //   label: t('attendeeDetailOrder'),
            //   value: attendee.order_id
            //     ? (
            //       <LocalizedLink href={`/tenant/events/${event.id}/orders/${String(attendee.order_id)}`} className="text-sky-700 hover:underline">
            //         {String(attendee.order_id).slice(-8)}
            //       </LocalizedLink>
            //     )
            //     : '—',
            // },
          ]}
        />

        {/* {attendee.credential && (
          <section className="state-panel mt-6">
            <h2 className="text-lg font-semibold">{t('attendeeDetailCredential')}</h2>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{t('attendeeDetailCredentialStatus')}</dt>
                <dd className="mt-1"><StatusBadge status={attendee.credential.status} /></dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{t('attendeeDetailCredentialCode')}</dt>
                <dd className="mt-1">
                  <LocalizedLink href={`/tenant/events/${event.id}/credentials/${String(attendee.credential.id)}`} className="text-sky-700 hover:underline">
                    {String(attendee.credential.id).slice(-8)}
                  </LocalizedLink>
                </dd>
              </div>
            </dl>
          </section>
        )} */}

        {attendee.credential && (
          <section className="state-panel mt-6">
            <h2 className="text-lg font-semibold">{t('attendeeActions')}</h2>
            <div className="mt-4 flex flex-wrap gap-2">
              <PermissionGate permission="badge.print">
                <button type="button" className="button-secondary" onClick={openPrintPreview} disabled={busyAction !== null}>
                  {t('printBadge')}
                </button>
              </PermissionGate>
              <PermissionGate permission="checkin.desk.perform">
                <button type="button" className="button-secondary" onClick={() => void handleManualCheckIn()} disabled={busyAction !== null}>
                  {t('manualCheckIn')}
                </button>
              </PermissionGate>
            </div>
            <dl className="mt-4 grid gap-4 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{t('reissueCredential')}</dt>
                <dd className="mt-1 text-sm text-slate-700">{t('attendeeActionReissueHelp')}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{t('printBadge')}</dt>
                <dd className="mt-1 text-sm text-slate-700">{t('attendeeActionPrintBadgeHelp')}</dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{t('manualCheckIn')}</dt>
                <dd className="mt-1 text-sm text-slate-700">{t('attendeeActionManualCheckInHelp')}</dd>
              </div>
            </dl>
          </section>
        )}

        {attendee.credential && (
          <CredentialDialog
            status={attendee.credential.status}
            loading={busyAction !== null}
            onRevoked={handleRevoke}
            onReissued={handleReissue}
          />
        )}

        {attendee.credential && tenantId ? (
          <BadgePrintPreviewModal
            open={printPreviewOpen}
            eventId={event.id}
            tenantId={tenantId}
            attendeeId={attendee.id}
            credentialId={attendee.credential.id}
            attendeeName={attendee.display_name ?? attendee.label}
            loading={busyAction === 'print'}
            onCancel={() => setPrintPreviewOpen(false)}
            onConfirm={(result) => void handlePrintBadge(result.overrides)}
          />
        ) : null}
      </PageContent>
    </DashboardLayout>
  )
}
