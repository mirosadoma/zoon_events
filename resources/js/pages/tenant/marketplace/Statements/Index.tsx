import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import { router } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { formatMinorUnits } from '@/lib/marketplaceLabels'
import { defaultPagination, type PaginationMeta } from '@/lib/pagination'
import type { StatementRow } from '@/types/phase6'
import { ArrowUpRight } from 'lucide-react'

type Props = {
  statements?: StatementRow[]
  pagination?: PaginationMeta
}

export default function StatementsIndex({ statements = [], pagination = defaultPagination }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const selected = statements.find((statement) => statement.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToView() {
    if (!selectedId) return
    router.visit(localizedPath(`/tenant/marketplace/statements/${selectedId}`))
  }

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
            selectedRowKey={selectedId}
            onRowClick={(row) => setSelectedId(String(row.id))}
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
            ]}
          />
        )}

        {pagination.last_page > 1 ? (
          <p className="mt-4 text-sm text-[var(--muted)]">
            {t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
          </p>
        ) : null}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? `${t('statementRevision')} ${selected.revision}` : ''}
        subtitle={selected ? String(selected.issued_at) : null}
        onClose={closePane}
        onEdit={goToView}
        editLabel={t('statementDetails')}
        footer={selected ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/tenant/marketplace/statements/${selected.id}`}
              className={sideDetailActionClassName('primary')}
            >
              {t('statementDetails')}
              <ArrowUpRight className="h-4 w-4" aria-hidden />
            </LocalizedLink>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('statementRevision'),
                value: String(selected.revision),
              },
              {
                label: t('updatedAt'),
                value: String(selected.issued_at),
              },
              {
                label: t('venueStatus'),
                value: <StatusBadge status={selected.status} />,
              },
              {
                label: t('quoteTotal'),
                value: formatMinorUnits(selected.total_minor, selected.currency, locale),
              },
              {
                label: t('disputePanel'),
                value: selected.dispute_status && selected.dispute_status !== 'none'
                  ? <StatusBadge status={selected.dispute_status} />
                  : '—',
              },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
