import { useState, useCallback } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { assetTypeLabelKey, formatMinorUnits } from '@/lib/marketplaceLabels'
import type { RentalDetail, RentalLine } from '@/types/phase6'
import RentalDecisionActions from '../Components/RentalDecisionActions'
import DecisionReasonDialog from '../Components/DecisionReasonDialog'
import DelegationStatusPanel from '../Components/DelegationStatusPanel'
import OperationalResourceLinks from '../Components/OperationalResourceLinks'

type Props = {
  rental?: RentalDetail | null
  rentalPublicId?: string
  can?: Record<string, boolean>
  tenantId?: string
}

export default function RentalShow({ rental = null, rentalPublicId, can = {}, tenantId }: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [reasonDialog, setReasonDialog] = useState<'reject' | 'revoke' | null>(null)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const publicId = rentalPublicId ?? rental?.public_id
  const rentalVersion = rental?.version

  const handleDecision = useCallback(async (action: string, body: Record<string, unknown> = {}) => {
    if (!publicId) return
    setBusy(true)
    setError(null)
    try {
      await apiFetch(`/api/v1/tenant/marketplace/rentals/${publicId}/${action}`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { ...body, expected_version: rentalVersion },
      })
      localizedRouter.visit(`/tenant/marketplace/rentals/${publicId}`, { preserveScroll: true })
    } catch (err) {
      setError(err instanceof ApiFetchError ? err.message : t('requestFailed'))
    } finally {
      setBusy(false)
    }
  }, [publicId, tenantId, rentalVersion, localizedRouter, t])

  if (!rental) {
    return (
      <DashboardLayout title={t('rentalDetails')}>
        <PageContent>
          <EmptyState title={t('noRentals')} detail={t('noRentalsDetail')} />
        </PageContent>
      </DashboardLayout>
    )
  }

  const lines = rental.lines ?? []
  const timeline = rental.timeline ?? []

  return (
    <DashboardLayout title={t('rentalDetails')}>
      <PageHeader
        title={t('rentalDetails')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('marketplace'), href: '/tenant/marketplace' },
          { label: t('marketplaceRentals'), href: '/tenant/marketplace/rentals' },
          { label: rental.event_name[locale] },
        ]}
        actions={
          <div className="flex flex-wrap items-center gap-2">
            <StatusBadge status={rental.status} size="md" />
            <span className="text-sm text-[var(--muted)]">
              {t('viewerRole')}: {rental.viewer_role === 'owner' ? t('roleOwner') : t('roleOrganizer')}
            </span>
          </div>
        }
      />
      <PageContent>
        {error ? (
          <p className="mb-4 rounded-xl bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{error}</p>
        ) : null}

        <div className="ta-card mb-6 grid gap-2 text-sm md:grid-cols-2">
          <p><span className="text-[var(--muted)]">{t('events')}: </span>{rental.event_name[locale]}</p>
          <p><span className="text-[var(--muted)]">{t('venues')}: </span>{rental.venue_name[locale]}</p>
          <p><span className="text-[var(--muted)]">{t('requestedWindow')}: </span>{rental.window_start} — {rental.window_end}</p>
          {rental.venue_timezone ? (
            <p><span className="text-[var(--muted)]">{t('venueTimezone')}: </span>{rental.venue_timezone}</p>
          ) : null}
          <p>
            <span className="text-[var(--muted)]">{t('quoteTotal')}: </span>
            {formatMinorUnits(rental.total_minor, rental.currency, locale)}
          </p>
        </div>

        <RentalDecisionActions
          status={rental.status}
          viewerRole={rental.viewer_role}
          busy={busy}
          onApprove={() => handleDecision('approve')}
          onReject={() => setReasonDialog('reject')}
          onRevoke={() => setReasonDialog('revoke')}
          onCancel={() => handleDecision('cancel')}
        />

        {lines.length > 0 ? (
          <div className="mt-6">
            <DataTable
              title={t('rentalQuote')}
              rows={lines as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'asset_name',
                  header: t('venueName'),
                  render: (row) => (row as unknown as RentalLine).asset_name[locale],
                },
                {
                  key: 'asset_type',
                  header: t('assetType'),
                  render: (row) => t(assetTypeLabelKey((row as unknown as RentalLine).asset_type)),
                },
                {
                  key: 'line_total_minor',
                  header: t('lineTotal'),
                  render: (row) => {
                    const line = row as unknown as RentalLine
                    return formatMinorUnits(line.line_total_minor, line.currency, locale)
                  },
                },
              ]}
            />
          </div>
        ) : null}

        <div className="mt-6 space-y-6">
          <section className="ta-card" aria-label={t('rentalTimeline')}>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{t('rentalTimeline')}</h2>
            {timeline.length === 0 ? (
              <p className="mt-2 text-sm text-[var(--muted)]">{t('emptyAudit')}</p>
            ) : (
              <ul className="mt-3 space-y-2">
                {timeline.map((event) => (
                  <li key={event.id} className="rounded-xl border border-[var(--border)] px-3 py-2 text-sm">
                    <span className="text-[var(--muted)]">{event.occurred_at}</span>
                    <span className="mx-2">—</span>
                    <span>{event.summary ?? event.kind}</span>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <DelegationStatusPanel delegation={rental.delegation} />
          <OperationalResourceLinks links={rental.operational_links ?? []} can={can} />
        </div>
      </PageContent>

      <DecisionReasonDialog
        open={reasonDialog !== null}
        kind={reasonDialog ?? 'reject'}
        loading={busy}
        onConfirm={(reason) => {
          const action = reasonDialog === 'reject' ? 'reject' : 'revoke'
          setReasonDialog(null)
          handleDecision(action, { reason })
        }}
        onCancel={() => setReasonDialog(null)}
      />
    </DashboardLayout>
  )
}
