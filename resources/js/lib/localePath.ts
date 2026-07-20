export type AppLocale = 'en' | 'ar'

export function stripLocalePrefix(path: string): string {
  const normalized = path.split('?')[0]?.split('#')[0] ?? path

  return normalized.replace(/^\/(en|ar)(?=\/|$)/, '') || '/'
}

export function localizedPath(locale: AppLocale, path: string): string {
  if (/^https?:\/\//i.test(path)) {
    return path
  }

  const hashIndex = path.indexOf('#')
  const hash = hashIndex >= 0 ? path.slice(hashIndex) : ''
  const withoutHash = hashIndex >= 0 ? path.slice(0, hashIndex) : path

  const queryIndex = withoutHash.indexOf('?')
  const query = queryIndex >= 0 ? withoutHash.slice(queryIndex) : ''
  const base = queryIndex >= 0 ? withoutHash.slice(0, queryIndex) : withoutHash
  const normalized = base.startsWith('/') ? base : `/${base}`
  const withoutLocale = stripLocalePrefix(normalized)

  if (withoutLocale === '/') {
    return `/${locale}${query}${hash}`
  }

  return `/${locale}${withoutLocale}${query}${hash}`
}

export function swapLocaleInPath(path: string, nextLocale: AppLocale): string {
  const hashIndex = path.indexOf('#')
  const hash = hashIndex >= 0 ? path.slice(hashIndex) : ''
  const withoutHash = hashIndex >= 0 ? path.slice(0, hashIndex) : path
  const query = withoutHash.includes('?') ? withoutHash.slice(withoutHash.indexOf('?')) : ''
  const base = stripLocalePrefix(withoutHash.split('?')[0] ?? withoutHash)

  return `${localizedPath(nextLocale, base)}${query}${hash}`
}
