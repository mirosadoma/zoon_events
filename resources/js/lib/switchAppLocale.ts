import { swapLocaleInPath, type AppLocale } from '@/lib/localePath'

const LOCALE_COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365

/** Switch UI locale and reload so singleton loaders (e.g. Google Maps) re-init cleanly. */
export function switchAppLocale(from: AppLocale): void {
  const next: AppLocale = from === 'ar' ? 'en' : 'ar'
  const currentPath = `${window.location.pathname}${window.location.search}`

  document.cookie = `locale=${next};path=/;max-age=${LOCALE_COOKIE_MAX_AGE_SECONDS};SameSite=Lax`
  window.location.assign(swapLocaleInPath(currentPath, next))
}
