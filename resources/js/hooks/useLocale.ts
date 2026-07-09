import { usePage } from '@inertiajs/react'
import { localizedPath, swapLocaleInPath, type AppLocale } from '@/lib/localePath'
import ar from '@/locales/ar'
import en from '@/locales/en'

export type Locale = AppLocale

type Messages = typeof en

export function useLocale() {
  const props = usePage().props as { locale?: Locale; direction?: 'ltr' | 'rtl' }
  const locale = props.locale || 'en'
  const direction = props.direction || (locale === 'ar' ? 'rtl' : 'ltr')
  const messages: Messages = locale === 'ar' ? ar : en

  function t(key: keyof Omit<Messages, 'statusLabels'> | string): string {
    const value = messages[key as keyof Messages]
    return typeof value === 'string' ? value : String(key)
  }

  return { locale, direction, t, localizedPath: (path: string) => localizedPath(locale, path) }
}
