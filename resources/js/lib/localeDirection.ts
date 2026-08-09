/** Resolve stored alignment values to logical Tailwind text classes. */
export function logicalTextAlignClass(value: unknown, fallback: 'start' | 'center' | 'end' = 'center'): string {
  const raw = typeof value === 'string' ? value : fallback
  if (raw === 'left' || raw === 'start') return 'text-start'
  if (raw === 'right' || raw === 'end') return 'text-end'
  return 'text-center'
}

/** Normalize legacy left/right alignments to start/end. */
export function normalizeLogicalAlign(value: unknown, fallback: 'start' | 'center' | 'end' = 'center'): 'start' | 'center' | 'end' {
  if (value === 'left' || value === 'start') return 'start'
  if (value === 'right' || value === 'end') return 'end'
  if (value === 'center') return 'center'
  return fallback
}

export function localeDirection(locale: 'en' | 'ar'): 'ltr' | 'rtl' {
  return locale === 'ar' ? 'rtl' : 'ltr'
}
