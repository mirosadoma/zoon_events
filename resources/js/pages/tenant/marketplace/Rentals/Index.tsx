import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { formatMinorUnits } from '@/lib/marketplaceLabels'
import { defaultPagination, type PaginationMeta } from '@/lib/pagination'
import type { RentalRow } from '@/types/phase6'

type Props = {
  rentals?: RentalRow[]
  pagination?: PaginationMeta
}

export default function RentalsIndex({ rentals = [], pagination = defaultPagination }: Props) {
  const { locale, t } = useLocale()

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
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => (
                  <LocalizedLink href={`/tenant/marketplace/rentals/${String(row.id)}`} className="ta-table-action">
                    {t('rentalDetails')}
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
