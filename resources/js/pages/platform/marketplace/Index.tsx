import LocalizedLink from '@/components/routing/LocalizedLink'
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
import type { PlatformMarketplaceRow } from '@/types/phase6'

type Props = {
  rows?: PlatformMarketplaceRow[]
}

import { useState } from 'react'

export default function PlatformMarketplaceIndex({ rows = [] }: Props) {
  const { t } = useLocale()
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const selected = rows.find((row) => row.id === selectedId) ?? null

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
            selectedRowKey={selectedId}
            onRowClick={(row) => setSelectedId(String(row.id))}
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
            ]}
          />
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? `${selected.kind} #${selected.id}` : ''}
        onClose={() => setSelectedId(null)}
        footer={selected?.kind === 'dispute' ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/platform/marketplace/disputes/${selected.id}`}
              className={sideDetailActionClassName('primary')}
            >
              {t('platformDisputeDetails')}
            </LocalizedLink>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              { label: t('assetType'), value: selected.kind },
              { label: t('venueStatus'), value: <StatusBadge status={selected.status} /> },
              { label: t('roleOwner'), value: selected.owner_name || '—' },
              { label: t('roleOrganizer'), value: selected.organizer_name || '—' },
              { label: t('venues'), value: selected.venue_name || '—' },
              { label: t('events'), value: selected.event_name || '—' },
              { label: t('updatedAt'), value: selected.opened_at || '—' },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
