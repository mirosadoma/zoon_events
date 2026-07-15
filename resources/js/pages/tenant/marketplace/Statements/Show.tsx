import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { assetTypeLabelKey, formatMinorUnits } from '@/lib/marketplaceLabels'
import type { RentalLine, StatementDetail } from '@/types/phase6'
import DisputePanel from '../Components/DisputePanel'
import DecisionReasonDialog from '../Components/DecisionReasonDialog'

type Props = {
  statement?: StatementDetail | null
  tenantId?: string
}

export default function StatementShow({ statement = null }: Props) {
  const { locale, t } = useLocale()
  const [disputeOpen, setDisputeOpen] = useState(false)

  if (!statement) {
    return (
      <DashboardLayout title={t('statementDetails')}>
        <PageContent>
          <EmptyState title={t('noStatements')} detail={t('noStatementsDetail')} />
        </PageContent>
      </DashboardLayout>
    )
  }

  const lines = statement.lines ?? []

  return (
    <DashboardLayout title={t('statementDetails')}>
      <PageHeader
        title={t('statementDetails')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('marketplace'), href: '/tenant/marketplace' },
          { label: t('marketplaceStatements'), href: '/tenant/marketplace/statements' },
          { label: `${t('statementRevision')} ${statement.revision}` },
        ]}
        actions={
          <div className="flex flex-wrap gap-2">
            <StatusBadge status={statement.status} size="md" />
            <a
              href={`/api/v1/tenant/marketplace/statements/${statement.id}/export`}
              className="button-secondary"
            >
              {t('exportStatementCsv')}
            </a>
          </div>
        }
      />
      <PageContent>
        <div className="ta-card mb-6 space-y-2" role="note">
          <p className="text-sm font-medium text-[var(--ink)]">{t('statementOnlyNotice')}</p>
          <p className="text-sm text-[var(--muted)]">{statement.issued_at}</p>
          {statement.window_start && statement.window_end ? (
            <p className="text-sm">
              {t('requestedWindow')}: {statement.window_start} — {statement.window_end}
            </p>
          ) : null}
        </div>

        {lines.length > 0 ? (
          <DataTable
            title={t('statementsTitle')}
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
        ) : null}

        <div className="mt-6">
          <p className="text-lg font-semibold text-[var(--ink)]">
            {t('quoteTotal')}: {formatMinorUnits(statement.total_minor, statement.currency, locale)}
          </p>
        </div>

        {statement.revisions && statement.revisions.length > 1 ? (
          <section className="mt-6 ta-card">
            <h2 className="text-lg font-semibold">{t('statementRevision')}</h2>
            <ul className="mt-2 space-y-1 text-sm">
              {statement.revisions.map((revision) => (
                <li key={revision.id}>
                  {revision.revision} — <StatusBadge status={revision.status} /> — {revision.issued_at}
                </li>
              ))}
            </ul>
          </section>
        ) : null}

        <div className="mt-6">
          <DisputePanel
            dispute={statement.dispute}
            canOpen={!statement.dispute}
            onOpenDispute={() => setDisputeOpen(true)}
          />
        </div>
      </PageContent>

      <DecisionReasonDialog
        open={disputeOpen}
        kind="dispute"
        onConfirm={() => setDisputeOpen(false)}
        onCancel={() => setDisputeOpen(false)}
      />
    </DashboardLayout>
  )
}
