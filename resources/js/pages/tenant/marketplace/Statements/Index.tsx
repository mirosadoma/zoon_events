import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { formatMinorUnits } from '@/lib/marketplaceLabels'
import { defaultPagination, type PaginationMeta } from '@/lib/pagination'
import type { StatementRow } from '@/types/phase6'

type Props = {
  statements?: StatementRow[]
  pagination?: PaginationMeta
}

export default function StatementsIndex({ statements = [], pagination = defaultPagination }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={t('statementsTitle')}>
      <PageHeader
        title={t('statementsTitle')}
        description={t('statementsDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('marketplace'), href: '/tenant/marketplace' },
          { label: t('marketplaceStatements') },
        ]}
      />
      <PageContent>
        {statements.length === 0 ? (
          <EmptyState title={t('noStatements')} detail={t('noStatementsDetail')} />
        ) : (
          <DataTable
            title={t('statementsTitle')}
            rows={statements as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'revision',
                header: t('statementRevision'),
                render: (row) => String(row.revision),
              },
              {
                key: 'issued_at',
                header: t('updatedAt'),
                render: (row) => String(row.issued_at),
              },
              {
                key: 'status',
                header: t('venueStatus'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'total_minor',
                header: t('quoteTotal'),
                render: (row) => {
                  const statement = row as unknown as StatementRow
                  return formatMinorUnits(statement.total_minor, statement.currency, locale)
                },
              },
              {
                key: 'dispute_status',
                header: t('disputePanel'),
                render: (row) => {
                  const status = (row as unknown as StatementRow).dispute_status
                  return status && status !== 'none' ? <StatusBadge status={status} /> : '—'
                },
              },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => (
                  <LocalizedLink href={`/tenant/marketplace/statements/${String(row.id)}`} className="ta-table-action">
                    {t('statementDetails')}
                  </LocalizedLink>
                ),
              },
            ]}
          />
        )}

        {pagination.last_page > 1 ? (
          <p className="mt-4 text-sm text-[var(--muted)]">
            {t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
          </p>
        ) : null}
      </PageContent>
    </DashboardLayout>
  )
}
