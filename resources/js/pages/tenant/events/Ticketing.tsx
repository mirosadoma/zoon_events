import { Link } from '@inertiajs/react'
import { InventoryStatus } from '@/components/ticketing/InventoryStatus'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { formatMoney } from '@/lib/formatMoney'

type Ticket = {
  id: string
  code: string
  name: { en: string; ar: string }
  price_minor: number
  currency: string
  remaining_quantity: number
  state: 'available' | 'sold_out' | 'paused' | 'conflict'
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
  }
  tickets: Ticket[]
}

export default function Ticketing({ event, tickets }: Props) {
  const { locale } = useLocale()

  return (
    <DashboardLayout title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}>
      <PageHeader
        title={locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types' },
        ]}
        actions={<Link className="button-secondary" href={`/tenant/events/${event.id}/price-tiers`}>{locale === 'ar' ? 'شرائح الأسعار' : 'Price tiers'}</Link>}
      />
      <PageContent>
        {tickets.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد أنواع تذاكر' : 'No ticket types yet'}
            detail={locale === 'ar' ? 'أنشئ نوع تذكرة لبدء التسعير.' : 'Create a ticket type to start pricing.'}
          />
        ) : (
          <ul className="space-y-3">
            {tickets.map((ticket) => (
              <li key={ticket.id} className="state-panel">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold">{ticket.name[locale]}</h2>
                    <p className="text-sm text-slate-600">{ticket.code}</p>
                  </div>
                  <InventoryStatus state={ticket.state} locale={locale} />
                </div>
                <p className="mt-3">{formatMoney(ticket.price_minor, ticket.currency, locale)}</p>
                <p className="text-sm text-slate-600">
                  {locale === 'ar' ? 'المتبقي' : 'Remaining'}: {ticket.remaining_quantity}
                </p>
              </li>
            ))}
          </ul>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
