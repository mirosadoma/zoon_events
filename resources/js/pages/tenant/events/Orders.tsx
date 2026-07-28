import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import { router } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import { NotificationStatus } from '@/components/orders/NotificationStatus'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import Pagination from '@/components/tables/Pagination'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'
import { ArrowUpRight } from 'lucide-react'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type OrderRow = {
  id: string
  reference: string
  buyer_name?: string | null
  status: string
  total: string
  notification_status?: string | null
}

type Filters = {
  search: string
  status: string
}

type Props = {
  event: EventRow
  orders: OrderRow[]
  filters?: Filters
  pagination?: PaginationMeta
}

const ORDER_STATUSES = ['draft', 'pending_payment', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded']

export default function Orders({
  event,
  orders,
  filters = { search: '', status: '' },
  pagination = defaultPagination,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [search, setSearch] = useState(filters.search)
  const [statusFilter, setStatusFilter] = useState(filters.status)

  const selected = orders.find((order) => order.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToView() {
    if (!selectedId) return
    router.visit(localizedPath(`/tenant/events/${event.id}/orders/${selectedId}`))
  }

  function queryParams(overrides: Partial<Filters & { page?: number }> = {}): Record<string, string> {
    const nextSearch = overrides.search ?? search
    const nextStatus = overrides.status ?? statusFilter
    const nextPage = overrides.page ?? pagination.page
    const query: Record<string, string> = {}

    if (nextSearch.trim() !== '') query.search = nextSearch.trim()
    if (nextStatus !== '') query.status = nextStatus

    return withPage(query, nextPage)
  }

  function applyFilters(overrides: Partial<Filters & { page?: number }> = {}) {
    localizedRouter.get(`/tenant/events/${event.id}/orders`, queryParams(overrides), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  function submitFilters(eventForm: FormEvent) {
    eventForm.preventDefault()
    applyFilters({ page: 1 })
  }

  const statusOptions = [
    { value: '', label: t('allStatuses') },
    ...ORDER_STATUSES.map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={t('orders')}>
      <PageHeader
        title={t('orders')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('orders') },
        ]}
      />
      <PageContent>
        <form onSubmit={submitFilters}>
          <FiltersBar>
            <SearchInput
              value={search}
              onChange={setSearch}
              label={t('ordersSearchLabel')}
              placeholder="ORD-…"
            />
            <SelectInput
              label={t('orderStatus')}
              name="status"
              value={statusFilter}
              onChange={(changeEvent) => {
                const nextStatus = changeEvent.target.value
                setStatusFilter(nextStatus)
                applyFilters({ status: nextStatus, page: 1 })
              }}
              options={statusOptions}
            />
            <button type="submit" className="button-primary">{t('search')}</button>
          </FiltersBar>
        </form>

        {orders.length === 0 ? (
          <EmptyState title={t('noOrders')} detail={t('noOrdersDetail')} />
        ) : (
          <>
            <DataTable
              rows={orders as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              selectedRowKey={selectedId}
              onRowClick={(row) => setSelectedId(String(row.id))}
              columns={[
                {
                  key: 'buyer_name',
                  header: t('ordersOwner'),
                  render: (row) => {
                    const order = row as unknown as OrderRow

                    return (
                      <LocalizedLink href={`/tenant/events/${event.id}/orders/${order.id}`} className="font-medium text-sky-700 hover:underline">
                        {order.buyer_name ?? order.reference}
                      </LocalizedLink>
                    )
                  },
                },
                {
                  key: 'reference',
                  header: t('ordersReference'),
                  render: (row) => String((row as unknown as OrderRow).reference),
                },
                {
                  key: 'status',
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                { key: 'total', header: t('ordersTotal') },
                {
                  key: 'notification_status',
                  header: t('ordersDelivery'),
                  render: (row) => {
                    const order = row as unknown as OrderRow

                    return order.notification_status
                      ? <NotificationStatus status={order.notification_status} locale={locale} />
                      : '—'
                  },
                },
              ]}
            />
            <Pagination
              page={pagination.page}
              totalPages={pagination.last_page}
              onPageChange={(page) => applyFilters({ page })}
              previousLabel={t('previousPage')}
              nextLabel={t('nextPage')}
              pageLabel={t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
            />
          </>
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? (selected.buyer_name || selected.reference) : ''}
        subtitle={selected?.reference || null}
        onClose={closePane}
        onEdit={goToView}
        editLabel={t('view')}
        footer={selected ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/tenant/events/${event.id}/orders/${selected.id}`}
              className={sideDetailActionClassName('primary')}
            >
              {t('view')}
              <ArrowUpRight className="h-4 w-4" aria-hidden />
            </LocalizedLink>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('ordersOwner'),
                value: selected.buyer_name || notAvailable,
              },
              {
                label: t('ordersReference'),
                value: selected.reference,
              },
              {
                label: t('status'),
                value: <StatusBadge status={selected.status} />,
              },
              {
                label: t('ordersTotal'),
                value: selected.total,
              },
              {
                label: t('ordersDelivery'),
                value: selected.notification_status
                  ? <NotificationStatus status={selected.notification_status} locale={locale} />
                  : '—',
              },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
