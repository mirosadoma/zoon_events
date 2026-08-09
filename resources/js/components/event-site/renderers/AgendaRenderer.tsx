import {
  bodyClasses,
  containerMaxWidthClass,
  headingClasses,
  headingStyle,
  sectionPadXClass,
  textStyle,
} from '@/lib/siteBlockStyle'

type AgendaItem = {
  id: string
  title?: string | null
  description?: string | null
  speaker?: string | null
  date?: string | null
  start_at?: string | null
  end_at?: string | null
}

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  resolved?: Record<string, unknown>
  locale: 'en' | 'ar'
}

function asItems(raw: unknown): AgendaItem[] {
  if (!Array.isArray(raw)) return []
  return raw.filter((item): item is AgendaItem => typeof item === 'object' && item !== null && 'id' in item)
}

function formatDateLabel(date: string, locale: 'en' | 'ar'): string {
  try {
    const parsed = new Date(`${date}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return date
    return parsed.toLocaleDateString(locale === 'ar' ? 'ar' : 'en', {
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
    })
  } catch {
    return date
  }
}

export default function AgendaRenderer({ content, options, resolved, locale }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const showSpeakers = options.show_speakers === true
  const groupByDate = options.group_by_date === true
  const items = asItems(resolved?.items)
  const isEmpty = items.length === 0 || resolved?.empty === true

  const groups: Array<{ key: string; label: string; items: AgendaItem[] }> = []
  if (!isEmpty && groupByDate) {
    const byDate = new Map<string, AgendaItem[]>()
    for (const item of items) {
      const key = item.date?.trim() || '_none'
      const list = byDate.get(key) ?? []
      list.push(item)
      byDate.set(key, list)
    }
    for (const [key, groupItems] of byDate) {
      groups.push({
        key,
        label: key === '_none'
          ? (locale === 'ar' ? 'بدون تاريخ' : 'No date')
          : formatDateLabel(key, locale),
        items: groupItems,
      })
    }
  } else if (!isEmpty) {
    groups.push({
      key: 'all',
      label: '',
      items,
    })
  }

  return (
    <section className={`relative py-16 ${sectionPadXClass(options)} bg-muted/30`}>
      <div className={`relative ${containerMaxWidthClass(options, 'max-w-4xl')}`}>
        {title && (
          <h2
            className={`mb-8 text-center ${headingClasses(options) || 'text-3xl font-bold'}`}
            style={headingStyle(options)}
          >
            {title}
          </h2>
        )}

        {isEmpty ? (
          <div
            className={`rounded-xl border border-dashed border-[var(--border)] bg-background/60 px-6 py-10 text-center ${bodyClasses(options)} text-muted-foreground`}
            style={textStyle(options)}
          >
            <p className="font-medium text-[var(--ink)]">
              {locale === 'ar' ? 'لا توجد عناصر في جدول الأعمال بعد' : 'No agenda items yet'}
            </p>
            <p className="mt-2 text-sm opacity-80">
              {locale === 'ar'
                ? 'أضف جلسات من صفحة الأجندة في لوحة التحكم، وستظهر هنا تلقائياً.'
                : 'Add sessions from the event Agenda page in the dashboard and they will show here automatically.'}
            </p>
          </div>
        ) : (
          <div className="space-y-8">
            {groups.map((group) => (
              <div key={group.key} className="space-y-4">
                {group.label && (
                  <h3
                    className={`border-b border-[var(--border)] pb-2 text-lg font-semibold ${bodyClasses(options)}`}
                    style={textStyle(options)}
                  >
                    {group.label}
                  </h3>
                )}
                <ul className="space-y-3">
                  {group.items.map((item) => {
                    const timeLabel = [item.start_at, item.end_at].filter(Boolean).join(' – ')
                    return (
                      <li
                        key={item.id}
                        className="rounded-xl border border-[var(--border)] bg-background/80 p-4 shadow-sm"
                      >
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                          <div className="min-w-0 flex-1">
                            <h4
                              className={`font-semibold ${bodyClasses(options)}`}
                              style={textStyle(options)}
                            >
                              {item.title || (locale === 'ar' ? 'جلسة' : 'Session')}
                            </h4>
                            {item.description && (
                              <p className="mt-1 text-sm text-muted-foreground whitespace-pre-line">
                                {item.description}
                              </p>
                            )}
                            {showSpeakers && item.speaker && (
                              <p className="mt-2 text-sm font-medium text-primary">
                                {item.speaker}
                              </p>
                            )}
                          </div>
                          {timeLabel && (
                            <div className="shrink-0 rounded-md bg-muted px-3 py-1.5 text-sm font-medium tabular-nums">
                              {timeLabel}
                            </div>
                          )}
                        </div>
                        {!groupByDate && item.date && (
                          <p className="mt-2 text-xs text-muted-foreground">
                            {formatDateLabel(item.date, locale)}
                          </p>
                        )}
                      </li>
                    )
                  })}
                </ul>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}
