import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'
import { isExternalHref, resolveSiteHref } from '@/lib/siteHref'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
}

export default function MediaTextRenderer({ content, options, refs, registerUrl, siteBaseUrl }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const body = typeof content.body === 'string' ? content.body : ''
  const buttonLabel = typeof content.button_label === 'string' ? content.button_label : ''
  const buttonHrefRaw = typeof content.button_href === 'string' ? content.button_href : ''
  const buttonHref = resolveSiteHref(buttonHrefRaw, { registerUrl, siteBaseUrl })
  const layout = typeof options.layout === 'string' ? options.layout : 'image_left'
  const image = typeof refs.image === 'string' ? refs.image : ''
  const background = options.background as SiteBackground | undefined

  const bgStyle = backgroundStyle(background)
  const overlayStyle = backgroundOverlayStyle(background)
  const hasBackground = background?.type && background.type !== 'none'

  const isVertical = layout === 'image_top'
  // Treat left/start as image-at-start, right/end as image-at-end (flips correctly in RTL).
  const imageAtEnd = layout === 'image_right' || layout === 'image_end'

  const textContent = (
    <div className={`flex flex-col justify-center ${isVertical ? 'text-center' : ''}`}>
      {title && (
        <h2 className="text-3xl md:text-4xl font-bold mb-4">{title}</h2>
      )}
      {body && (
        <p className="text-lg text-muted-foreground mb-6 whitespace-pre-wrap">{body}</p>
      )}
      {buttonLabel && buttonHrefRaw && (
        <div className={isVertical ? '' : ''}>
          <a
            href={buttonHref}
            className="button-primary inline-block"
            {...(isExternalHref(buttonHref) ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          >
            {buttonLabel}
          </a>
        </div>
      )}
    </div>
  )

  const imageContent = image ? (
    <div className={`overflow-hidden rounded-xl ${isVertical ? 'mx-auto max-w-2xl' : ''}`}>
      <img
        src={image}
        alt={title || ''}
        className="w-full h-auto object-cover"
      />
    </div>
  ) : null

  if (isVertical) {
    return (
      <section className="relative py-16 px-6" style={bgStyle}>
        {overlayStyle && <div style={overlayStyle} />}
        <div className={`relative max-w-5xl mx-auto space-y-8 ${hasBackground && background?.type === 'image' ? 'text-white' : ''}`}>
          {imageContent}
          {textContent}
        </div>
      </section>
    )
  }

  return (
    <section className="relative py-16 px-6" style={bgStyle}>
      {overlayStyle && <div style={overlayStyle} />}
      <div
        className={`relative max-w-6xl mx-auto grid gap-8 md:gap-12 items-center ${
          image ? 'md:grid-cols-2' : ''
        } ${hasBackground && background?.type === 'image' ? 'text-white' : ''}`}
      >
        {imageAtEnd ? (
          <>
            {textContent}
            {imageContent}
          </>
        ) : (
          <>
            {imageContent}
            {textContent}
          </>
        )}
      </div>
    </section>
  )
}
