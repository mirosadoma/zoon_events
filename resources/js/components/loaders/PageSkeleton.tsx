export default function PageSkeleton() {
  return (
    <div className="space-y-4" role="status" aria-label="Loading page">
      <div className="h-8 w-1/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
      <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
      <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        {Array.from({ length: 4 }).map((_, index) => (
          <div key={index} className="h-24 animate-pulse rounded-xl bg-slate-200 dark:bg-slate-800" />
        ))}
      </div>
    </div>
  )
}
