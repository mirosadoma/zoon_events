import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import ConfirmModal from '@/components/modals/ConfirmModal'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { PlatformDisputeDetail } from '@/types/phase6'

type Props = {
  dispute?: PlatformDisputeDetail | null
}

export default function PlatformDisputeShow({ dispute = null }: Props) {
  const { locale, t } = useLocale()
  const [confirmAction, setConfirmAction] = useState<'review' | 'resolve' | 'reject' | null>(null)
  const [resolutionCode, setResolutionCode] = useState('')
  const [resolutionSummary, setResolutionSummary] = useState('')

  if (!dispute) {
    return (
      <DashboardLayout title={t('platformDisputeDetails')}>
        <PageContent>
          <EmptyState title={t('noDispute')} detail={t('platformMarketplaceDescription')} />
        </PageContent>
      </DashboardLayout>
    )
  }

  return (
    <DashboardLayout title={t('platformDisputeDetails')}>
      <PageHeader
        title={t('platformDisputeDetails')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('platformMarketplace'), href: '/platform/marketplace' },
          { label: dispute.id },
        ]}
        actions={<StatusBadge status={dispute.status} size="md" />}
      />
      <PageContent>
        <section className="ta-card mb-6 space-y-2" aria-label={t('platformDisputeDetails')}>
          <h2 className="text-lg font-semibold">{t('platformDisputeDetails')}</h2>
          {dispute.owner_display_name ? (
            <p className="text-sm"><span className="text-[var(--muted)]">{t('roleOwner')}: </span>{dispute.owner_display_name}</p>
          ) : null}
          {dispute.organizer_display_name ? (
            <p className="text-sm"><span className="text-[var(--muted)]">{t('roleOrganizer')}: </span>{dispute.organizer_display_name}</p>
          ) : null}
          {dispute.venue_name ? (
            <p className="text-sm"><span className="text-[var(--muted)]">{t('venues')}: </span>{dispute.venue_name[locale]}</p>
          ) : null}
          {dispute.event_name ? (
            <p className="text-sm"><span className="text-[var(--muted)]">{t('events')}: </span>{dispute.event_name[locale]}</p>
          ) : null}
          {dispute.reason ? (
            <p className="text-sm"><span className="text-[var(--muted)]">{t('disputeReason')}: </span>{dispute.reason}</p>
          ) : null}
        </section>

        {dispute.timeline && dispute.timeline.length > 0 ? (
          <section className="ta-card mb-6" aria-label={t('rentalTimeline')}>
            <h2 className="text-lg font-semibold">{t('rentalTimeline')}</h2>
            <ul className="mt-3 space-y-2">
              {dispute.timeline.map((event) => (
                <li key={event.id} className="rounded-xl border border-[var(--border)] px-3 py-2 text-sm">
                  <span className="text-[var(--muted)]">{event.occurred_at}</span>
                  <span className="mx-2">—</span>
                  <span>{event.summary ?? event.kind}</span>
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <PermissionGate permission="platform.marketplace.disputes.manage">
          <section className="ta-card mb-6 space-y-3" aria-label={t('platformNotes')}>
            <h2 className="text-lg font-semibold">{t('platformNotes')}</h2>
            {dispute.platform_notes && dispute.platform_notes.length > 0 ? (
              <ul className="space-y-2 text-sm">
                {dispute.platform_notes.map((note) => (
                  <li key={note.id} className="rounded-xl border border-[var(--border)] px-3 py-2">
                    <span className="text-[var(--muted)]">{note.created_at}</span>
                    <p className="mt-1">{note.body}</p>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-[var(--muted)]">{t('emptyAudit')}</p>
            )}
            <button type="button" className="button-secondary">{t('addPlatformNote')}</button>
          </section>

          <div className="flex flex-wrap gap-2">
            <button type="button" className="button-secondary" onClick={() => setConfirmAction('review')}>
              {t('startReview')}
            </button>
            <button type="button" className="button-primary" onClick={() => setConfirmAction('resolve')}>
              {t('resolveDispute')}
            </button>
            <button type="button" className="button-secondary" onClick={() => setConfirmAction('reject')}>
              {t('rejectDispute')}
            </button>
          </div>
        </PermissionGate>
      </PageContent>

      <ConfirmModal
        open={confirmAction === 'review'}
        title={t('startReview')}
        message={t('confirm')}
        confirmLabel={t('startReview')}
        cancelLabel={t('cancel')}
        onConfirm={() => setConfirmAction(null)}
        onCancel={() => setConfirmAction(null)}
      />

      <ConfirmModal
        open={confirmAction === 'resolve' || confirmAction === 'reject'}
        title={confirmAction === 'resolve' ? t('resolveDispute') : t('rejectDispute')}
        message={t('confirm')}
        confirmLabel={t('confirm')}
        cancelLabel={t('cancel')}
        confirmDisabled={!resolutionCode.trim() || !resolutionSummary.trim()}
        onConfirm={() => setConfirmAction(null)}
        onCancel={() => setConfirmAction(null)}
      >
        <div className="space-y-3">
          <TextInput
            label={t('resolutionCode')}
            name="resolution_code"
            value={resolutionCode}
            onChange={(event) => setResolutionCode(event.target.value)}
            required
          />
          <TextareaInput
            label={t('resolutionSummary')}
            name="resolution_summary"
            value={resolutionSummary}
            onChange={(event) => setResolutionSummary(event.target.value)}
            required
          />
        </div>
      </ConfirmModal>
    </DashboardLayout>
  )
}
