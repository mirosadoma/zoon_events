import { usePage } from '@inertiajs/react'

export type Locale = 'en' | 'ar'

export function useLocale() {
  const props = usePage().props as { locale?: Locale; direction?: 'ltr' | 'rtl' }
  const locale = props.locale || 'en'
  const direction = props.direction || (locale === 'ar' ? 'rtl' : 'ltr')

  return { locale, direction }
}
