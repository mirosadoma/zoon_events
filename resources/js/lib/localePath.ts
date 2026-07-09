export type AppLocale = 'en' | 'ar'

export function stripLocalePrefix(path: string): string {
  const normalized = path.split('?')[0] ?? path

  return normalized.replace(/^\/(en|ar)(?=\/|$)/, '') || '/'
}

export function localizedPath(locale: AppLocale, path: string): string {
  if (/^https?:\/\//i.test(path)) {
    return path
  }

  const normalized = path.startsWith('/') ? path : `/${path}`
  const withoutLocale = stripLocalePrefix(normalized)

  if (withoutLocale === '/') {
    return `/${locale}`
  }

  return `/${locale}${withoutLocale}`
}

export function swapLocaleInPath(path: string, nextLocale: AppLocale): string {
  const query = path.includes('?') ? path.slice(path.indexOf('?')) : ''
  const base = stripLocalePrefix(path.split('?')[0] ?? path)

  return `${localizedPath(nextLocale, base)}${query}`
}
