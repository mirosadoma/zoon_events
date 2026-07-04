export default function Confirmation({
  locale,
  reference,
}: {
  locale: 'en' | 'ar'
  reference: string
}) {
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'اكتمل التسجيل' : 'Registration complete'}</h1>
      <p>{locale === 'ar' ? 'مرجع الطلب' : 'Order reference'}: {reference}</p>
    </main>
  )
}
