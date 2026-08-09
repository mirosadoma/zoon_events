import { backgroundOverlayStyle, backgroundStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'
import {
  bodyClasses,
  blockShellClasses,
  headingClasses,
  headingStyle,
  textStyle,
} from '@/lib/siteBlockStyle'
import { logicalTextAlignClass } from '@/lib/localeDirection'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function HeroRenderer({ content, options, refs }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const textAlignment = typeof options.text_alignment === 'string' ? options.text_alignment : 'center'
  const backgroundStyleOpt = typeof options.background_style === 'string' ? options.background_style : 'gradient'

  const alignClass = logicalTextAlignClass(textAlignment, 'center')

  const bgObject = options.background as SiteBackground | undefined
  const hasCustomBg = bgObject && bgObject.type && bgObject.type !== 'none'

  let sectionClass = 'relative py-24 px-6 text-primary-foreground'
  let sectionInline: Record<string, string | number> = {}

  if (hasCustomBg) {
    sectionInline = { ...backgroundStyle(bgObject) } as Record<string, string | number>
    sectionClass += ' text-white'
  } else if (backgroundStyleOpt === 'solid') {
    sectionClass += ' bg-[var(--brand)] text-white'
  } else if (backgroundStyleOpt === 'image' && typeof refs.background_image === 'string' && refs.background_image) {
    sectionInline = {
      backgroundImage: `url(${refs.background_image})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
    }
    sectionClass += ' text-white'
  } else {
    sectionClass += ' bg-gradient-to-br from-primary/90 to-primary text-primary-foreground'
  }

  const overlayOpacity = typeof options.overlay_opacity === 'number' ? options.overlay_opacity : 0
  const overlayStyle = hasCustomBg
    ? backgroundOverlayStyle(bgObject)
    : overlayOpacity > 0
      ? { position: 'absolute' as const, inset: 0, backgroundColor: `rgba(0,0,0,${overlayOpacity / 100})`, pointerEvents: 'none' as const }
      : null

  return (
    <section className={`${sectionClass} ${blockShellClasses(options)}`} style={sectionInline}>
      {overlayStyle && <div style={overlayStyle} />}
      <div className={`relative mx-auto max-w-4xl ${alignClass}`}>
        {title && (
          <h1 className={`mb-4 ${headingClasses(options)}`} style={headingStyle(options)}>
            {title}
          </h1>
        )}
        {subtitle && (
          <p className={`${bodyClasses(options)} opacity-90 md:text-2xl`} style={textStyle(options)}>
            {subtitle}
          </p>
        )}
      </div>
    </section>
  )
}
