import { NotificationStatus } from '@/components/orders/NotificationStatus'

export default function Orders({ orders, locale = 'en' }: { orders: Array<{ id: string; reference: string; status: string; total: string; notification_status?: string }>; locale?: 'en' | 'ar' }) {
  const labels = locale === 'ar'
    ? { title: 'الطلبات', reference: 'المرجع', status: 'الحالة', total: 'الإجمالي', delivery: 'التسليم' }
    : { title: 'Orders', reference: 'Reference', status: 'Status', total: 'Total', delivery: 'Delivery' }
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{labels.title}</h1>
      <table>
        <thead><tr><th>{labels.reference}</th><th>{labels.status}</th><th>{labels.total}</th><th>{labels.delivery}</th></tr></thead>
        <tbody>{orders.map((order) => (
          <tr key={order.id}>
            <td>{order.reference}</td><td>{order.status}</td><td>{order.total}</td>
            <td>{order.notification_status && <NotificationStatus status={order.notification_status} locale={locale} />}</td>
          </tr>
        ))}</tbody>
      </table>
    </main>
  )
}
