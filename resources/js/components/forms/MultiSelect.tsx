import { useMemo, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'
import { ValidationError } from './TextInput'

export type MultiSelectOption = {
  value: string
  label: string
  hint?: string
}

type MultiSelectProps = {
  label: string
  values: string[]
  options: MultiSelectOption[]
  onChange: (values: string[]) => void
  placeholder?: string
  error?: string
  hint?: string
}

export default function MultiSelect({
  label,
  values,
  options,
  onChange,
  placeholder,
  error,
  hint,
}: MultiSelectProps) {
  const { locale, t } = useLocale()
  const [query, setQuery] = useState('')
  const normalized = query.trim().toLowerCase()
  const selected = new Set(values)
  const filtered = useMemo(
    () => options
      .filter((option) => !normalized || `${option.label} ${option.hint ?? ''}`.toLowerCase().includes(normalized))
      .slice(0, 80),
    [normalized, options],
  )
  const errorId = `${label.replace(/\s+/g, '-')}-error`
  const hintId = `${label.replace(/\s+/g, '-')}-hint`

  function toggle(value: string) {
    onChange(selected.has(value) ? values.filter((item) => item !== value) : [...values, value])
  }

  return (
    <div className="grid gap-2 text-sm" aria-describedby={[hint ? hintId : null, error ? errorId : null].filter(Boolean).join(' ') || undefined}>
      <span className="font-medium text-[var(--ink)]">{label}</span>
      {hint ? <span id={hintId} className="text-xs text-[var(--muted)]">{hint}</span> : null}
      <input
        type="search"
        className="control focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--focus-ring)]/20"
        placeholder={placeholder ?? t('multiSelectSearchPlaceholder')}
        value={query}
        onChange={(event) => setQuery(event.target.value)}
      />
      <div className="max-h-56 overflow-y-auto rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-2">
        {filtered.length === 0 ? (
          <p className="px-2 py-3 text-sm text-[var(--muted)]">{t('multiSelectNoOptions')}</p>
        ) : filtered.map((option) => (
          <label key={option.value} className="flex cursor-pointer items-start gap-2 rounded-md px-2 py-2 hover:bg-[var(--brand-soft)]">
            <input
              type="checkbox"
              checked={selected.has(option.value)}
              onChange={() => toggle(option.value)}
              className="mt-1 accent-[var(--brand)]"
            />
            <span>
              <span className="block font-medium">{option.label}</span>
              {option.hint ? <span className="block text-xs text-[var(--muted)]">{option.hint}</span> : null}
            </span>
          </label>
        ))}
      </div>
      {values.length > 0 ? (
        <div className="flex flex-wrap gap-1">
          {values.map((value) => {
            const option = options.find((item) => item.value === value)

            return (
              <button
                key={value}
                type="button"
                className="ta-badge ta-badge-primary"
                onClick={() => toggle(value)}
              >
                {option?.label ?? value} ×
              </button>
            )
          })}
        </div>
      ) : null}
      {error ? <ValidationError id={errorId} message={error} /> : null}
    </div>
  )
}
