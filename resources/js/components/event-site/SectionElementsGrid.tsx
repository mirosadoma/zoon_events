import { useRef, type CSSProperties, type ReactNode } from 'react'
import { useDroppable } from '@dnd-kit/core'
import SectionElementEditFrame from './builder/SectionElementEditFrame'
import SectionElementFreeformFrame from './builder/SectionElementFreeformFrame'
import {
  isFreeformSection,
  resolveFreeformHeightCss,
  resolveFreeformPlacement,
  freeformStyle,
} from '@/lib/sectionFreeformLayout'
import {
  bodyClasses,
  buttonStyle,
  elementInlineStyle,
  elementScopeClass,
  elementStyleClasses,
  headingClasses,
  headingStyle,
  textStyle,
} from '@/lib/siteBlockStyle'
import { clampColSpan, clampColStart } from '@/lib/sectionElementGrid'
import { isExternalHref, resolveSiteHref } from '@/lib/siteHref'

export type SectionElementData = {
  id?: string
  kind?: string
  col_span?: number
  col_start?: number
  order?: number
  x_pct?: number
  y_pct?: number
  width_pct?: number
  height_pct?: number
  z_index?: number
  align?: 'start' | 'center' | 'end'
  v_align?: 'start' | 'center' | 'end'
  title?: string
  body?: string
  label?: string
  href?: string
  src?: string
  alt?: string
  style?: Record<string, unknown>
}

export type SectionGridEdit = {
  blockId: string
  locale: 'en' | 'ar'
  selectedElementId: string | null
  onSelectElement: (id: string) => void
  onElementChange: (id: string, patch: Partial<SectionElementData>) => void
  onMoveElementUp?: (id: string) => void
  onMoveElementDown?: (id: string) => void
  onDuplicateElement?: (id: string) => void
  onRemoveElement?: (id: string) => void
}

type Props = {
  elements: SectionElementData[]
  options: Record<string, unknown>
  registerUrl?: string
  siteBaseUrl?: string
  gapClass: string
  edit?: SectionGridEdit
}

function gridCellStyle(
  element: { col_span?: number; col_start?: number },
  spacingStyle: CSSProperties,
): CSSProperties {
  const span = clampColSpan(Number(element.col_span ?? 6), element.col_start ?? 1)
  const start =
    element.col_start !== undefined && element.col_start >= 1
      ? clampColStart(element.col_start, span)
      : undefined
  const gc = start !== undefined ? `${start} / span ${span}` : `span ${span} / span ${span}`

  return {
    ...spacingStyle,
    // Always place on the 12-col grid (builder + public). Mobile stacks via CSS class.
    gridColumn: gc,
    ['--section-gc' as string]: gc,
    position: 'relative',
    left: 'auto',
    top: 'auto',
    width: 'auto',
    maxWidth: '100%',
    height: 'auto',
  }
}

function alignClass(align?: string): string {
  if (align === 'start') return 'text-start'
  if (align === 'center') return 'text-center'
  if (align === 'end') return 'text-end'
  return ''
}

function vAlignClass(vAlign?: string): string {
  if (vAlign === 'start') return 'self-start'
  if (vAlign === 'center') return 'self-center'
  if (vAlign === 'end') return 'self-end'
  return ''
}

function resolveHref(href: string, registerUrl?: string, siteBaseUrl?: string): string {
  return resolveSiteHref(href, { registerUrl, siteBaseUrl })
}

function videoEmbedUrl(url: string): string | null {
  if (!url) return null
  const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w-]+)/)
  if (yt) return `https://www.youtube-nocookie.com/embed/${yt[1]}`
  const vimeo = url.match(/vimeo\.com\/(\d+)/)
  if (vimeo) return `https://player.vimeo.com/video/${vimeo[1]}`
  if (url.includes('/embed/')) return url
  return null
}

function renderElementBody(
  element: SectionElementData,
  index: number,
  options: Record<string, unknown>,
  registerUrl?: string,
  locale: 'en' | 'ar' = 'en',
  siteBaseUrl?: string,
): ReactNode {
  const kind = element.kind ?? 'text'
  const elStyle = element.style

  if (kind === 'spacer') {
    return null
  }

  if (kind === 'hero') {
    return (
      <div
        className="flex min-h-64 flex-col justify-center rounded-2xl bg-[var(--brand)] p-8 text-white shadow-xl md:p-12"
        style={elementInlineStyle(elStyle)}
      >
        <h2 className={`m-0 text-3xl font-bold ${headingClasses(elStyle || options)}`} style={headingStyle(elStyle || options)}>
          {element.title || 'Event'}
        </h2>
        {element.body && (
          <p className={`mt-3 text-white/80 ${bodyClasses(elStyle || options)}`} style={textStyle(elStyle || options)}>
            {element.body}
          </p>
        )}
      </div>
    )
  }

  if (kind === 'box') {
    return (
      <div
        className={`min-h-32 rounded-xl border border-[var(--border)] bg-muted/30 p-6 shadow-sm ${bodyClasses(elStyle || options)}`}
        style={elementInlineStyle(elStyle)}
      >
        {element.body || (locale === 'ar' ? 'صندوق نص' : 'Box content')}
      </div>
    )
  }

  if (kind === 'shape') {
    return (
      <div
        className="mx-auto aspect-square w-32 rounded-xl bg-[var(--brand)]"
        style={elementInlineStyle(elStyle)}
      />
    )
  }

  if (kind === 'details') {
    return (
      <div className="rounded-2xl border border-[var(--border)] bg-background p-6 shadow-lg md:p-10">
        <h2 className="m-0 text-2xl font-bold">{locale === 'ar' ? 'تفاصيل الحدث' : 'Event details'}</h2>
        <div className="mt-6 grid gap-4 md:grid-cols-3">
          {[
            { label: locale === 'ar' ? 'يبدأ' : 'Starts', value: locale === 'ar' ? 'التاريخ' : 'Date' },
            { label: locale === 'ar' ? 'ينتهي' : 'Ends', value: locale === 'ar' ? 'التاريخ' : 'Date' },
            { label: locale === 'ar' ? 'الموقع' : 'Location', value: locale === 'ar' ? 'يُحدد لاحقاً' : 'To be announced' },
          ].map((item) => (
            <div key={item.label} className="rounded-xl border border-[var(--border)] bg-muted/40 p-4">
              <small className="block text-xs font-bold text-muted-foreground">{item.label}</small>
              <strong className="mt-1 block text-sm">{item.value}</strong>
            </div>
          ))}
        </div>
      </div>
    )
  }

  if (kind === 'image') {
    return element.src ? (
      <img src={element.src} alt={element.alt ?? ''} className="h-auto w-full rounded-lg object-cover" />
    ) : (
      <div className="flex aspect-video items-center justify-center rounded-lg border border-dashed border-[var(--border)] text-sm opacity-60">
        Image
      </div>
    )
  }

  if (kind === 'button') {
    const btnStyle = { ...buttonStyle(elStyle || options), ...elementInlineStyle(elStyle) }
    const hasCustom = Object.keys(btnStyle).length > 0
    const href = resolveHref(String(element.href ?? '#'), registerUrl, siteBaseUrl)
    const external = isExternalHref(href)
    return (
      <a
        href={href}
        className={`inline-flex rounded-md px-5 py-3 text-sm font-semibold ${hasCustom ? '' : 'bg-[var(--brand)] text-white'}`}
        style={hasCustom ? btnStyle : undefined}
        {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
      >
        {element.label || 'Learn more'}
      </a>
    )
  }

  if (kind === 'card') {
    return (
      <div className="rounded-xl border border-[var(--border)] bg-background/80 p-5 shadow-sm">
        {element.title && (
          <h3 className={`mb-2 text-lg font-semibold ${headingClasses(elStyle || options)}`} style={headingStyle(elStyle || options)}>
            {element.title}
          </h3>
        )}
        {element.body && (
          <p className={`${bodyClasses(elStyle || options)} leading-relaxed whitespace-pre-wrap`} style={textStyle(elStyle || options)}>
            {element.body}
          </p>
        )}
      </div>
    )
  }

  if (kind === 'divider') {
    const lineColor = typeof elStyle?.accent_color === 'string' ? elStyle.accent_color : undefined
    return (
      <hr
        className="m-0 w-full border-0 border-t opacity-80"
        style={{ borderTopColor: lineColor ?? 'var(--border)', borderTopWidth: 1 }}
      />
    )
  }

  if (kind === 'quote') {
    return (
      <blockquote className={`rounded-lg border-l-4 border-[var(--brand)] bg-muted/30 px-4 py-3 italic ${bodyClasses(elStyle || options)}`} style={textStyle(elStyle || options)}>
        {element.body}
      </blockquote>
    )
  }

  if (kind === 'video') {
    const embed = videoEmbedUrl(String(element.href ?? ''))
    return embed ? (
      <div className="aspect-video overflow-hidden rounded-xl bg-black/10">
        <iframe src={embed} className="h-full w-full" allowFullScreen title="Video" />
      </div>
    ) : (
      <div className="flex aspect-video items-center justify-center rounded-xl border border-dashed border-[var(--border)] text-sm opacity-60">
        Video URL
      </div>
    )
  }

  if (kind === 'list') {
    const items = String(element.body ?? '').split('\n').filter((line) => line.trim())
    return (
      <ul className={`list-disc space-y-1 ps-5 ${bodyClasses(elStyle || options)}`} style={textStyle(elStyle || options)}>
        {items.map((item, i) => <li key={i}>{item}</li>)}
      </ul>
    )
  }

  if (kind === 'icon') {
    return <span className="text-4xl leading-none" style={textStyle(elStyle || options)}>{element.label || '★'}</span>
  }

  if (kind === 'html') {
    return <div className="prose prose-sm max-w-none dark:prose-invert" dangerouslySetInnerHTML={{ __html: String(element.body ?? '') }} />
  }

  if (kind === 'heading') {
    return (
      <h3 className={headingClasses(elStyle || options)} style={headingStyle(elStyle || options)}>
        {element.title || element.body}
      </h3>
    )
  }

  return (
    <p className={`${bodyClasses(elStyle || options)} leading-relaxed whitespace-pre-wrap opacity-90`} style={textStyle(elStyle || options)}>
      {element.body}
    </p>
  )
}

export default function SectionElementsGrid({ elements, options, registerUrl, siteBaseUrl, gapClass, edit }: Props) {
  const gridRef = useRef<HTMLDivElement>(null)
  const canvasRef = useRef<HTMLDivElement>(null)
  const isEdit = Boolean(edit)
  const freeform = isFreeformSection(options)
  const freeformHeightCss = resolveFreeformHeightCss(options)
  const sortedElements = [...elements].sort((a, b) => (a.order ?? 0) - (b.order ?? 0))
  const elementCount = sortedElements.length

  const { setNodeRef: dropRef, isOver: isDropOver } = useDroppable({
    id: edit ? `section-drop-${edit.blockId}` : 'section-drop-disabled',
    data: edit ? { kind: 'section', blockId: edit.blockId } : undefined,
    disabled: !isEdit,
  })

  return (
    <div
      ref={dropRef}
      className={`relative rounded-lg transition-colors ${isDropOver ? 'ring-2 ring-violet-400/60 ring-offset-2 ring-offset-transparent bg-violet-500/5' : ''}`}
    >
      {isEdit && !freeform && (
        <div
          className="pointer-events-none absolute inset-0 z-10 opacity-30"
          style={{
            backgroundImage:
              'linear-gradient(to right, rgb(139 92 246 / 0.2) 1px, transparent 1px)',
            backgroundSize: 'calc(100% / 12) 100%',
          }}
        />
      )}

      {isEdit && freeform && (
        <div
          className="pointer-events-none absolute inset-0 z-10 opacity-40"
          style={{
            backgroundImage:
              'linear-gradient(to right, rgb(139 92 246 / 0.15) 1px, transparent 1px), linear-gradient(to bottom, rgb(139 92 246 / 0.15) 1px, transparent 1px)',
            backgroundSize: '24px 24px',
          }}
        />
      )}

      <div
        ref={(node) => {
          gridRef.current = node
          canvasRef.current = node
        }}
        className={`relative ${
          freeform
            ? 'overflow-visible'
            : `${gapClass} min-h-[120px]`
        } ${isEdit ? 'z-20' : ''}`}
        style={
          freeform
            ? ({
                position: 'relative',
                height: freeformHeightCss,
                minHeight: freeformHeightCss,
              } as CSSProperties)
            : ({
                display: 'grid',
                gridTemplateColumns: 'repeat(12, minmax(0, 1fr))',
                alignItems: 'start',
              } as CSSProperties)
        }
      >
        {sortedElements.map((element, index) => {
          const kind = element.kind ?? 'text'
          const elStyle = element.style
          const span = Number(element.col_span ?? 6)
          const freeformPlacement = freeform ? resolveFreeformPlacement(element, index) : null
          const alignClasses = `${alignClass(element.align)} ${vAlignClass(element.v_align)} ${elementStyleClasses(elStyle)}`
          const spacingStyle = elementInlineStyle(elStyle)
          const cellStyle: CSSProperties = freeformPlacement
            ? { ...freeformStyle(freeformPlacement), ...spacingStyle }
            : gridCellStyle(element, spacingStyle)
          const gridCellClass = !freeform ? 'section-grid-cell' : ''
          const elScope = element.id ? elementScopeClass(element.id) : ''
          const elCustomCss = typeof elStyle?.custom_css === 'string' ? elStyle.custom_css.trim() : ''
          const elCustomClass = typeof elStyle?.custom_class === 'string' ? elStyle.custom_class.trim() : ''
          const innerClass = `${elScope} ${elCustomClass} ${kind === 'spacer' ? 'min-h-6' : ''} ${kind === 'button' ? 'flex items-center' : ''} ${kind === 'icon' ? 'flex items-center justify-center' : ''}`.trim()
          const chromeStyle = elCustomCss && elScope ? <style>{`.${elScope} { ${elCustomCss} }`}</style> : null
          const elementId = element.id ?? `idx_${index}`
          const body = renderElementBody(element, index, options, registerUrl, edit?.locale ?? 'en', siteBaseUrl)

          const inner = (
            <div className={innerClass}>
              {chromeStyle}
              {body}
            </div>
          )

          if (!edit || !element.id) {
            return (
              <div key={elementId} className={`${alignClasses} ${gridCellClass}`.trim()} style={cellStyle}>
                {inner}
              </div>
            )
          }

          if (freeform && freeformPlacement) {
            return (
              <div key={element.id} className={alignClasses} style={cellStyle}>
                <SectionElementFreeformFrame
                  elementId={element.id}
                  blockId={edit.blockId}
                  kind={kind}
                  placement={freeformPlacement}
                  selected={edit.selectedElementId === element.id}
                  locale={edit.locale}
                  canMoveUp={index > 0}
                  canMoveDown={index < elementCount - 1}
                  canvasRef={canvasRef}
                  onSelect={() => edit.onSelectElement(element.id!)}
                  onChange={(patch) => edit.onElementChange(element.id!, patch)}
                  onMoveUp={edit.onMoveElementUp ? () => edit.onMoveElementUp!(element.id!) : undefined}
                  onMoveDown={edit.onMoveElementDown ? () => edit.onMoveElementDown!(element.id!) : undefined}
                  onDuplicate={edit.onDuplicateElement ? () => edit.onDuplicateElement!(element.id!) : undefined}
                  onRemove={edit.onRemoveElement ? () => edit.onRemoveElement!(element.id!) : undefined}
                >
                  {inner}
                </SectionElementFreeformFrame>
              </div>
            )
          }

          return (
            <div key={element.id} className={`${alignClasses} ${gridCellClass}`.trim()} style={cellStyle}>
              <SectionElementEditFrame
                elementId={element.id}
                blockId={edit.blockId}
                kind={kind}
                colSpan={span}
                colStart={element.col_start}
                align={element.align ?? 'start'}
                selected={edit.selectedElementId === element.id}
                locale={edit.locale}
                canMoveUp={index > 0}
                canMoveDown={index < elementCount - 1}
                gridRef={gridRef}
                onSelect={() => edit.onSelectElement(element.id!)}
                onChange={(patch) => edit.onElementChange(element.id!, patch)}
                onMoveUp={edit.onMoveElementUp ? () => edit.onMoveElementUp!(element.id!) : undefined}
                onMoveDown={edit.onMoveElementDown ? () => edit.onMoveElementDown!(element.id!) : undefined}
                onDuplicate={edit.onDuplicateElement ? () => edit.onDuplicateElement!(element.id!) : undefined}
                onRemove={edit.onRemoveElement ? () => edit.onRemoveElement!(element.id!) : undefined}
              >
                {inner}
              </SectionElementEditFrame>
            </div>
          )
        })}
      </div>

      {isEdit && sortedElements.length === 0 && (
        <p className="absolute inset-0 z-30 flex items-center justify-center text-sm text-violet-300/70">
          {freeform
            ? edit!.locale === 'ar'
              ? 'أضف عناصر واسحبها بحرية داخل السيكشن'
              : 'Add elements and drag them freely inside the section'
            : edit!.locale === 'ar'
              ? 'أضف عناصر من لوحة المحتوى → Grid elements'
              : 'Add elements from the panel → Grid elements'}
        </p>
      )}
    </div>
  )
}
