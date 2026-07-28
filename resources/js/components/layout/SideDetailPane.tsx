import type { ReactNode } from 'react'
import { Pencil, Trash2, X } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

type SideDetailPaneProps = {
  open: boolean
  title: string
  subtitle?: string | null
  hero?: ReactNode
  onClose: () => void
  onEdit?: (() => void) | null
  onDelete?: (() => void) | null
  editLabel?: string
  deleteLabel?: string
  footer?: ReactNode
  children: ReactNode
}

export function sideDetailActionClassName(variant: 'primary' | 'secondary' | 'danger' = 'secondary'): string {
  if (variant === 'primary') {
    return 'inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] bg-[var(--brand-soft)] px-4 py-2.5 text-sm font-semibold text-[var(--brand)] transition hover:brightness-95'
  }
  if (variant === 'danger') {
    return 'inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[color-mix(in_srgb,var(--danger)_30%,var(--border))] bg-[var(--surface-elevated)] px-4 py-2.5 text-sm font-semibold text-[var(--danger)] transition hover:bg-[var(--danger-soft)]'
  }
  return 'inline-flex w-full items-center justify-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] px-4 py-2.5 text-sm font-semibold text-[var(--ink)] transition hover:border-[color-mix(in_srgb,var(--brand)_35%,var(--border))] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]'
}

export function SideDetailActions({ children }: { children: ReactNode }) {
  return <div className="flex flex-col gap-2">{children}</div>
}

export default function SideDetailPane({
  open,
  title,
  subtitle = null,
  hero = null,
  onClose,
  onEdit = null,
  onDelete = null,
  editLabel,
  deleteLabel,
  footer = null,
  children,
}: SideDetailPaneProps) {
  const { t } = useLocale()

  if (!open) {
    return null
  }

  return (
    <div className="fixed inset-0 z-50 flex justify-end" role="dialog" aria-modal="true" aria-label={title}>
      <button
        type="button"
        className="absolute inset-0 bg-slate-950/25 backdrop-blur-[1px] transition-opacity"
        aria-label={t('close')}
        onClick={onClose}
      />
      <aside className="side-detail-pane relative mt-16 flex h-[calc(100%-4rem)] w-full max-w-[26rem] flex-col bg-[var(--surface-elevated)] shadow-[-12px_0_40px_rgba(15,23,42,0.12)]">
        <header className="shrink-0 border-b border-[var(--border)] px-5 pb-4 pt-5">
          <div className="flex items-start gap-3">
            <div className="min-w-0 flex-1">
              <h2 className="truncate text-[1.05rem] font-semibold leading-snug tracking-tight text-[var(--ink)]">
                {title}
              </h2>
              {subtitle ? (
                <p className="mt-1 truncate text-sm text-[var(--muted)]">{subtitle}</p>
              ) : null}
            </div>
            <div className="flex shrink-0 items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--surface)]/70 p-1">
              {onEdit ? (
                <button
                  type="button"
                  className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[var(--muted)] transition hover:bg-[var(--brand-soft)] hover:text-[var(--brand)]"
                  aria-label={editLabel ?? t('edit')}
                  title={editLabel ?? t('edit')}
                  onClick={onEdit}
                >
                  <Pencil className="h-3.5 w-3.5" aria-hidden />
                </button>
              ) : null}
              {onDelete ? (
                <button
                  type="button"
                  className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[var(--muted)] transition hover:bg-[var(--danger-soft)] hover:text-[var(--danger)]"
                  aria-label={deleteLabel ?? t('delete')}
                  title={deleteLabel ?? t('delete')}
                  onClick={onDelete}
                >
                  <Trash2 className="h-3.5 w-3.5" aria-hidden />
                </button>
              ) : null}
              <button
                type="button"
                className="inline-flex h-8 w-8 items-center justify-center rounded-lg text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
                aria-label={t('close')}
                title={t('close')}
                onClick={onClose}
              >
                <X className="h-3.5 w-3.5" aria-hidden />
              </button>
            </div>
          </div>
        </header>

        <div className="flex-1 overflow-y-auto">
          {hero ? (
            <div className="border-b border-[var(--border)] bg-[color-mix(in_srgb,var(--surface)_70%,var(--surface-elevated))] px-5 py-5">
              {hero}
            </div>
          ) : null}
          <div className="px-5 py-5">{children}</div>
        </div>

        {footer ? (
          <div className="shrink-0 border-t border-[var(--border)] bg-[color-mix(in_srgb,var(--surface)_55%,var(--surface-elevated))] px-5 py-4">
            {footer}
          </div>
        ) : null}
      </aside>
    </div>
  )
}

type InfoGridItem = {
  label: string
  value: ReactNode
  valueClassName?: string
}

export function SideDetailInfoGrid({
  title,
  items,
}: {
  title?: string
  items: InfoGridItem[]
}) {
  const { t } = useLocale()

  return (
    <section>
      <div className="mb-4 flex items-center gap-3">
        <h3 className="text-[0.7rem] font-bold uppercase tracking-[0.14em] text-[var(--muted)]">
          {title ?? t('attendeePaneBasicInfo')}
        </h3>
        <div className="h-px flex-1 bg-[var(--border)]" />
      </div>
      <dl className="grid grid-cols-2 gap-x-5 gap-y-5">
        {items.map((item) => (
          <div key={item.label} className="min-w-0">
            <dt className="text-[0.7rem] font-medium uppercase tracking-[0.06em] text-[var(--muted)]">
              {item.label}
            </dt>
            <dd className={`mt-1.5 break-words text-sm font-semibold leading-snug text-[var(--ink)] ${item.valueClassName ?? ''}`}>
              {item.value}
            </dd>
          </div>
        ))}
      </dl>
    </section>
  )
}
