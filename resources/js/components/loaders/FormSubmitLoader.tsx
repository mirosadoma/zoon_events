import { useLocale } from '@/hooks/useLocale'

export default function FormSubmitLoader({ label }: { label?: string }) {
  const { t } = useLocale()

  return (
    <div className="inline-flex items-center gap-2 rounded-lg bg-[var(--brand-soft)] px-3 py-2 text-sm font-medium text-[var(--brand)]" role="status">
      <span className="size-4 animate-spin rounded-full border-2 border-[var(--brand)]/25 border-t-[var(--brand)]" aria-hidden />
      {label ?? t('saving')}
    </div>
  )
}
