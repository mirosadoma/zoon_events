import {
  bodyClasses,
  blockShellClasses,
  blockShellOverlay,
  blockShellStyle,
  headingClasses,
  headingStyle,
  sectionPadXClass,
  textStyle,
} from '@/lib/siteBlockStyle'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

function aboutContentWidth(layout: string): string {
  // Percentage widths keep left/right space equal on builder canvas and public page.
  if (layout === 'two-column') return 'mx-auto w-[min(96%,72rem)]'
  if (layout === 'centered') return 'mx-auto w-[min(92%,48rem)] text-center'
  return 'mx-auto w-[min(92%,56rem)] text-start'
}

export default function AboutRenderer({ content, options, refs }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const body = typeof content.body === 'string' ? content.body : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'centered'
  const imageUrl = typeof refs.image === 'string' ? refs.image : ''

  const shellCls = blockShellClasses(options)
  const shellStyle = blockShellStyle(options)
  const shellOverlay = blockShellOverlay(options)

  return (
    <section
      className={`relative w-full ${sectionPadXClass(options)} py-[4%] sm:py-16 ${shellCls}`.trim()}
      style={shellStyle}
    >
      {shellOverlay && <div className="pointer-events-none absolute inset-0" style={shellOverlay} />}
      <div className={`relative ${aboutContentWidth(layout)}`}>
        {layout === 'two-column' && imageUrl ? (
          <div className="grid gap-8 md:grid-cols-2 md:items-center">
            <img src={imageUrl} alt="" className="w-full rounded-xl object-cover" />
            <div className="min-w-0">
              {title && (
                <h2 className={`mb-6 ${headingClasses(options)}`} style={headingStyle(options)}>
                  {title}
                </h2>
              )}
              {body && (
                <div className={`${bodyClasses(options)} leading-relaxed`} style={textStyle(options)}>
                  {body.split('\n').map((paragraph, i) =>
                    paragraph.trim() ? (
                      <p key={i} className="mb-3 last:mb-0">
                        {paragraph}
                      </p>
                    ) : null,
                  )}
                </div>
              )}
            </div>
          </div>
        ) : (
          <div className="min-w-0">
            {title && (
              <h2 className={`mb-6 ${headingClasses(options)}`} style={headingStyle(options)}>
                {title}
              </h2>
            )}
            {body && (
              <div className={`${bodyClasses(options)} leading-relaxed`} style={textStyle(options)}>
                {body.split('\n').map((paragraph, i) =>
                  paragraph.trim() ? (
                    <p key={i} className="mb-3 last:mb-0">
                      {paragraph}
                    </p>
                  ) : null,
                )}
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  )
}
