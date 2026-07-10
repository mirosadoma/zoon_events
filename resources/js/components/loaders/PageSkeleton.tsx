export default function PageSkeleton() {
  return (
    <div className="space-y-5" role="status" aria-label="Loading page">
      <div className="space-y-2">
        <div className="h-8 w-1/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
        <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
      </div>
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="ta-card h-28 animate-pulse bg-slate-200/70 dark:bg-slate-800" />
        ))}
      </div>
      <div className="ta-card h-64 animate-pulse bg-slate-200/70 dark:bg-slate-800" />
    </div>
  )
}
