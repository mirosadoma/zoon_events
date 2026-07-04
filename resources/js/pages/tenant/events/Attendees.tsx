export default function Attendees({ attendees, locale = 'en' }: { attendees: Array<{ id: string; status: string; locale: string }>; locale?: 'en' | 'ar' }) {
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'الحضور' : 'Attendees'}</h1>
      <ul>{attendees.map((attendee) => (
        <li key={attendee.id}>{attendee.id} · {attendee.status} · {attendee.locale}</li>
      ))}</ul>
    </main>
  )
}
