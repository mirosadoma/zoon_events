import { usePage } from '@inertiajs/react'
import { useEffect } from 'react'
import { useSiteBranding } from '@/hooks/useSiteBranding'

type LocalePageProps = {
  locale?: 'en' | 'ar'
  direction?: 'ltr' | 'rtl'
}

export default function LocaleDocumentSync() {
  const { locale, direction } = usePage().props as LocalePageProps
  const { faviconUrl, appNameEn } = useSiteBranding()

  useEffect(() => {
    if (!locale) {
      return
    }

    const resolvedDirection = direction ?? (locale === 'ar' ? 'rtl' : 'ltr')
    document.documentElement.lang = locale
    document.documentElement.dir = resolvedDirection
    document.title = document.title || appNameEn
  }, [locale, direction, appNameEn])

  useEffect(() => {
    const href = faviconUrl ?? '/favicon.ico'
    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')

    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }

    if (link.href !== href) {
      link.href = href
    }
  }, [faviconUrl])

  return null
}
