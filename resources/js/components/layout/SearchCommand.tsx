import { router, usePage } from '@inertiajs/react'
import { CalendarDays, FileText, Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'
import { searchableEventPages } from '@/lib/tenant-navigation'
import type { PermissionMap } from '@/types/shell'
import ar from '@/locales/ar'
import en from '@/locales/en'

type SearchResult = {
  type: 'event' | 'user'
  id: string
  label: string
  label_ar?: string
  href: string
  meta?: string
  tenant_name?: string
  main_image?: string | null
}

type AccessibleEvent = {
  id: string
  label: string
  label_ar?: string
  tenant_name?: string
}

type SearchResponse = {
  results?: SearchResult[]
  accessible_events?: AccessibleEvent[]
}

type PageHit = {
  key: string
  label: string
  href: string
}

type PageGroup = {
  eventId: string
  eventLabel: string
  tenantName?: string
  pages: PageHit[]
}

type PageProps = {
  auth?: {
    user?: unknown
  }
  session?: {
    user?: unknown
    tenant?: {
      id?: string | number
    } | null
  } | null
  can?: PermissionMap
}

const pageCatalog = searchableEventPages()

function matchesPageQuery(query: string, labelKey: string, pathSuffix: string, key: string): boolean {
  const needle = query.trim().toLowerCase()

  if (needle.length < 1) {
    return false
  }

  const enLabel = String(en[labelKey as keyof typeof en] ?? labelKey).toLowerCase()
  const arLabel = String(ar[labelKey as keyof typeof ar] ?? labelKey).toLowerCase()
  const suffix = pathSuffix.replace(/^\//, '').toLowerCase()

  return (
    enLabel.includes(needle)
    || arLabel.includes(needle)
    || key.toLowerCase().includes(needle)
    || suffix.includes(needle)
  )
}

function buildPageGroups(
  query: string,
  events: AccessibleEvent[],
  permissions: PermissionMap,
  locale: string,
  translate: (key: string) => string,
): PageGroup[] {
  const trimmed = query.trim()

  if (trimmed.length < 1 || events.length === 0) {
    return []
  }

  const matchingPages = pageCatalog.filter((page) => {
    const permitted = page.permission === null || permissions[page.permission as keyof PermissionMap] === true

    return permitted && matchesPageQuery(trimmed, page.labelKey, page.pathSuffix, page.key)
  })

  if (matchingPages.length === 0) {
    return []
  }

  return events.map((event) => ({
    eventId: event.id,
    eventLabel: locale === 'ar' && event.label_ar ? event.label_ar : event.label,
    tenantName: event.tenant_name,
    pages: matchingPages.map((page) => ({
      key: page.key,
      label: translate(page.labelKey),
      href: `/tenant/events/${event.id}${page.pathSuffix}`,
    })),
  }))
}

export default function SearchCommand() {
  const { locale, t, localizedPath: toLocalizedPath } = useLocale()
  const { auth, session, can } = usePage<PageProps>().props
  const permissions = (can ?? {}) as PermissionMap
  const tenantId = session?.tenant?.id ? String(session.tenant.id) : ''
  const canSearchEvents = Boolean(auth?.user ?? session?.user)
  const ref = useRef<HTMLDivElement>(null)
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [results, setResults] = useState<SearchResult[]>([])
  const [accessibleEvents, setAccessibleEvents] = useState<AccessibleEvent[]>([])

  useClickOutside(ref, () => setOpen(false), open)

  const pageGroups = buildPageGroups(query, accessibleEvents, permissions, locale, t)

  useEffect(() => {
    const onKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault()
        ref.current?.querySelector('input')?.focus()
      }

      if (event.key === 'Escape') {
        setOpen(false)
      }
    }

    document.addEventListener('keydown', onKeyDown)

    return () => document.removeEventListener('keydown', onKeyDown)
  }, [])

  useEffect(() => {
    const trimmed = query.trim()

    if (trimmed.length < 1 || !canSearchEvents) {
      setResults([])
      setAccessibleEvents([])
      setOpen(false)

      return
    }

    setOpen(true)

    const timer = window.setTimeout(async () => {
      setLoading(true)

      try {
        const headers: Record<string, string> = {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        }

        if (tenantId !== '') {
          headers['X-Tenant-ID'] = tenantId
        }

        const response = await fetch(
          localizedPath(locale, `/dashboard/search?q=${encodeURIComponent(trimmed)}`),
          {
            credentials: 'include',
            headers,
          },
        )

        if (!response.ok) {
          setResults([])
          setAccessibleEvents([])
          setOpen(true)

          return
        }

        const body = await response.json() as SearchResponse
        const eventResults = (body.results ?? []).filter((result) => result.type === 'event')
        setResults(eventResults)
        setAccessibleEvents(body.accessible_events ?? [])
        setOpen(true)
      } catch {
        setResults([])
        setAccessibleEvents([])
        setOpen(true)
      } finally {
        setLoading(false)
      }
    }, 250)

    return () => window.clearTimeout(timer)
  }, [canSearchEvents, locale, query, tenantId])

  function openHref(href: string) {
    setOpen(false)
    setQuery('')
    router.visit(toLocalizedPath(href))
  }

  const hasAnyResults = results.length > 0 || pageGroups.length > 0
  const showEmpty = canSearchEvents && !loading && !hasAnyResults

  return (
    <div ref={ref} className="relative min-w-0 flex-1 sm:max-w-xl" data-tour="search">
      <label className="ta-search">
        <Search className="h-4 w-4 shrink-0" aria-hidden />
        <input
          type="search"
          className="w-full border-0 bg-transparent text-sm text-[var(--ink)] outline-none placeholder:text-[var(--muted)]"
          placeholder={t('searchPlaceholder')}
          aria-label={t('searchPlaceholder')}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onFocus={() => {
            if (hasAnyResults || query.trim().length >= 1) {
              setOpen(true)
            }
          }}
        />
        <kbd>⌘K</kbd>
      </label>

      {open ? (
        <div className="absolute start-0 top-full z-50 mt-2 w-[min(100vw-1.5rem,36rem)] overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-xl sm:w-[36rem]">
          {!canSearchEvents ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchEventsUnavailable')}</p>
          ) : null}

          {canSearchEvents && loading ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchLoading')}</p>
          ) : null}

          {showEmpty ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchNoResults')}</p>
          ) : null}

          {canSearchEvents && !loading && hasAnyResults ? (
            <div className="grid max-h-[min(70vh,28rem)] grid-cols-1 divide-y divide-[var(--border)] overflow-hidden sm:grid-cols-2 sm:divide-x sm:divide-y-0 sm:rtl:divide-x-reverse">
              <section className="max-h-[min(70vh,28rem)] min-h-0 overflow-y-auto overscroll-contain" aria-label={t('searchSectionEvents')}>
                <p className="sticky top-0 z-10 border-b border-[var(--border)] bg-[var(--surface-elevated)] px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
                  {t('searchSectionEvents')}
                </p>

                {results.length === 0 ? (
                  <p className="px-3 py-3 text-sm text-[var(--muted)]">{t('searchNoEventResults')}</p>
                ) : (
                  results.map((result) => {
                    const label = locale === 'ar' && result.label_ar ? result.label_ar : result.label

                    return (
                      <button
                        key={`${result.type}-${result.id}`}
                        type="button"
                        className="flex w-full items-center gap-3 px-3 py-2.5 text-start hover:bg-[var(--brand-soft)]"
                        onClick={() => openHref(result.href)}
                      >
                        <span className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)]">
                          {result.main_image ? (
                            <img
                              src={result.main_image}
                              alt=""
                              className="h-full w-full object-cover"
                            />
                          ) : (
                            <CalendarDays className="h-4 w-4 text-[var(--muted)]" aria-hidden />
                          )}
                        </span>

                        <span className="min-w-0 flex-1">
                          <span className="block truncate text-sm font-medium text-[var(--ink)]">{label}</span>
                          <span className="mt-1 flex flex-wrap items-center gap-2 text-xs text-[var(--muted)]">
                            <span>{t('searchResultEvent')}</span>
                            {result.tenant_name ? <span>{result.tenant_name}</span> : null}
                            {result.meta ? <StatusBadge status={result.meta} /> : null}
                          </span>
                        </span>
                      </button>
                    )
                  })
                )}
              </section>

              <section className="max-h-[min(70vh,28rem)] min-h-0 overflow-y-auto overscroll-contain" aria-label={t('searchSectionPages')}>
                <p className="sticky top-0 z-10 border-b border-[var(--border)] bg-[var(--surface-elevated)] px-3 py-2 text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
                  {t('searchSectionPages')}
                </p>

                {pageGroups.length === 0 ? (
                  <p className="px-3 py-3 text-sm text-[var(--muted)]">{t('searchNoPageResults')}</p>
                ) : (
                  pageGroups.map((group) => (
                    <div key={group.eventId} className="border-b border-[var(--border)] last:border-b-0">
                      <p className="truncate px-3 pb-1 pt-2.5 text-xs font-semibold text-[var(--ink)]">
                        {group.eventLabel}
                        {group.tenantName ? (
                          <span className="ms-1 font-normal text-[var(--muted)]">· {group.tenantName}</span>
                        ) : null}
                      </p>

                      {group.pages.map((page) => (
                        <button
                          key={`${group.eventId}-${page.key}`}
                          type="button"
                          className="flex w-full items-center gap-2.5 px-3 py-2 text-start hover:bg-[var(--brand-soft)]"
                          onClick={() => openHref(page.href)}
                        >
                          <FileText className="h-3.5 w-3.5 shrink-0 text-[var(--muted)]" aria-hidden />
                          <span className="truncate text-sm text-[var(--ink)]">{page.label}</span>
                        </button>
                      ))}
                    </div>
                  ))
                )}
              </section>
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}
