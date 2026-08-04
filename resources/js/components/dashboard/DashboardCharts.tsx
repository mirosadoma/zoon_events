import type { ReactNode } from 'react'

type DayPoint = { date: string; count: number }

export function DaySeriesBars({
  days,
  emptyLabel,
  barClassName = 'bg-sky-500/80',
}: {
  days: DayPoint[]
  emptyLabel: string
  barClassName?: string
}) {
  const max = Math.max(1, ...days.map((day) => day.count))
  const allZero = days.every((day) => day.count === 0)

  if (allZero) {
    return <p className="text-sm text-[var(--muted)]">{emptyLabel}</p>
  }

  return (
    <div className="flex gap-1.5 overflow-x-auto pb-1">
      {days.map((day) => {
        const height = Math.round((day.count / max) * 100)
        return (
          <div
            key={day.date}
            className="flex w-9 shrink-0 flex-col items-center gap-1"
            title={`${day.date}: ${day.count}`}
          >
            <div className="flex h-28 w-full items-end justify-center">
              <div
                className={`w-3 rounded-t ${barClassName}`}
                style={{ height: `${height}%`, minHeight: day.count > 0 ? 4 : 0 }}
              />
            </div>
            <span className="text-[10px] text-[var(--muted)]">{day.date.slice(5)}</span>
          </div>
        )
      })}
    </div>
  )
}

export function FunnelStrip({
  steps,
}: {
  steps: Array<{ key: string; label: string; count: number; conversion_from_previous?: number | null }>
}) {
  // Use the first stage as the 100% baseline when it has volume; otherwise the peak count.
  const firstCount = steps[0]?.count ?? 0
  const peak = Math.max(0, ...steps.map((step) => step.count))
  const baseline = firstCount > 0 ? firstCount : Math.max(1, peak)

  return (
    <div className="space-y-3">
      {steps.map((step, index) => {
        const width = step.count <= 0
          ? 0
          : Math.min(100, Math.round((step.count / baseline) * 100))
        const previous = index > 0 ? steps[index - 1]?.count ?? 0 : null
        const conversion = step.conversion_from_previous !== undefined
          ? step.conversion_from_previous
          : previous !== null && previous > 0
            ? Math.round((step.count / previous) * 1000) / 10
            : null

        return (
          <div key={step.key}>
            <div className="mb-1 flex items-center justify-between gap-3 text-sm">
              <span className="font-medium text-[var(--ink)]">{step.label}</span>
              <span className="tabular-nums text-[var(--muted)]">
                {step.count}
                {conversion !== null ? ` · ${conversion}%` : ''}
              </span>
            </div>
            <div className="h-2.5 overflow-hidden rounded-full border border-[var(--border)] bg-[var(--surface)]">
              <div
                className="h-full rounded-full bg-[var(--brand)] transition-[width]"
                style={{ width: `${width}%` }}
              />
            </div>
          </div>
        )
      })}
    </div>
  )
}

export function DashboardSection({
  title,
  description,
  children,
}: {
  title: string
  description?: string
  children: ReactNode
}) {
  return (
    <section className="ta-card mt-6 p-5">
      <div className="mb-4 border-b border-[var(--border)] pb-3">
        <h2 className="text-lg font-semibold text-[var(--ink)]">{title}</h2>
        {description ? (
          <p className="mt-1 text-sm text-[var(--muted)]">{description}</p>
        ) : null}
      </div>
      {children}
    </section>
  )
}
