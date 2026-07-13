import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { NotificationStatus } from '@/components/orders/NotificationStatus'
import StatusBadge from '@/components/status/StatusBadge'
import { checkinStatusLabel } from '@/lib/scanLabels'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type OrderItem = {
  id: string
  quantity: number
  total_minor: number
  currency: string
  ticket_name: Record<string, string>
  attendee_id?: string | null
}

type OrderDetail = {
  id: string
  reference: string
  buyer_name?: string | null
  status: string
  total: string
  currency: string
  notification_status?: string | null
  paid_at?: string | null
  created_at?: string | null
  items: OrderItem[]
  attendees: Array<{ id: string; label: string; checkin_status?: string | null }>
}

type Props = {
  event: EventRow
  order: OrderDetail
}

export default function OrderDetailPage({ event, order }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={order.buyer_name ?? order.reference}>
      <PageHeader
        title={order.buyer_name ?? order.reference}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الطلبات' : 'Orders', href: `/tenant/events/${event.id}/orders` },
          { label: order.buyer_name ?? order.reference },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'ملخص الطلب' : 'Order summary'}
          items={[
            { label: locale === 'ar' ? 'صاحب الطلب' : 'Order owner', value: order.buyer_name ?? '—' },
            { label: locale === 'ar' ? 'المرجع' : 'Reference', value: order.reference },
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={order.status} /> },
            { label: locale === 'ar' ? 'الإجمالي' : 'Total', value: order.total },
            { label: locale === 'ar' ? 'العملة' : 'Currency', value: order.currency },
            { label: locale === 'ar' ? 'تاريخ الدفع' : 'Paid at', value: order.paid_at ?? '—' },
            {
              label: locale === 'ar' ? 'التسليم' : 'Delivery',
              value: order.notification_status
                ? <NotificationStatus status={order.notification_status} locale={locale} />
                : '—',
            },
            { label: locale === 'ar' ? 'تاريخ الإنشاء' : 'Created', value: order.created_at ?? '—' },
          ]}
        />

        <section className="state-panel mt-6">
          <h2 className="text-lg font-semibold">{locale === 'ar' ? 'البنود' : 'Items'}</h2>
          <ul className="mt-4 space-y-2">
            {order.items.map((item) => (
              <li key={item.id} className="rounded-lg border border-slate-200 p-3 dark:border-slate-800">
                {item.ticket_name?.en ?? item.id} · {item.quantity} × {(item.total_minor / 100).toFixed(2)} {item.currency}
              </li>
            ))}
          </ul>
        </section>

        <section className="state-panel mt-6">
          <h2 className="text-lg font-semibold">{locale === 'ar' ? 'الحضور المرتبطون' : 'Linked attendees'}</h2>
          <ul className="mt-4 space-y-2">
            {order.attendees.length === 0 ? (
              <li>{locale === 'ar' ? 'لا يوجد حضور مرتبط.' : 'No linked attendees.'}</li>
            ) : order.attendees.map((attendee) => (
              <li key={attendee.id}>
                <LocalizedLink href={`/tenant/events/${event.id}/attendees/${attendee.id}`} className="text-sky-700 hover:underline">
                  {attendee.label}
                </LocalizedLink>
                {attendee.checkin_status ? ` · ${checkinStatusLabel(attendee.checkin_status, locale)}` : ''}
              </li>
            ))}
          </ul>
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
