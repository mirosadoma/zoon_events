import { usePage } from '@inertiajs/react'
import ar from '@/locales/ar'
import en from '@/locales/en'

export type Locale = 'en' | 'ar'

type Messages = typeof en

export function useLocale() {
  const props = usePage().props as { locale?: Locale; direction?: 'ltr' | 'rtl' }
  const locale = props.locale || 'en'
  const direction = props.direction || (locale === 'ar' ? 'rtl' : 'ltr')
  const messages: Messages = locale === 'ar' ? ar : en

  function t(key: keyof Messages | string): string {
    return (messages as Record<string, string>)[key] ?? String(key)
  }

  return { locale, direction, t }
}
