import SectionElementsGrid, { type SectionGridEdit, type SectionElementData } from '../SectionElementsGrid'
import {
  blockShellClasses,
  blockShellOverlay,
  blockShellStyle,
  bodyClasses,
  containerMaxWidthClass,
  headingClasses,
  headingStyle,
  sectionPadXClass,
  textStyle,
} from '@/lib/siteBlockStyle'
import { backgroundOverlayStyle, backgroundStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'

type SectionElement = SectionElementData

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs?: Record<string, unknown>
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
  sectionEdit?: SectionGridEdit
}

function alignClass(align?: string): string {
  if (align === 'start' || align === 'left') return 'text-start'
  if (align === 'center') return 'text-center'
  if (align === 'end' || align === 'right') return 'text-end'
  return ''
}

export default function SectionRenderer({ content, options, registerUrl, siteBaseUrl, sectionEdit }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const elements = Array.isArray(content.elements) ? (content.elements as SectionElement[]) : []
  const gap = typeof options.gap === 'string' ? options.gap : 'md'
  const padding = typeof options.padding === 'string' ? options.padding : 'lg'
  const contentAlign = typeof options.content_align === 'string' ? options.content_align : undefined
  const sectionAlign = typeof options.align === 'string' ? options.align : 'center'

  const backgroundPreset = typeof options.background_preset === 'string' ? options.background_preset : ''
  const bgObject = options.background
  const hasBgObject = bgObject && typeof bgObject === 'object' && !Array.isArray(bgObject) && (bgObject as SiteBackground).type && (bgObject as SiteBackground).type !== 'none'

  const presetClass =
    !hasBgObject && backgroundPreset === 'muted'
      ? 'bg-muted/40'
      : !hasBgObject && backgroundPreset === 'brand'
        ? 'bg-[var(--brand)] text-white'
        : !hasBgObject && backgroundPreset === 'dark'
          ? 'bg-[var(--ink)] text-white'
          : ''

  const padClass =
    padding === 'sm' ? 'py-8' : padding === 'md' ? 'py-12' : padding === 'xl' ? 'py-24' : 'py-16'

  const gapClass = gap === 'sm' ? 'gap-3' : gap === 'lg' ? 'gap-8' : 'gap-5'

  const titleAlign =
    sectionAlign === 'start' || sectionAlign === 'left'
      ? 'text-start'
      : sectionAlign === 'end' || sectionAlign === 'right'
        ? 'text-end'
        : 'text-center mx-auto'
  const containerAlign = contentAlign ? alignClass(contentAlign) : ''

  const shellCls = blockShellClasses(options)
  const shellStyle = blockShellStyle(options)
  const shellOverlay = blockShellOverlay(options)
  const innerBg = hasBgObject ? backgroundStyle(bgObject as SiteBackground) : {}
  const innerOverlay = hasBgObject ? backgroundOverlayStyle(bgObject as SiteBackground) : null

  const sortedElements = [...elements].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))

  return (
    <section
      className={`relative ${presetClass} ${!hasBgObject && !backgroundPreset ? 'bg-background' : ''} ${padClass} ${sectionPadXClass(options)} ${shellCls} ${containerAlign}`.trim()}
      style={{ ...shellStyle, ...innerBg }}
    >
      {(shellOverlay || innerOverlay) && <div style={shellOverlay || innerOverlay} className="pointer-events-none absolute inset-0" />}
      <div className={`relative ${containerMaxWidthClass(options)} ${options.width === 'full' ? 'px-6' : ''}`.trim()}>
        {(title || subtitle) && (
          <div className={`mb-8 w-[min(92%,48rem)] ${titleAlign}`}>
            {title && (
              <h2 className={`${headingClasses(options)} mb-0`} style={headingStyle(options)}>
                {title}
              </h2>
            )}
            {subtitle && (
              <p className={`mt-3 ${bodyClasses(options)} opacity-80`} style={textStyle(options)}>
                {subtitle}
              </p>
            )}
          </div>
        )}

        <SectionElementsGrid
          elements={sortedElements}
          options={options}
          registerUrl={registerUrl}
          siteBaseUrl={siteBaseUrl}
          gapClass={gapClass}
          edit={sectionEdit}
        />
      </div>
    </section>
  )
}
