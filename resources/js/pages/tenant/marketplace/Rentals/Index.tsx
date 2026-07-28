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
import type { RentalRow } from '@/types/phase6'
import { ArrowUpRight } from 'lucide-react'

type Props = {
  rentals?: RentalRow[]
  pagination?: PaginationMeta
}

export default function RentalsIndex({ rentals = [], pagination = defaultPagination }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const selected = rentals.find((rental) => rental.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToView() {
    if (!selectedId) return
    router.visit(localizedPath(`/tenant/marketplace/rentals/${selectedId}`))
  }

  return (
    <DashboardLayout title={t('myRentals')}>
      <PageHeader
        title={t('myRentals')}
        description={t('myRentalsDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('marketplace'), href: '/tenant/marketplace' },
          { label: t('marketplaceRentals') },
        ]}
      />
      <PageContent>
        {rentals.length === 0 ? (
          <EmptyState title={t('noRentals')} detail={t('noRentalsDetail')} />
        ) : (
          <DataTable
            title={t('myRentals')}
            rows={rentals as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            selectedRowKey={selectedId}
            onRowClick={(row) => setSelectedId(String(row.id))}
            columns={[
              {
                key: 'viewer_role',
                header: t('viewerRole'),
                render: (row) => ((row as unknown as RentalRow).viewer_role === 'owner' ? t('roleOwner') : t('roleOrganizer')),
              },
              {
                key: 'event_name',
                header: t('events'),
                render: (row) => (row as unknown as RentalRow).event_name[locale],
              },
              {
                key: 'venue_name',
                header: t('venues'),
                render: (row) => (row as unknown as RentalRow).venue_name[locale],
              },
              {
                key: 'window',
                header: t('requestedWindow'),
                render: (row) => {
                  const rental = row as unknown as RentalRow
                  return `${rental.window_start} — ${rental.window_end}`
                },
              },
              {
                key: 'total_minor',
                header: t('quoteTotal'),
                render: (row) => {
                  const rental = row as unknown as RentalRow
                  return formatMinorUnits(rental.total_minor, rental.currency, locale)
                },
              },
              {
                key: 'status',
                header: t('venueStatus'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'delegation_status',
                header: t('delegationStatus'),
                render: (row) => {
                  const status = (row as unknown as RentalRow).delegation_status
                  return status ? <StatusBadge status={status} /> : '—'
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
        title={selected ? selected.event_name[locale] : ''}
        subtitle={selected ? selected.venue_name[locale] : null}
        onClose={closePane}
        onEdit={goToView}
        editLabel={t('rentalDetails')}
        footer={selected ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/tenant/marketplace/rentals/${selected.id}`}
              className={sideDetailActionClassName('primary')}
            >
              {t('rentalDetails')}
              <ArrowUpRight className="h-4 w-4" aria-hidden />
            </LocalizedLink>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('viewerRole'),
                value: selected.viewer_role === 'owner' ? t('roleOwner') : t('roleOrganizer'),
              },
              {
                label: t('events'),
                value: selected.event_name[locale],
              },
              {
                label: t('venues'),
                value: selected.venue_name[locale],
              },
              {
                label: t('requestedWindow'),
                value: `${selected.window_start} — ${selected.window_end}`,
              },
              {
                label: t('quoteTotal'),
                value: formatMinorUnits(selected.total_minor, selected.currency, locale),
              },
              {
                label: t('venueStatus'),
                value: <StatusBadge status={selected.status} />,
              },
              {
                label: t('delegationStatus'),
                value: selected.delegation_status ? <StatusBadge status={selected.delegation_status} /> : '—',
              },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
