export default function CardSkeleton() {
  return (
    <div className="ta-card space-y-3" role="status" aria-label="Loading card">
      <div className="h-10 w-10 animate-pulse rounded-full bg-slate-200 dark:bg-slate-800" />
      <div className="h-4 w-2/3 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
      <div className="h-8 w-1/2 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
    </div>
  )
}
