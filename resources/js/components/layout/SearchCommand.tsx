import { router, usePage } from '@inertiajs/react'
import { CalendarDays, Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'

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

type SearchResponse = {
  results?: SearchResult[]
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
}

export default function SearchCommand() {
  const { locale, t, localizedPath: toLocalizedPath } = useLocale()
  const { auth, session } = usePage<PageProps>().props
  const tenantId = session?.tenant?.id ? String(session.tenant.id) : ''
  const canSearchEvents = Boolean(auth?.user ?? session?.user)
  const ref = useRef<HTMLDivElement>(null)
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [results, setResults] = useState<SearchResult[]>([])

  useClickOutside(ref, () => setOpen(false), open)

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
          setOpen(true)

          return
        }

        const body = await response.json() as SearchResponse
        const eventResults = (body.results ?? []).filter((result) => result.type === 'event')
        setResults(eventResults)
        setOpen(true)
      } catch {
        setResults([])
        setOpen(true)
      } finally {
        setLoading(false)
      }
    }, 250)

    return () => window.clearTimeout(timer)
  }, [canSearchEvents, locale, query, tenantId])

  function openResult(result: SearchResult) {
    setOpen(false)
    setQuery('')
    router.visit(toLocalizedPath(result.href))
  }

  return (
    <div ref={ref} className="relative min-w-0 flex-1 sm:max-w-md" data-tour="search">
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
            if (results.length > 0 || query.trim().length >= 1) {
              setOpen(true)
            }
          }}
        />
        <kbd>⌘K</kbd>
      </label>

      {open ? (
        <div className="absolute start-0 top-full z-50 mt-2 w-full overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] py-1 shadow-xl">
          {!canSearchEvents ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchEventsUnavailable')}</p>
          ) : null}

          {canSearchEvents && loading ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchLoading')}</p>
          ) : null}

          {canSearchEvents && !loading && results.length === 0 ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{t('searchNoResults')}</p>
          ) : null}

          {canSearchEvents && !loading && results.map((result) => {
            const label = locale === 'ar' && result.label_ar ? result.label_ar : result.label

            return (
              <button
                key={`${result.type}-${result.id}`}
                type="button"
                className="flex w-full items-center gap-3 px-3 py-2.5 text-start hover:bg-[var(--brand-soft)]"
                onClick={() => openResult(result)}
              >
                <span className="relative flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)]">
                  {result.main_image ? (
                    <img
                      src={result.main_image}
                      alt=""
                      className="h-full w-full object-cover"
                    />
                  ) : (
                    <CalendarDays className="h-5 w-5 text-[var(--muted)]" aria-hidden />
                  )}
                </span>

                <span className="min-w-0 flex-1">
                  <span className="block truncate font-medium text-[var(--ink)]">{label}</span>
                  <span className="mt-1 flex flex-wrap items-center gap-2 text-xs text-[var(--muted)]">
                    <span>{t('searchResultEvent')}</span>
                    {result.tenant_name ? <span>{result.tenant_name}</span> : null}
                    {result.meta ? <StatusBadge status={result.meta} /> : null}
                  </span>
                </span>
              </button>
            )
          })}
        </div>
      ) : null}
    </div>
  )
}
