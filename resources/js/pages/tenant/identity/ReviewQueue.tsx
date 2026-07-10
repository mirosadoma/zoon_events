import { router } from '@inertiajs/react'
import { useState } from 'react'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type ReviewItem = {
  id: string
  attendee_id: string
  method: string
  status: string
  provider_reference?: string | null
  submitted_at?: string | null
}

type Props = {
  tenantId: string
  event: EventRow
  items: ReviewItem[]
  canReview: boolean
}

export default function IdentityReviewQueuePage({ tenantId, event, items, canReview }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [busyId, setBusyId] = useState<string | null>(null)
  const [approveId, setApproveId] = useState<string | null>(null)
  const [rejectId, setRejectId] = useState<string | null>(null)

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

  async function submitDecision(verificationId: string, decision: 'approve' | 'reject', reason?: string) {
    setBusyId(verificationId)
    try {
      const response = await fetch(
        `/api/v1/tenant/events/${event.id}/identity/verifications/${verificationId}/review`,
        {
          method: 'POST',
          credentials: 'include',
          headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
          body: JSON.stringify({ decision, reason: reason ?? null }),
        },
      )
      const body = await response.json().catch(() => ({}))
      if (!response.ok) {
        toast(extractError(body, t('identityReviewFailed')), 'error')
        return
      }
      toast(
        decision === 'approve' ? t('identityReviewApprovedToast') : t('identityReviewRejectedToast'),
        'success',
      )
      router.reload({ only: ['items'] })
    } catch {
      toast(t('identityReviewFailed'), 'error')
    } finally {
      setBusyId(null)
      setApproveId(null)
      setRejectId(null)
    }
  }

  return (
    <DashboardLayout title={t('identityReviewQueue')}>
      <PageHeader
        title={t('identityReviewQueue')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('identityReviewQueue') },
        ]}
      />
      <PageContent>
        {items.length === 0 ? (
          <p className="text-sm text-slate-600">{t('emptyState')}</p>
        ) : (
          <ul className="space-y-4">
            {items.map((item) => (
              <li key={item.id} className="state-panel">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p className="font-medium">{item.attendee_id.slice(-8)}</p>
                    <p className="text-sm text-slate-600">{item.method}</p>
                    <StatusBadge status={item.status} />
                  </div>
                  <PermissionGate permission="identity.review">
                    {canReview ? (
                      <div className="flex gap-2">
                        <button
                          type="button"
                          className="button-primary"
                          disabled={busyId !== null}
                          onClick={() => setApproveId(item.id)}
                        >
                          {t('identityReviewApprove')}
                        </button>
                        <button
                          type="button"
                          className="button-secondary"
                          disabled={busyId !== null}
                          onClick={() => setRejectId(item.id)}
                        >
                          {t('identityReviewReject')}
                        </button>
                      </div>
                    ) : null}
                  </PermissionGate>
                </div>
              </li>
            ))}
          </ul>
        )}

        <ConfirmModal
          open={approveId !== null}
          title={t('identityReviewApprove')}
          message={t('identityReviewApproveConfirm')}
          confirmLabel={t('confirm')}
          cancelLabel={t('cancel')}
          loading={busyId !== null}
          onCancel={() => setApproveId(null)}
          onConfirm={() => approveId && void submitDecision(approveId, 'approve')}
        />

        <ReasonModal
          open={rejectId !== null}
          title={t('identityReviewReject')}
          message={t('identityReviewRejectConfirm')}
          reasonLabel={t('reasonRequired')}
          confirmLabel={t('identityReviewReject')}
          cancelLabel={t('cancel')}
          loading={busyId !== null}
          onCancel={() => setRejectId(null)}
          onConfirm={(reason) => rejectId && void submitDecision(rejectId, 'reject', reason)}
        />
      </PageContent>
    </DashboardLayout>
  )
}
