type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function SponsorsRenderer({ content, locale }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''

  return (
    <section className="py-16 px-6 bg-muted/30">
      <div className="max-w-4xl mx-auto">
        {title && (
          <h2 className="text-3xl font-bold mb-8 text-center">{title}</h2>
        )}
        <div className="text-center text-muted-foreground py-8">
          {locale === 'ar'
            ? 'سيتم عرض شعارات الرعاة هنا.'
            : 'Sponsor logos will be displayed here.'}
        </div>
      </div>
    </section>
  )
}
