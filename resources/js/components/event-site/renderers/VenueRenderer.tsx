import { MapPin } from 'lucide-react'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function VenueRenderer({ content }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const description = typeof content.description === 'string' ? content.description : ''

  return (
    <section className="py-16 px-6 bg-muted/30">
      <div className="max-w-4xl mx-auto text-center">
        {title && (
          <h2 className="text-3xl font-bold mb-6">{title}</h2>
        )}
        {description && (
          <div className="flex items-start justify-center gap-2 text-lg">
            <MapPin className="w-6 h-6 flex-shrink-0 text-primary mt-1" />
            <div className="whitespace-pre-line">{description}</div>
          </div>
        )}
      </div>
    </section>
  )
}
