type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function GalleryRenderer({ content, locale }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''

  return (
    <section className="py-16 px-6">
      <div className="max-w-5xl mx-auto">
        {title && (
          <h2 className="text-3xl font-bold mb-8 text-center">{title}</h2>
        )}
        <div className="text-center text-muted-foreground py-8">
          {locale === 'ar'
            ? 'سيتم عرض صور الحدث هنا.'
            : 'Event images will be displayed here.'}
        </div>
      </div>
    </section>
  )
}
