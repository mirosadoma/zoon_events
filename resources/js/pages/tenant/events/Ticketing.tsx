import { InventoryStatus } from '@/components/ticketing/InventoryStatus'
import { formatMoney } from '@/lib/formatMoney'

type Ticket = {
  id: string
  name: { en: string; ar: string }
  price_minor: number
  currency: string
  remaining_quantity: number
  state: 'available' | 'sold_out' | 'paused' | 'conflict'
}

export default function Ticketing({
  locale,
  tickets,
}: {
  locale: 'en' | 'ar'
  tickets: Ticket[]
}) {
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'التذاكر والأسعار' : 'Tickets and pricing'}</h1>
      <ul>
        {tickets.map((ticket) => (
          <li key={ticket.id}>
            <h2>{ticket.name[locale]}</h2>
            <p>{formatMoney(ticket.price_minor, ticket.currency, locale)}</p>
            <InventoryStatus state={ticket.state} locale={locale} />
          </li>
        ))}
      </ul>
    </main>
  )
}
