export type ResolveSiteHrefOptions = {
  /** Event public site base path, e.g. `/en/e/phase-1-event` */
  siteBaseUrl?: string | null
  registerUrl?: string | null
}

/**
 * Resolve header/footer/button hrefs for an event public site.
 *
 * - `/`, `home`, empty → site home (`siteBaseUrl`)
 * - `about`, `/about`, `about/` → `{siteBaseUrl}/about`
 * - full `https://…` / `mailto:` / `tel:` → unchanged (external)
 * - `registration` → registerUrl
 * - `#section` → unchanged (in-page anchor)
 */
export function resolveSiteHref(href: unknown, options: ResolveSiteHrefOptions = {}): string {
  const raw = typeof href === 'string' ? href.trim() : ''
  if (!raw) return '#'

  if (raw === 'registration') {
    return options.registerUrl?.trim() || '#'
  }

  // In-page anchors only (not "/#/" paths)
  if (raw.startsWith('#') && !raw.includes('/')) {
    return raw
  }

  // External / absolute protocols
  if (/^(https?:|mailto:|tel:)/i.test(raw) || raw.startsWith('//')) {
    return raw
  }

  const base = (options.siteBaseUrl || '').replace(/\/+$/, '')
  if (!base) {
    // Builder fallback when base is unknown
    if (raw === '/' || raw === 'home') return '/'
    return raw.startsWith('/') ? raw : `/${raw.replace(/^\/+|\/+$/g, '')}`
  }

  // Already scoped to this site
  if (raw === base || raw.startsWith(`${base}/`) || raw.startsWith(`${base}#`) || raw.startsWith(`${base}?`)) {
    return raw
  }

  let path = raw
  let hash = ''
  let query = ''

  const hashIdx = path.indexOf('#')
  if (hashIdx >= 0) {
    hash = path.slice(hashIdx)
    path = path.slice(0, hashIdx)
  }

  const queryIdx = path.indexOf('?')
  if (queryIdx >= 0) {
    query = path.slice(queryIdx)
    path = path.slice(0, queryIdx)
  }

  path = path.replace(/^\/+/, '').replace(/\/+$/, '')

  if (path === '' || path === 'home' || path === 'index') {
    return `${base}${query}${hash}`
  }

  // Legacy `/p/{slug}` → pretty `/{slug}`
  if (path === 'p') {
    return `${base}${query}${hash}`
  }
  if (path.startsWith('p/')) {
    path = path.slice(2).replace(/^\/+|\/+$/g, '')
  }

  // Pasted locale site path without host: `en/e/slug/...` or `en/e/slug/p/...`
  const localeSiteMatch = path.match(/^(en|ar)\/(e|events)\/[^/]+(?:\/(.*))?$/i)
  if (localeSiteMatch) {
    let rest = (localeSiteMatch[3] || '').replace(/^\/+|\/+$/g, '')
    if (rest === 'p') rest = ''
    if (rest.startsWith('p/')) rest = rest.slice(2).replace(/^\/+|\/+$/g, '')
    if (rest === '' || rest === 'home' || rest === 'index') {
      return `${base}${query}${hash}`
    }
    return `${base}/${rest}${query}${hash}`
  }

  return `${base}/${path}${query}${hash}`
}

export function isExternalHref(href: string): boolean {
  const raw = href.trim()
  return /^(https?:|mailto:|tel:)/i.test(raw) || raw.startsWith('//')
}

/** Build a page URL under the event site base. */
export function sitePageHref(
  siteBaseUrl: string,
  page: { slug?: string; is_home?: boolean },
): string {
  const base = siteBaseUrl.replace(/\/+$/, '')
  if (page.is_home || !page.slug || page.slug === 'home') {
    return base || '/'
  }
  return `${base}/${page.slug.replace(/^\/+|\/+$/g, '')}`
}
