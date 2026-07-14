import { clsx } from 'clsx'
import { ChevronLeft, ChevronRight } from 'lucide-react'

type PaginationProps = {
  page: number
  totalPages: number
  onPageChange: (page: number) => void
  previousLabel?: string
  nextLabel?: string
  pageLabel?: string
}

function pageItems(current: number, total: number): Array<number | 'gap'> {
  if (total <= 7) {
    return Array.from({ length: total }, (_, index) => index + 1)
  }

  const pages = new Set<number>([1, total])
  for (let page = current - 1; page <= current + 1; page += 1) {
    if (page >= 1 && page <= total) {
      pages.add(page)
    }
  }

  const sorted = [...pages].sort((a, b) => a - b)
  const items: Array<number | 'gap'> = []
  let previous = 0

  for (const value of sorted) {
    if (previous > 0 && value - previous > 1) {
      items.push('gap')
    }
    items.push(value)
    previous = value
  }

  return items
}

const controlClass =
  'inline-flex h-9 min-w-9 items-center justify-center rounded-lg px-2.5 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-40'

export default function Pagination({
  page,
  totalPages,
  onPageChange,
  previousLabel = 'Previous',
  nextLabel = 'Next',
  pageLabel,
}: PaginationProps) {
  if (totalPages <= 1) {
    return null
  }

  const items = pageItems(page, totalPages)

  return (
    <nav
      aria-label="Pagination"
      className="mt-4 flex flex-wrap items-center justify-center gap-3 border-t border-[var(--border)] pt-4"
    >
      <p className="text-sm text-[var(--muted)]">
        {pageLabel ?? `Page ${page} of ${totalPages}`}
      </p>

      <div className="inline-flex items-center gap-1 rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-1 shadow-[var(--card-shadow)]">
        <button
          type="button"
          className={clsx(
            controlClass,
            'gap-1 text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--ink)]',
          )}
          disabled={page <= 1}
          aria-label={previousLabel}
          onClick={() => onPageChange(page - 1)}
        >
          <ChevronLeft className="h-4 w-4 rtl:rotate-180" aria-hidden />
          <span className="hidden sm:inline">{previousLabel}</span>
        </button>

        <div className="mx-0.5 flex items-center gap-0.5 border-x border-[var(--border)] px-1">
          {items.map((item, index) =>
            item === 'gap' ? (
              <span
                key={`gap-${index}`}
                className="inline-flex h-9 w-8 items-center justify-center text-sm text-[var(--muted)]"
                aria-hidden
              >
                …
              </span>
            ) : (
              <button
                key={item}
                type="button"
                className={clsx(
                  controlClass,
                  item === page
                    ? 'bg-[var(--brand)] text-white shadow-sm'
                    : 'text-[var(--ink)] hover:bg-[var(--surface)]',
                )}
                aria-label={`Page ${item}`}
                aria-current={item === page ? 'page' : undefined}
                onClick={() => onPageChange(item)}
              >
                {item}
              </button>
            ),
          )}
        </div>

        <button
          type="button"
          className={clsx(
            controlClass,
            'gap-1 text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--ink)]',
          )}
          disabled={page >= totalPages}
          aria-label={nextLabel}
          onClick={() => onPageChange(page + 1)}
        >
          <span className="hidden sm:inline">{nextLabel}</span>
          <ChevronRight className="h-4 w-4 rtl:rotate-180" aria-hidden />
        </button>
      </div>
    </nav>
  )
}
