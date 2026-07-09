export default function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="ta-card space-y-3" role="status" aria-label="Loading table">
      <div className="flex items-center justify-between gap-3">
        <div className="h-5 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
        <div className="h-9 w-40 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-800" />
      </div>
      {Array.from({ length: rows }).map((_, index) => (
        <div key={index} className="h-12 animate-pulse rounded-lg bg-slate-200 dark:bg-slate-800" />
      ))}
    </div>
  )
}
