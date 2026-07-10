import { useState } from 'react'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ReasonModal from '@/components/modals/ReasonModal'
import StatusBadge from '@/components/status/StatusBadge'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type ArtifactRow = {
  id: string
  artifact_type: string
  liveness_result?: string | null
  retention_until: string
  purged_at?: string | null
}

type DetailPayload = {
  verification: {
    id: string
    attendee_id: string
    method: string
    status: string
    verified_at?: string | null
    rejection_reason?: string | null
    retention_until?: string | null
  }
  artifacts: ArtifactRow[]
  consent: {
    notice_version: string
    residency_mode: string
    consented_at?: string | null
  } | null
  residency: {
    mode: string
    cross_border_transfer: boolean
  }
}

type Props = {
  tenantId: string
  event: EventRow
  verificationId: string
  attendeeId: string
  detail: DetailPayload
  canManage: boolean
}

export default function IdentityVerificationDetailPage({
  tenantId,
  event,
  verificationId,
  attendeeId,
  detail,
  canManage,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const { toast } = useToast()
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [busy, setBusy] = useState(false)

  const apiHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-ID': tenantId,
  }

  function extractError(body: unknown, fallback: string): string {
    if (typeof body !== 'object' || body === null) {
      return fallback
    }
    const maybe = body as { detail?: string; message?: string; title?: string; code?: string }
    return maybe.detail ?? maybe.message ?? maybe.title ?? maybe.code ?? fallback
  }

  async function handleDelete(reason: string) {
    setBusy(true)
    try {
      const response = await fetch(
        `/api/v1/tenant/events/${event.id}/attendees/${attendeeId}/identity/data`,
        {
          method: 'DELETE',
          credentials: 'include',
          headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
          body: JSON.stringify({ reason }),
        },
      )
      const body = await response.json().catch(() => ({}))
      if (!response.ok) {
        toast(extractError(body, t('identityDataDeleteFailed')), 'error')
        return
      }
      toast(t('identityDataDeletedToast'), 'success')
      localizedRouter.visit(`/tenant/events/${event.id}/identity/review`)
    } catch {
      toast(t('identityDataDeleteFailed'), 'error')
    } finally {
      setBusy(false)
      setDeleteOpen(false)
    }
  }

  const verificationItems = [
    { label: t('attendees'), value: attendeeId.slice(-8) },
    { label: t('identityVerifyStatus'), value: <StatusBadge status={detail.verification.status} /> },
    { label: t('identityRequirementLevel'), value: detail.verification.method },
  ]

  if (detail.verification.verified_at) {
    verificationItems.push({ label: t('verifiedAt'), value: detail.verification.verified_at })
  }
  if (detail.verification.rejection_reason) {
    verificationItems.push({ label: t('reasonRequired'), value: detail.verification.rejection_reason })
  }

  return (
    <DashboardLayout title={t('identityVerificationDetail')}>
      <PageHeader
        title={t('identityVerificationDetail')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('identityReviewQueue'), href: `/tenant/events/${event.id}/identity/review` },
          { label: verificationId.slice(-8) },
        ]}
      />
      <PageContent>
        <DetailsCard title={t('identityVerifyStatus')} items={verificationItems} />

        {detail.consent ? (
          <DetailsCard
            title={t('identityComplianceConsentTitle')}
            items={[
              { label: t('identityResidencyMode'), value: detail.consent.residency_mode },
              { label: t('identityConsentVersion'), value: detail.consent.notice_version },
            ]}
          />
        ) : null}

        <section className="state-panel">
          <h2 className="text-lg font-semibold">{t('identityArtifactsTitle')}</h2>
          {detail.artifacts.length === 0 ? (
            <p className="mt-4 text-sm text-slate-600">{t('emptyState')}</p>
          ) : (
            <ul className="mt-4 space-y-3 text-sm">
              {detail.artifacts.map((artifact) => (
                <li key={artifact.id} className="rounded border border-slate-200 p-3">
                  <p className="font-medium">{artifact.artifact_type}</p>
                  <p className="text-slate-600">
                    {t('identityRetentionUntil')}: {artifact.retention_until}
                  </p>
                  {artifact.purged_at ? (
                    <p className="text-slate-600">
                      {t('identityPurgedAt')}: {artifact.purged_at}
                    </p>
                  ) : null}
                </li>
              ))}
            </ul>
          )}
        </section>

        <DetailsCard
          title={t('identityResidencyTitle')}
          items={[
            { label: t('identityResidencyMode'), value: detail.residency.mode },
            {
              label: t('identityCrossBorderTransfer'),
              value: detail.residency.cross_border_transfer ? t('yes') : t('no'),
            },
          ]}
        />

        <PermissionGate permission="identity.data.manage">
          {canManage ? (
            <button type="button" className="button-danger" onClick={() => setDeleteOpen(true)}>
              {t('identityDataDelete')}
            </button>
          ) : null}
        </PermissionGate>

        <ReasonModal
          open={deleteOpen}
          title={t('identityDataDelete')}
          message={t('identityDataDeleteConfirm')}
          reasonLabel={t('reasonRequired')}
          confirmLabel={t('identityDataDelete')}
          cancelLabel={t('cancel')}
          loading={busy}
          onCancel={() => setDeleteOpen(false)}
          onConfirm={(reason) => void handleDelete(reason)}
        />
      </PageContent>
    </DashboardLayout>
  )
}
