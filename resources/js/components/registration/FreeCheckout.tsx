import { useState } from 'react'

type Props = {
  locale: 'en' | 'ar'
  onSubmit: (values: { email: string; marketing: boolean }) => void
}

export function FreeCheckout({ locale, onSubmit }: Props) {
  const [email, setEmail] = useState('')
  const [marketing, setMarketing] = useState(false)
  const rtl = locale === 'ar'

  return (
    <form
      lang={locale}
      dir={rtl ? 'rtl' : 'ltr'}
      aria-label={rtl ? 'إتمام التسجيل المجاني' : 'Complete free registration'}
      onSubmit={(event) => {
        event.preventDefault()
        if (email) onSubmit({ email, marketing })
      }}
    >
      <label>
        {rtl ? 'البريد الإلكتروني' : 'Email'}
        <input type="email" required value={email} onChange={(event) => setEmail(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={marketing} onChange={(event) => setMarketing(event.target.checked)} />
        {rtl ? 'أوافق على الرسائل التسويقية الاختيارية' : 'Optional marketing messages'}
      </label>
      <button type="submit">{rtl ? 'تسجيل' : 'Register'}</button>
    </form>
  )
}
