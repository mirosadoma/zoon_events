import { useState, useCallback, useEffect, useRef, type ReactNode, type CSSProperties, type PointerEvent as ReactPointerEvent } from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'
import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'
import { containerMaxWidthClass, sectionPadXClass } from '@/lib/siteBlockStyle'
import { formatCssLength, parseCssLength } from '@/lib/cssLength'
import { isExternalHref, resolveSiteHref } from '@/lib/siteHref'
import {
  isCarouselDisplay,
  normalizeShowcaseItems,
  type ArrowsStyle,
  type DotsStyle,
  type ShowcaseItem,
} from '@/lib/showcaseCarousel'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
}

function alignClass(align?: string): string {
  if (align === 'start' || align === 'left') return 'items-start text-start'
  if (align === 'end' || align === 'right') return 'items-end text-end'
  return 'items-center text-center'
}

function vAlignClass(align?: string): string {
  if (align === 'start') return 'justify-start'
  if (align === 'end') return 'justify-end'
  return 'justify-center'
}

function SlideContent({
  item,
  locale,
  registerUrl,
  siteBaseUrl,
}: {
  item: ShowcaseItem
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
}) {
  const colorStyle = item.text_color ? { color: item.text_color } : undefined
  const mutedStyle = item.text_color ? { color: item.text_color, opacity: 0.85 } : undefined

  const extra = item.extra_text?.trim() ? (
    <p className="text-sm md:text-base leading-relaxed" style={mutedStyle}>
      {item.extra_text}
    </p>
  ) : null

  const divider = item.show_divider ? (
    <div
      className="my-1 h-px w-16 max-w-full opacity-70"
      style={{ backgroundColor: item.text_color || 'currentColor' }}
    />
  ) : null

  const title = item.title?.trim() ? (
    <h3 className="text-2xl font-bold md:text-4xl" style={colorStyle}>
      {item.title}
    </h3>
  ) : null

  const description = item.description?.trim() ? (
    <p className="w-full text-sm md:text-lg leading-relaxed" style={mutedStyle}>
      {item.description}
    </p>
  ) : null

  const buttonHref = item.button_href?.trim()
    ? resolveSiteHref(item.button_href, { registerUrl, siteBaseUrl })
    : ''
  const button =
    item.button_label?.trim() && item.button_href?.trim() ? (
      <a
        href={buttonHref}
        className="button-primary inline-flex"
        {...(isExternalHref(buttonHref) ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
      >
        {item.button_label}
      </a>
    ) : null

  const blocks: Array<{ key: string; node: ReactNode }> = []

  const pushExtra = (pos: string) => {
    if (item.extra_text_position === pos && extra) blocks.push({ key: `extra-${pos}`, node: extra })
  }
  const pushDivider = (pos: string) => {
    if (item.divider_position === pos && divider) blocks.push({ key: `div-${pos}`, node: divider })
  }

  pushExtra('above_title')
  if (title) blocks.push({ key: 'title', node: title })
  pushExtra('below_title')
  pushDivider('after_title')
  if (description) blocks.push({ key: 'desc', node: description })
  pushExtra('below_description')
  pushDivider('after_description')
  pushDivider('after_extra')
  pushDivider('before_button')
  if (button) blocks.push({ key: 'btn', node: button })
  pushExtra('below_button')

  if (blocks.length === 0) {
    return (
      <p className="text-sm opacity-60" style={colorStyle}>
        {locale === 'ar' ? 'شريحة فارغة' : 'Empty slide'}
      </p>
    )
  }

  const align = item.content_align ?? 'center'
  const boxAlign =
    align === 'start' || align === 'left'
      ? 'me-auto items-stretch text-start'
      : align === 'end' || align === 'right'
        ? 'ms-auto items-stretch text-end'
        : 'mx-auto items-stretch text-center'

  return (
    <div className={`flex w-[min(92%,48rem)] flex-col gap-3 ${boxAlign}`}>
      {blocks.map((b) => (
        <div key={b.key} className="w-full flex flex-col gap-3" style={{ alignItems: 'inherit' }}>
          {b.node}
        </div>
      ))}
    </div>
  )
}

function SlideCard({
  item,
  locale,
  variant,
  height,
  imageFit = 'cover',
  edgeToEdge = false,
  registerUrl,
  siteBaseUrl,
}: {
  item: ShowcaseItem
  locale: 'en' | 'ar'
  variant: 'grid' | 'carousel'
  height?: string
  imageFit?: 'cover' | 'contain' | 'fill'
  edgeToEdge?: boolean
  registerUrl?: string
  siteBaseUrl?: string
}) {
  const layout = item.layout ?? 'content'
  const bg = item.background
  const bgStyle = backgroundStyle(bg)
  const overlay = backgroundOverlayStyle(bg)
  const fallbackMin = variant === 'carousel' ? '360px' : '280px'
  const itemMin = formatCssLength(
    parseCssLength(item.min_height, item.min_height_unit, { value: 360, unit: 'px' }),
  )
  const resolvedHeight = height && height.trim() !== '' ? height : itemMin || fallbackMin
  // Carousel uses a fixed height so every slide matches; grid keeps flexible min-height.
  const sizeStyle: CSSProperties =
    variant === 'carousel'
      ? { height: resolvedHeight, minHeight: resolvedHeight }
      : { minHeight: resolvedHeight }
  const fitClass =
    imageFit === 'contain' ? 'object-contain' : imageFit === 'fill' ? 'object-fill' : 'object-cover'
  const radiusClass = edgeToEdge ? 'rounded-none' : 'rounded-xl'

  if (layout === 'image_only') {
    return (
      <div className={`overflow-hidden bg-muted/30 ${radiusClass}`} style={sizeStyle}>
        {item.src ? (
          <img
            src={item.src}
            alt={item.title || ''}
            className={`h-full w-full ${fitClass}`}
            style={{ height: '100%', width: '100%' }}
          />
        ) : (
          <div className="flex h-full items-center justify-center text-sm text-muted-foreground" style={sizeStyle}>
            {locale === 'ar' ? 'لا توجد صورة' : 'No image'}
          </div>
        )}
      </div>
    )
  }

  if (layout === 'image_overlay') {
    return (
      <div className={`relative overflow-hidden bg-muted/30 ${radiusClass}`} style={sizeStyle}>
        {item.src ? (
          <img src={item.src} alt="" className={`absolute inset-0 h-full w-full ${fitClass}`} />
        ) : (
          <div className="absolute inset-0" style={bgStyle} />
        )}
        <div className="absolute inset-0 bg-black/45" />
        <div
          className={`relative z-[1] flex h-full flex-col px-[4%] py-[5%] md:px-[5%] md:py-10 ${vAlignClass(item.content_v_align)} ${alignClass(item.content_align)}`}
          style={sizeStyle}
        >
          <SlideContent
            item={{ ...item, text_color: item.text_color || '#ffffff' }}
            locale={locale}
            registerUrl={registerUrl}
            siteBaseUrl={siteBaseUrl}
          />
        </div>
      </div>
    )
  }

  // content layout: solid / gradient / image background + texts
  return (
    <div className={`relative overflow-hidden ${radiusClass}`} style={{ ...bgStyle, ...sizeStyle }}>
      {overlay && <div style={overlay} />}
      <div
        className={`relative z-[1] flex h-full flex-col px-[4%] py-[5%] md:px-[5%] md:py-10 ${vAlignClass(item.content_v_align)} ${alignClass(item.content_align)}`}
        style={sizeStyle}
      >
        <SlideContent item={item} locale={locale} registerUrl={registerUrl} siteBaseUrl={siteBaseUrl} />
      </div>
    </div>
  )
}

export default function ImageShowcaseRenderer({ content, options, locale, registerUrl, siteBaseUrl }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const items = normalizeShowcaseItems(content.items)
  const display = typeof options.display === 'string' ? options.display : 'grid'
  const columns = typeof options.columns === 'number' ? options.columns : 3
  const autoplay = options.autoplay === true
  const autoplayMs = typeof options.autoplay_ms === 'number' && options.autoplay_ms >= 1500 ? options.autoplay_ms : 4000
  const showArrows = options.show_arrows !== false
  const showDots = options.show_dots !== false
  const arrowsStyle = (typeof options.arrows_style === 'string' ? options.arrows_style : 'circle') as ArrowsStyle
  const dotsStyle = (typeof options.dots_style === 'string' ? options.dots_style : 'dots') as DotsStyle
  const dotsColor = typeof options.dots_color === 'string' ? options.dots_color : 'rgba(255,255,255,0.35)'
  const dotsActiveColor = typeof options.dots_active_color === 'string' ? options.dots_active_color : '#8b5cf6'
  const arrowsColor = typeof options.arrows_color === 'string' ? options.arrows_color : '#ffffff'
  const pauseOnHover = options.pause_on_hover !== false
  const loop = options.loop !== false
  const dragToSlide = options.drag_to_slide === true
  const background = options.background as SiteBackground | undefined
  const slideHeight = formatCssLength(
    parseCssLength(options.slide_height, options.slide_height_unit, { value: 400, unit: 'px' }),
  )
  const imageFitRaw = typeof options.image_fit === 'string' ? options.image_fit : 'cover'
  const imageFit =
    imageFitRaw === 'contain' || imageFitRaw === 'fill' || imageFitRaw === 'cover'
      ? imageFitRaw
      : 'cover'
  const isFullWidth = options.width === 'full'
  const widthClass = containerMaxWidthClass(options)
  const padXClass = sectionPadXClass(options)

  const bgStyle = backgroundStyle(background)
  const overlayStyle = backgroundOverlayStyle(background)
  const hasBackground = Boolean(background?.type && background.type !== 'none')
  const carousel = isCarouselDisplay(display)

  const [currentSlide, setCurrentSlide] = useState(0)
  const [paused, setPaused] = useState(false)
  const [dragging, setDragging] = useState(false)
  const [dragOffset, setDragOffset] = useState(0)
  const intervalRef = useRef<number>()
  const trackRef = useRef<HTMLDivElement>(null)
  const dragStartX = useRef(0)
  const trackWidth = useRef(1)
  const dragMoved = useRef(false)

  const goTo = useCallback(
    (index: number) => {
      if (items.length === 0) return
      if (loop) {
        setCurrentSlide(((index % items.length) + items.length) % items.length)
      } else {
        setCurrentSlide(Math.max(0, Math.min(items.length - 1, index)))
      }
    },
    [items.length, loop],
  )

  const nextSlide = useCallback(() => goTo(currentSlide + 1), [currentSlide, goTo])
  const prevSlide = useCallback(() => goTo(currentSlide - 1), [currentSlide, goTo])

  useEffect(() => {
    if (!carousel || !autoplay || items.length < 2 || paused || dragging) return
    intervalRef.current = window.setInterval(nextSlide, autoplayMs)
    return () => clearInterval(intervalRef.current)
  }, [carousel, autoplay, items.length, nextSlide, autoplayMs, paused, dragging])

  const endDrag = useCallback(
    (clientX: number) => {
      if (!dragging) return
      const dx = clientX - dragStartX.current
      const threshold = Math.max(48, trackWidth.current * 0.18)
      if (dragMoved.current) {
        if (dx <= -threshold) nextSlide()
        else if (dx >= threshold) prevSlide()
      }
      setDragging(false)
      setDragOffset(0)
      dragMoved.current = false
    },
    [dragging, nextSlide, prevSlide],
  )

  const onPointerDown = useCallback(
    (event: ReactPointerEvent<HTMLDivElement>) => {
      if (!dragToSlide || items.length < 2) return
      const target = event.target as HTMLElement | null
      if (target?.closest('a, button, input, textarea, select, label')) return

      trackWidth.current = trackRef.current?.offsetWidth || 1
      dragStartX.current = event.clientX
      dragMoved.current = false
      setDragging(true)
      setDragOffset(0)
      setPaused(true)
      event.currentTarget.setPointerCapture(event.pointerId)
    },
    [dragToSlide, items.length],
  )

  const onPointerMove = useCallback(
    (event: ReactPointerEvent<HTMLDivElement>) => {
      if (!dragging) return
      const dx = event.clientX - dragStartX.current
      if (Math.abs(dx) > 4) dragMoved.current = true
      // Resist overscroll slightly at ends when loop is off
      let offset = dx
      if (!loop) {
        if ((currentSlide === 0 && dx > 0) || (currentSlide === items.length - 1 && dx < 0)) {
          offset = dx * 0.35
        }
      }
      setDragOffset(offset)
    },
    [dragging, loop, currentSlide, items.length],
  )

  const onPointerUp = useCallback(
    (event: ReactPointerEvent<HTMLDivElement>) => {
      if (!dragging) return
      try {
        event.currentTarget.releasePointerCapture(event.pointerId)
      } catch {
        // ignore if already released
      }
      endDrag(event.clientX)
    },
    [dragging, endDrag],
  )

  const onPointerCancel = useCallback(
    (event: ReactPointerEvent<HTMLDivElement>) => {
      if (!dragging) return
      endDrag(event.clientX)
    },
    [dragging, endDrag],
  )

  // Prevent accidental click-through after a drag
  const onClickCapture = useCallback(
    (event: React.MouseEvent) => {
      if (dragMoved.current) {
        event.preventDefault()
        event.stopPropagation()
        dragMoved.current = false
      }
    },
    [],
  )

  const columnClass =
    columns === 2
      ? 'grid-cols-1 sm:grid-cols-2'
      : columns === 4
        ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'
        : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'

  const arrowBtnClass =
    arrowsStyle === 'square'
      ? 'rounded-lg'
      : arrowsStyle === 'minimal'
        ? 'rounded-md bg-transparent shadow-none hover:bg-black/25'
        : 'rounded-full'

  const arrowChrome =
    arrowsStyle === 'minimal'
      ? ''
      : 'bg-black/45 shadow-lg backdrop-blur-sm hover:bg-black/65'

  if (items.length === 0) {
    return (
      <section className={`relative py-16 ${padXClass}`} style={bgStyle}>
        {overlayStyle && <div style={overlayStyle} />}
        <div className={`relative text-center ${widthClass}`}>
          {title && <h2 className="mb-4 text-3xl font-bold">{title}</h2>}
          <p className="text-muted-foreground">{locale === 'ar' ? 'لا توجد عناصر لعرضها.' : 'No items to display.'}</p>
        </div>
      </section>
    )
  }

  return (
    <section className={`relative ${carousel && isFullWidth ? 'py-0' : 'py-16'} ${padXClass}`} style={bgStyle}>
      {overlayStyle && <div style={overlayStyle} />}
      <div className={`relative ${widthClass} ${hasBackground && background?.type === 'image' ? 'text-white' : ''}`}>
        {(title || subtitle) && (
          <div className={`mb-10 text-center ${isFullWidth ? 'px-6 pt-10' : ''}`}>
            {title && <h2 className="mb-3 text-3xl font-bold md:text-4xl">{title}</h2>}
            {subtitle && (
              <p className={`text-lg ${hasBackground && background?.type === 'image' ? 'text-white/80' : 'text-muted-foreground'}`}>
                {subtitle}
              </p>
            )}
          </div>
        )}

        {!carousel ? (
          <div className={`grid gap-6 ${columnClass} ${isFullWidth ? 'px-6' : ''}`}>
            {items.map((item) => (
              <SlideCard
                key={item.id}
                item={item}
                locale={locale}
                variant="grid"
                imageFit={imageFit}
                registerUrl={registerUrl}
                siteBaseUrl={siteBaseUrl}
              />
            ))}
          </div>
        ) : (
          <div
            className="relative"
            onMouseEnter={() => pauseOnHover && setPaused(true)}
            onMouseLeave={() => {
              if (pauseOnHover && !dragging) setPaused(false)
            }}
          >
            <div
              ref={trackRef}
              className={`overflow-hidden ${isFullWidth ? '' : 'rounded-xl'} ${
                dragToSlide && items.length > 1 ? 'touch-pan-y cursor-grab active:cursor-grabbing select-none' : ''
              }`}
              dir="ltr"
              onPointerDown={onPointerDown}
              onPointerMove={onPointerMove}
              onPointerUp={onPointerUp}
              onPointerCancel={onPointerCancel}
              onClickCapture={onClickCapture}
            >
              <div
                className={`flex ${dragging ? '' : 'transition-transform duration-500 ease-in-out'}`}
                style={{
                  transform: `translateX(calc(-${currentSlide * 100}% + ${dragOffset}px))`,
                }}
              >
                {items.map((item) => (
                  <div
                    key={item.id}
                    className="w-full flex-shrink-0"
                    dir={locale === 'ar' ? 'rtl' : 'ltr'}
                    lang={locale}
                  >
                    <SlideCard
                      item={item}
                      locale={locale}
                      variant="carousel"
                      height={slideHeight}
                      imageFit={imageFit}
                      edgeToEdge={isFullWidth}
                      registerUrl={registerUrl}
                      siteBaseUrl={siteBaseUrl}
                    />
                  </div>
                ))}
              </div>
            </div>

            {items.length > 1 && showArrows && (
              <>
                <button
                  type="button"
                  onClick={prevSlide}
                  className={`absolute start-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center transition ${arrowChrome} ${arrowBtnClass}`}
                  style={{ color: arrowsColor }}
                  aria-label={locale === 'ar' ? 'السابق' : 'Previous'}
                >
                  <ChevronLeft className="h-5 w-5 rtl:rotate-180" />
                </button>
                <button
                  type="button"
                  onClick={nextSlide}
                  className={`absolute end-3 top-1/2 z-10 flex h-10 w-10 -translate-y-1/2 items-center justify-center transition ${arrowChrome} ${arrowBtnClass}`}
                  style={{ color: arrowsColor }}
                  aria-label={locale === 'ar' ? 'التالي' : 'Next'}
                >
                  <ChevronRight className="h-5 w-5 rtl:rotate-180" />
                </button>
              </>
            )}

            {items.length > 1 && showDots && (
              <div className="pointer-events-none absolute inset-x-0 bottom-3 z-10 flex justify-center px-3">
                <div className="pointer-events-auto flex flex-wrap items-center justify-center gap-2 rounded-full bg-black/35 px-3 py-1.5 backdrop-blur-sm">
                  {items.map((item, index) => {
                    const active = index === currentSlide
                    if (dotsStyle === 'numbers') {
                      return (
                        <button
                          key={item.id}
                          type="button"
                          onClick={() => goTo(index)}
                          className="flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-[11px] font-semibold transition"
                          style={{
                            backgroundColor: active ? dotsActiveColor : dotsColor,
                            color: active ? '#fff' : 'rgba(255,255,255,0.85)',
                          }}
                        >
                          {index + 1}
                        </button>
                      )
                    }
                    if (dotsStyle === 'bars') {
                      return (
                        <button
                          key={item.id}
                          type="button"
                          onClick={() => goTo(index)}
                          className="h-1.5 rounded-full transition-all"
                          style={{
                            width: active ? 28 : 14,
                            backgroundColor: active ? dotsActiveColor : dotsColor,
                          }}
                          aria-label={`Slide ${index + 1}`}
                        />
                      )
                    }
                    return (
                      <button
                        key={item.id}
                        type="button"
                        onClick={() => goTo(index)}
                        className="h-2.5 w-2.5 rounded-full transition ring-1 ring-white/20"
                        style={{ backgroundColor: active ? dotsActiveColor : dotsColor }}
                        aria-label={`Slide ${index + 1}`}
                      />
                    )
                  })}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </section>
  )
}
