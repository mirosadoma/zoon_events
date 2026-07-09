import LocalizedLink from '@/components/routing/LocalizedLink'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { NotificationStatus } from '@/components/orders/NotificationStatus'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import SearchInput from '@/components/tables/SearchInput'
import SelectInput from '@/components/forms/SelectInput'
import { useLocale } from '@/hooks/useLocale'

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

type Props = {
  event: EventRow
  orders: OrderRow[]
}

export default function Orders({ event, orders }: Props) {
  const { locale } = useLocale()
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('')

  const filtered = useMemo(() => orders.filter((order) => {
    const haystack = `${order.reference} ${order.buyer_name ?? ''}`.toLowerCase()
    const matchesSearch = search.trim() === '' || haystack.includes(search.trim().toLowerCase())
    const matchesStatus = statusFilter === '' || order.status === statusFilter

    return matchesSearch && matchesStatus
  }), [orders, search, statusFilter])

  const statusOptions = [
    { value: '', label: locale === 'ar' ? 'كل الحالات' : 'All statuses' },
    ...Array.from(new Set(orders.map((order) => order.status))).map((status) => ({ value: status, label: status })),
  ]

  return (
    <DashboardLayout title={locale === 'ar' ? 'الطلبات' : 'Orders'}>
      <PageHeader
        title={locale === 'ar' ? 'الطلبات' : 'Orders'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الطلبات' : 'Orders' },
        ]}
      />
      <PageContent>
        <FiltersBar>
          <SearchInput
            value={search}
            onChange={setSearch}
            label={locale === 'ar' ? 'بحث بالاسم أو المرجع' : 'Search name or reference'}
            placeholder={locale === 'ar' ? 'ORD-…' : 'ORD-…'}
          />
          <SelectInput
            label={locale === 'ar' ? 'حالة الطلب' : 'Order status'}
            name="status"
            value={statusFilter}
            onChange={(event) => setStatusFilter(event.target.value)}
            options={statusOptions}
          />
        </FiltersBar>

        {filtered.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد طلبات' : 'No orders yet'}
            detail={locale === 'ar' ? 'ستظهر الطلبات هنا بعد التسجيل.' : 'Orders will appear here after registration.'}
          />
        ) : (
          <DataTable
            rows={filtered as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'buyer_name',
                header: locale === 'ar' ? 'صاحب الطلب' : 'Order owner',
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
                header: locale === 'ar' ? 'المرجع' : 'Reference',
                render: (row) => String((row as unknown as OrderRow).reference),
              },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              { key: 'total', header: locale === 'ar' ? 'الإجمالي' : 'Total' },
              {
                key: 'notification_status',
                header: locale === 'ar' ? 'التسليم' : 'Delivery',
                render: (row) => {
                  const order = row as unknown as OrderRow

                  return order.notification_status
                    ? <NotificationStatus status={order.notification_status} locale={locale} />
                    : '—'
                },
              },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
