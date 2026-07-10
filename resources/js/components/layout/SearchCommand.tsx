import { router } from '@inertiajs/react'
import { Search } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'
import ar from '@/locales/ar'
import en from '@/locales/en'

type SearchResult = {
  type: 'event' | 'user'
  id: string
  label: string
  label_ar?: string
  href: string
  meta?: string
}

export default function SearchCommand() {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
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
    if (query.trim().length < 2) {
      setResults([])
      setOpen(false)

      return
    }

    const timer = window.setTimeout(async () => {
      setLoading(true)

      try {
        const response = await fetch(localizedPath(locale, `/dashboard/search?q=${encodeURIComponent(query.trim())}`), {
          credentials: 'include',
          headers: { Accept: 'application/json' },
        })
        const body = await response.json() as { results?: SearchResult[] }
        setResults(body.results ?? [])
        setOpen(true)
      } catch {
        setResults([])
        setOpen(true)
      } finally {
        setLoading(false)
      }
    }, 250)

    return () => window.clearTimeout(timer)
  }, [locale, query])

  return (
    <div ref={ref} className="relative hidden min-w-0 flex-1 sm:block sm:max-w-md" data-tour="search">
      <label className="ta-search flex">
        <Search className="h-4 w-4 shrink-0" aria-hidden />
        <input
          type="search"
          className="w-full border-0 bg-transparent text-sm outline-none"
          placeholder={messages.searchPlaceholder}
          aria-label={messages.searchPlaceholder}
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onFocus={() => results.length > 0 && setOpen(true)}
        />
        <kbd className="hidden rounded border border-[var(--border)] px-1.5 text-xs text-slate-400 md:inline">⌘K</kbd>
      </label>

      {open ? (
        <div className="absolute start-0 top-full z-50 mt-2 w-full rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] py-1 shadow-xl">
          {loading ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{locale === 'ar' ? 'جارٍ البحث...' : 'Searching...'}</p>
          ) : null}
          {!loading && results.length === 0 ? (
            <p className="px-4 py-3 text-sm text-[var(--muted)]">{locale === 'ar' ? 'لا توجد نتائج' : 'No results'}</p>
          ) : null}
          {!loading && results.map((result) => (
            <button
              key={`${result.type}-${result.id}`}
              type="button"
              className="flex w-full flex-col gap-0.5 px-4 py-2 text-start text-sm hover:bg-[var(--brand-soft)]"
              onClick={() => {
                setOpen(false)
                setQuery('')
                router.visit(localizedPath(locale, result.href))
              }}
            >
              <span className="font-medium">
                {locale === 'ar' && result.label_ar ? result.label_ar : result.label}
              </span>
              <span className="text-xs text-[var(--muted)]">
                {result.type === 'event' ? (locale === 'ar' ? 'فعالية' : 'Event') : (locale === 'ar' ? 'مستخدم' : 'User')}
                {result.meta ? ` · ${result.meta}` : ''}
              </span>
            </button>
          ))}
        </div>
      ) : null}
    </div>
  )
}
