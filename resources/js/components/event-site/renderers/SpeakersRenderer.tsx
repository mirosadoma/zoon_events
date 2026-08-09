type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  resolved?: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function SpeakersRenderer({ content, options, resolved, locale }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const speakersRaw = Array.isArray(resolved?.speakers) ? resolved.speakers : []
  const speakers = speakersRaw.filter(
    (item): item is { name: string } =>
      typeof item === 'object' && item !== null && typeof (item as { name?: unknown }).name === 'string',
  )
  const isEmpty = speakers.length === 0 || resolved?.empty === true
  const columns = typeof options.columns === 'number' ? options.columns : 3
  const gridClass =
    columns === 2
      ? 'sm:grid-cols-2'
      : columns === 4
        ? 'sm:grid-cols-2 lg:grid-cols-4'
        : 'sm:grid-cols-2 lg:grid-cols-3'

  return (
    <section className="py-16 px-6">
      <div className="max-w-4xl mx-auto">
        {title && (
          <h2 className="text-3xl font-bold mb-8 text-center">{title}</h2>
        )}
        {isEmpty ? (
          <div className="rounded-xl border border-dashed border-[var(--border)] px-6 py-10 text-center text-muted-foreground">
            {locale === 'ar'
              ? 'لا يوجد متحدثون بعد. أضفهم في جلسات الأجندة.'
              : 'No speakers yet. Add them on agenda sessions.'}
          </div>
        ) : (
          <div className={`grid gap-4 grid-cols-1 ${gridClass}`}>
            {speakers.map((speaker) => (
              <div
                key={speaker.name}
                className="rounded-xl border border-[var(--border)] bg-background/80 p-5 text-center shadow-sm"
              >
                <div className="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-primary/15 text-lg font-bold text-primary">
                  {speaker.name.trim().charAt(0).toUpperCase() || '?'}
                </div>
                <p className="font-semibold">{speaker.name}</p>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  )
}
