export type AppLocale = 'en' | 'ar'

export function stripLocalePrefix(path: string): string {
  const normalized = path.split('?')[0] ?? path

  return normalized.replace(/^\/(en|ar)(?=\/|$)/, '') || '/'
}

export function localizedPath(locale: AppLocale, path: string): string {
  if (/^https?:\/\//i.test(path)) {
    return path
  }

  const queryIndex = path.indexOf('?')
  const query = queryIndex >= 0 ? path.slice(queryIndex) : ''
  const base = queryIndex >= 0 ? path.slice(0, queryIndex) : path
  const normalized = base.startsWith('/') ? base : `/${base}`
  const withoutLocale = stripLocalePrefix(normalized)

  if (withoutLocale === '/') {
    return `/${locale}${query}`
  }

  return `/${locale}${withoutLocale}${query}`
}

export function swapLocaleInPath(path: string, nextLocale: AppLocale): string {
  const query = path.includes('?') ? path.slice(path.indexOf('?')) : ''
  const base = stripLocalePrefix(path.split('?')[0] ?? path)

  return `${localizedPath(nextLocale, base)}${query}`
}
