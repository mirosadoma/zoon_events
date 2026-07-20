import { usePage } from '@inertiajs/react'
import { localizedPath, type AppLocale } from '@/lib/localePath'
import ar from '@/locales/ar'
import en from '@/locales/en'

export type Locale = AppLocale

type Messages = typeof en

function interpolate(template: string, params?: Record<string, string | number>): string {
  if (!params) {
    return template
  }

  return Object.entries(params).reduce((result, [key, value]) => {
    const stringValue = String(value)
    return result
      .replaceAll(`:${key}`, stringValue)
      .replaceAll(`{${key}}`, stringValue)
  }, template)
}

export function useLocale() {
  const props = usePage().props as { locale?: Locale; direction?: 'ltr' | 'rtl' }
  const locale = props.locale || 'en'
  const direction = props.direction || (locale === 'ar' ? 'rtl' : 'ltr')
  const messages: Messages = locale === 'ar' ? ar : en

  function t(
    key: keyof Omit<Messages, 'statusLabels'> | string,
    params?: Record<string, string | number>,
  ): string {
    const value = messages[key as keyof Messages]
    const template = typeof value === 'string' ? value : String(key)
    return interpolate(template, params)
  }

  return { locale, direction, t, localizedPath: (path: string) => localizedPath(locale, path) }
}
