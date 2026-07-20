import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { defaultPagination, type PaginationMeta } from '@/lib/pagination'
import type { VenueRow } from '@/types/phase6'

type Filters = {
  status?: string
  country_code?: string
  city_code?: string
  publication_readiness?: string
}

type Props = {
  venues?: VenueRow[]
  filters?: Filters
  pagination?: PaginationMeta
  tenantId?: string
}

export default function VenuesIndex({
  venues = [],
  filters = {},
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [localFilters, setLocalFilters] = useState({
    status: filters.status ?? '',
    country_code: filters.country_code ?? '',
    city_code: filters.city_code ?? '',
  })

  const hasFilters = Boolean(localFilters.status || localFilters.country_code || localFilters.city_code)
  const filteredEmpty = venues.length === 0 && hasFilters

  function applyFilters() {
    localizedRouter.get('/tenant/venues', localFilters, { preserveState: true, preserveScroll: true })
  }

  return (
    <DashboardLayout title={t('venueManagement')}>
      <PageHeader
        title={t('venueManagement')}
        description={t('venueManagementDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('venues') },
        ]}
        actions={
          <PermissionGate permission="venue.manage">
            <LocalizedLink className="button-primary" href="/tenant/venues/create">
              {t('createVenue')}
            </LocalizedLink>
          </PermissionGate>
        }
      />
      <PageContent>
        <div className="mb-4 grid gap-3 md:grid-cols-4">
          <SelectInput
            label={t('venueStatus')}
            name="status"
            value={localFilters.status}
            onChange={(event) => setLocalFilters({ ...localFilters, status: event.target.value })}
            options={[
              { value: '', label: t('allStatuses') },
              { value: 'draft', label: 'draft' },
              { value: 'active', label: 'active' },
              { value: 'suspended', label: 'suspended' },
              { value: 'archived', label: 'archived' },
            ]}
          />
          <SelectInput
            label={t('filterCountry')}
            name="country_code"
            value={localFilters.country_code}
            onChange={(event) => setLocalFilters({ ...localFilters, country_code: event.target.value })}
            options={[{ value: '', label: t('allTypes') }]}
          />
          <SelectInput
            label={t('filterCity')}
            name="city_code"
            value={localFilters.city_code}
            onChange={(event) => setLocalFilters({ ...localFilters, city_code: event.target.value })}
            options={[{ value: '', label: t('allTypes') }]}
          />
          <div className="flex items-end gap-2">
            <button type="button" className="button-primary" onClick={applyFilters}>
              {t('applyFilters')}
            </button>
            <button
              type="button"
              className="button-secondary"
              onClick={() => {
                setLocalFilters({ status: '', country_code: '', city_code: '' })
                localizedRouter.get('/tenant/venues', {}, { preserveState: true })
              }}
            >
              {t('clearFilters')}
            </button>
          </div>
        </div>

        {venues.length === 0 ? (
          <EmptyState
            title={filteredEmpty ? t('noVenuesFiltered') : t('noVenues')}
            detail={filteredEmpty ? t('noVenuesFilteredDetail') : t('noVenuesDetail')}
            action={
              !filteredEmpty ? (
                <PermissionGate permission="venue.manage">
                  <LocalizedLink className="button-primary" href="/tenant/venues/create">
                    {t('createVenue')}
                  </LocalizedLink>
                </PermissionGate>
              ) : undefined
            }
          />
        ) : (
          <DataTable
            title={t('venues')}
            rows={venues as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'name',
                header: t('venueName'),
                render: (row) => {
                  const venue = row as unknown as VenueRow
                  return (
                    <LocalizedLink
                      href={`/tenant/venues/${venue.id}`}
                      className="font-medium text-[var(--brand)] hover:underline"
                    >
                      {venue.name[locale]}
                    </LocalizedLink>
                  )
                },
              },
              {
                key: 'location',
                header: t('venueLocation'),
                render: (row) => {
                  const venue = row as unknown as VenueRow
                  return [venue.city_code, venue.country_code].filter(Boolean).join(', ') || '—'
                },
              },
              {
                key: 'status',
                header: t('venueStatus'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'active_asset_count',
                header: t('activeAssets'),
                render: (row) => String(row.active_asset_count ?? 0),
              },
              {
                key: 'published_asset_count',
                header: t('publishedAssets'),
                render: (row) => String(row.published_asset_count ?? 0),
              },
              {
                key: 'future_reservation_warning',
                header: t('futureReservationWarning'),
                render: (row) => (row.future_reservation_warning ? t('yes') : t('no')),
              },
              {
                key: 'updated_at',
                header: t('updatedAt'),
                render: (row) => String(row.updated_at ?? '—'),
              },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => (
                  <LocalizedLink href={`/tenant/venues/${String(row.id)}`} className="ta-table-action">
                    {t('viewVenue')}
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
