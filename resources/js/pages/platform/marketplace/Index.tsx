import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import type { PlatformMarketplaceRow } from '@/types/phase6'

type Props = {
  rows?: PlatformMarketplaceRow[]
}

export default function PlatformMarketplaceIndex({ rows = [] }: Props) {
  const { t } = useLocale()

  return (
    <DashboardLayout title={t('platformMarketplace')}>
      <PageHeader
        title={t('platformMarketplace')}
        description={t('platformMarketplaceDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('navGroupPlatform'), href: '/platform/tenants' },
          { label: t('platformMarketplace') },
        ]}
      />
      <PageContent>
        {rows.length === 0 ? (
          <EmptyState title={t('emptyState')} detail={t('platformMarketplaceDescription')} />
        ) : (
          <DataTable
            title={t('platformMarketplace')}
            rows={rows as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'kind', header: t('assetType') },
              {
                key: 'status',
                header: t('venueStatus'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              { key: 'owner_name', header: t('roleOwner') },
              { key: 'organizer_name', header: t('roleOrganizer') },
              { key: 'venue_name', header: t('venues') },
              { key: 'event_name', header: t('events') },
              { key: 'opened_at', header: t('updatedAt') },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => {
                  const item = row as unknown as PlatformMarketplaceRow
                  if (item.kind === 'dispute') {
                    return (
                      <LocalizedLink href={`/platform/marketplace/disputes/${item.id}`} className="ta-table-action">
                        {t('platformDisputeDetails')}
                      </LocalizedLink>
                    )
                  }
                  return '—'
                },
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
