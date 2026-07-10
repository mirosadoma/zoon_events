type TimelineItem = {
  id: string
  title: string
  detail?: string
  occurredAt?: string
}

type TimelineProps = {
  items: TimelineItem[]
}

export default function Timeline({ items }: TimelineProps) {
  if (items.length === 0) {
    return null
  }

  return (
    <ol className="space-y-4 border-s border-slate-200 ps-4 dark:border-slate-700">
      {items.map((item) => (
        <li key={item.id} className="relative">
          <span className="absolute -start-[1.35rem] top-1.5 size-2 rounded-full bg-sky-600" />
          <p className="font-medium">{item.title}</p>
          {item.detail && <p className="text-sm text-slate-600 dark:text-slate-300">{item.detail}</p>}
          {item.occurredAt && (
            <time className="text-xs text-slate-500" dateTime={item.occurredAt}>{item.occurredAt}</time>
          )}
        </li>
      ))}
    </ol>
  )
}
