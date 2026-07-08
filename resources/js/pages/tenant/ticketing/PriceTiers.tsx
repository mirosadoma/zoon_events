import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { formatMoney } from '@/lib/formatMoney'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type PriceTierRow = {
  id: string
  name: string
  ticket_type_id: string
  price_minor: number
  currency: string
  starts_at?: string | null
  ends_at?: string | null
  remaining_at_most?: number | null
  priority: number
  status: string
  is_active_now: boolean
}

type Props = {
  event: EventRow
  priceTiers: PriceTierRow[]
}

export default function PriceTiers({ event, priceTiers }: Props) {
  const { locale } = useLocale()

  return (
    <DashboardLayout title={locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}>
      <PageHeader
        title={locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers' },
        ]}
      />
      <PageContent>
        {priceTiers.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد شرائح أسعار' : 'No price tiers yet'}
            detail={locale === 'ar' ? 'أضف شرائح الأسعار بعد إنشاء أنواع التذاكر.' : 'Add price tiers after creating ticket types.'}
          />
        ) : (
          <DataTable
            rows={priceTiers as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'name', header: locale === 'ar' ? 'الاسم' : 'Name' },
              { key: 'ticket_type_id', header: locale === 'ar' ? 'نوع التذكرة' : 'Ticket type' },
              {
                key: 'price_minor',
                header: locale === 'ar' ? 'السعر' : 'Price',
                render: (row) => formatMoney(Number(row.price_minor), String(row.currency), locale),
              },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'is_active_now',
                header: locale === 'ar' ? 'نشط الآن' : 'Active now',
                render: (row) => <StatusBadge status={row.is_active_now ? 'active' : 'inactive'} label={row.is_active_now ? (locale === 'ar' ? 'نعم' : 'Yes') : (locale === 'ar' ? 'لا' : 'No')} />,
              },
              { key: 'priority', header: locale === 'ar' ? 'الأولوية' : 'Priority' },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
