import { useCallback, useId, useMemo, useRef, useState } from 'react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'

export type SearchableOption = {
  value: string
  label: string
  hint?: string
  searchText?: string
}

type SearchableSelectProps = {
  label: string
  value: string
  onChange: (value: string) => void
  options: SearchableOption[]
  placeholder?: string
  error?: string
  disabled?: boolean
  name?: string
}

function normalizeSearch(value: string): string {
  return value.trim().toLowerCase()
}

export default function SearchableSelect({
  label,
  value,
  onChange,
  options,
  placeholder = 'Search…',
  error,
  disabled = false,
  name,
}: SearchableSelectProps) {
  const { locale } = useLocale()
  const listId = useId()
  const rootRef = useRef<HTMLDivElement>(null)
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')

  const selected = useMemo(
    () => options.find((option) => option.value === value) ?? null,
    [options, value],
  )

  const filtered = useMemo(() => {
    const needle = normalizeSearch(query)
    if (!needle) return options.slice(0, 120)

    return options
      .filter((option) => {
        const haystack = normalizeSearch(
          option.searchText ?? `${option.label} ${option.hint ?? ''} ${option.value}`,
        )

        return haystack.includes(needle)
      })
      .slice(0, 300)
  }, [options, query])

  const close = useCallback(() => {
    setOpen(false)
    setQuery('')
  }, [])

  useClickOutside(rootRef, close, open)

  const emptyLabel = locale === 'ar' ? 'لا توجد نتائج' : 'No matches'

  return (
    <div ref={rootRef} className="grid gap-2 text-sm">
      <span className="font-medium text-[var(--ink)]">{label}</span>
      <div className="relative">
        <button
          type="button"
          className="control flex w-full cursor-pointer items-center justify-between gap-2 text-start"
          disabled={disabled}
          aria-expanded={open}
          aria-controls={listId}
          onClick={() => {
            if (disabled) return
            setOpen((current) => !current)
          }}
        >
          <span className={selected ? 'text-[var(--ink)]' : 'text-[var(--muted)]'}>
            {selected?.label ?? placeholder}
          </span>
          <span aria-hidden className="text-[var(--muted)]">▾</span>
        </button>
        {name && <input type="hidden" name={name} value={value} />}
        {open && (
          <div
            id={listId}
            className="absolute z-30 mt-1 w-full overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] shadow-lg"
          >
            <div className="border-b border-[var(--border)] p-2">
              <input
                type="search"
                className="control w-full"
                placeholder={placeholder}
                value={query}
                autoFocus
                onChange={(event) => setQuery(event.target.value)}
              />
            </div>
            <ul className="max-h-60 overflow-y-auto py-1" role="listbox">
              {filtered.length === 0 && (
                <li className="px-3 py-2 text-[var(--muted)]">{emptyLabel}</li>
              )}
              {filtered.map((option) => (
                <li key={option.value}>
                  <button
                    type="button"
                    role="option"
                    aria-selected={option.value === value}
                    className={`w-full cursor-pointer px-3 py-2 text-start transition hover:bg-[var(--brand-soft)] ${option.value === value ? 'bg-[var(--brand-soft)] font-medium text-[var(--brand)]' : 'text-[var(--ink)]'}`}
                    onClick={() => {
                      onChange(option.value)
                      close()
                    }}
                  >
                    <span className="block">{option.label}</span>
                    {option.hint && (
                      <span className="block text-xs text-[var(--muted)]">{option.hint}</span>
                    )}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
      {error && <span role="alert" className="text-red-600 dark:text-red-400">{error}</span>}
    </div>
  )
}
