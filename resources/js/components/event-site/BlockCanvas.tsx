import BuilderSectionFrame from './builder/BuilderSectionFrame'
import BlockDropSlot from './builder/BlockDropSlot'
import BlockCustomStyles from './builder/BlockCustomStyles'
import HeaderRenderer from './renderers/HeaderRenderer'
import HeroRenderer from './renderers/HeroRenderer'
import AboutRenderer from './renderers/AboutRenderer'
import AgendaRenderer from './renderers/AgendaRenderer'
import SpeakersRenderer from './renderers/SpeakersRenderer'
import VenueRenderer from './renderers/VenueRenderer'
import FaqRenderer from './renderers/FaqRenderer'
import SponsorsRenderer from './renderers/SponsorsRenderer'
import GalleryRenderer from './renderers/GalleryRenderer'
import SectionRenderer from './renderers/SectionRenderer'
import RegisterCtaRenderer from './renderers/RegisterCtaRenderer'
import FooterRenderer from './renderers/FooterRenderer'
import MediaTextRenderer from './renderers/MediaTextRenderer'
import ImageShowcaseRenderer from './renderers/ImageShowcaseRenderer'
import FormRenderer from './renderers/FormRenderer'
import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'
import { blockShellClasses, blockShellOverlay, blockShellStyle } from '@/lib/siteBlockStyle'
import {
  SortableContext,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'

export type CanvasBlock = {
  id: string
  type: string
  visible?: boolean
  page_id?: string
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
  options: Record<string, unknown>
  refs?: Record<string, unknown>
  resolved?: Record<string, unknown>
}

type BlockTypeLabels = Record<string, { en: string; ar: string }>

type SitePreviewData = {
  agenda?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
  speakers?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
  venue?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
}

type Props = {
  blocks: CanvasBlock[]
  locale: 'en' | 'ar'
  registerUrl?: string
  /** Public event site base path, e.g. `/en/e/phase-1-event` */
  siteBaseUrl?: string
  eventSlug?: string
  currentPageId?: string
  selectedId?: string | null
  onSelect?: (id: string) => void
  interactive?: boolean
  blockTypeLabels?: BlockTypeLabels
  /** Live event data for builder preview when blocks lack resolved payloads. */
  previewData?: SitePreviewData
  onDuplicate?: (id: string) => void
  onMoveUp?: (id: string) => void
  onMoveDown?: (id: string) => void
  onToggleVisibility?: (id: string) => void
  onRemove?: (id: string) => void
  onUpdateBlock?: (id: string, updates: Partial<CanvasBlock>) => void
  selectedSectionElementId?: string | null
  onSelectSectionElement?: (blockId: string, elementId: string | null) => void
  onAppendSection?: () => void
  onBlockSlotHover?: (index: number) => void
  onChangeSectionLayout?: (blockId: string) => void
  onOpenSectionStyle?: (blockId: string) => void
  onMoveSectionElementUp?: (blockId: string, elementId: string) => void
  onMoveSectionElementDown?: (blockId: string, elementId: string) => void
  onDuplicateSectionElement?: (blockId: string, elementId: string) => void
  onRemoveSectionElement?: (blockId: string, elementId: string) => void
}

function widthClass(width?: string): string {
  if (width === 'full') return 'mx-auto w-[min(100%,96rem)]'
  if (width === 'narrow') return 'mx-auto w-[min(92%,48rem)]'
  return 'mx-auto w-[min(94%,72rem)]'
}

function alignClass(align?: string): string {
  if (align === 'center') return 'text-center'
  if (align === 'end' || align === 'right') return 'text-end'
  if (align === 'start' || align === 'left') return 'text-start'
  return 'text-start'
}

function SortableBlockShell({
  blockId,
  children,
}: {
  blockId: string
  children: (props: {
    sortableHandleProps: { attributes: Record<string, unknown>; listeners: Record<string, unknown> | undefined }
    isDragging: boolean
  }) => React.ReactNode
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: blockId,
    data: { kind: 'canvas-block', blockId },
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.45 : 1,
  }

  return (
    <div ref={setNodeRef} style={style}>
      {children({
        sortableHandleProps: { attributes, listeners },
        isDragging,
      })}
    </div>
  )
}

export default function BlockCanvas({
  blocks,
  locale,
  registerUrl,
  siteBaseUrl,
  eventSlug,
  currentPageId = 'home',
  selectedId = null,
  onSelect,
  interactive = false,
  blockTypeLabels = {},
  previewData,
  onDuplicate,
  onMoveUp,
  onMoveDown,
  onToggleVisibility,
  onRemove,
  onUpdateBlock,
  selectedSectionElementId = null,
  onSelectSectionElement,
  onAppendSection,
  onBlockSlotHover,
  onChangeSectionLayout,
  onOpenSectionStyle,
  onMoveSectionElementUp,
  onMoveSectionElementDown,
  onDuplicateSectionElement,
  onRemoveSectionElement,
}: Props) {
  const canvasBlocks = interactive ? blocks : blocks.filter((block) => block.visible !== false)

  function previewFor(type: string): Record<string, unknown> {
    const bucket =
      type === 'agenda'
        ? previewData?.agenda
        : type === 'speakers'
          ? previewData?.speakers
          : type === 'venue'
            ? previewData?.venue
            : undefined
    if (!bucket) return {}
    const localized = locale === 'ar' ? bucket.ar : bucket.en
    return localized && typeof localized === 'object' ? localized : {}
  }

  function renderBlock(block: CanvasBlock) {
    if (!interactive && block.visible === false) return null
    const content = locale === 'ar' ? block.content_ar : block.content_en
    const blockWidth = typeof block.options.width === 'string' ? block.options.width : undefined
    const blockAlign = typeof block.options.content_align === 'string' ? block.options.content_align : undefined
    const resolved =
      block.resolved && Object.keys(block.resolved).length > 0
        ? block.resolved
        : previewFor(block.type)

    const common = {
      content,
      options: block.options,
      refs: block.refs ?? {},
      resolved,
      locale,
      registerUrl,
      siteBaseUrl,
    }

    let node = null
    switch (block.type) {
      case 'header':
        node = <HeaderRenderer {...common} />
        break
      case 'hero':
        node = <HeroRenderer {...common} />
        break
      case 'about':
        node = <div id="about"><AboutRenderer {...common} /></div>
        break
      case 'agenda':
        node = <div id="agenda"><AgendaRenderer {...common} /></div>
        break
      case 'speakers':
        node = <SpeakersRenderer {...common} />
        break
      case 'venue':
        node = <div id="venue"><VenueRenderer {...common} /></div>
        break
      case 'faq':
        node = <FaqRenderer {...common} />
        break
      case 'sponsors':
        node = <SponsorsRenderer {...common} />
        break
      case 'gallery':
        node = <GalleryRenderer {...common} />
        break
      case 'section':
        node = (
          <SectionRenderer
            {...common}
            sectionEdit={
              interactive && onUpdateBlock
                ? {
                    blockId: block.id,
                    locale,
                    selectedElementId: selectedId === block.id ? selectedSectionElementId : null,
                    onSelectElement: (elementId) => {
                      onSelect?.(block.id)
                      onSelectSectionElement?.(block.id, elementId)
                    },
                    onElementChange: (elementId, patch) => {
                      const updateElements = (content: Record<string, unknown>) => {
                        const rawElements = content.elements
                        if (!Array.isArray(rawElements)) return content
                        return {
                          ...content,
                          elements: rawElements.map((item) => {
                            if (typeof item !== 'object' || item === null) return item
                            const el = item as Record<string, unknown>
                            if (el.id !== elementId) return item
                            return mergeElementLayoutPatch(el, patch as Record<string, unknown>)
                          }),
                        }
                      }
                      onUpdateBlock(block.id, {
                        content_en: updateElements(block.content_en as Record<string, unknown>),
                        content_ar: updateElements(block.content_ar as Record<string, unknown>),
                      })
                    },
                    onMoveElementUp: onMoveSectionElementUp
                      ? (elementId) => onMoveSectionElementUp(block.id, elementId)
                      : undefined,
                    onMoveElementDown: onMoveSectionElementDown
                      ? (elementId) => onMoveSectionElementDown(block.id, elementId)
                      : undefined,
                    onDuplicateElement: onDuplicateSectionElement
                      ? (elementId) => onDuplicateSectionElement(block.id, elementId)
                      : undefined,
                    onRemoveElement: onRemoveSectionElement
                      ? (elementId) => onRemoveSectionElement(block.id, elementId)
                      : undefined,
                  }
                : undefined
            }
          />
        )
        break
      case 'register_cta':
        node = <RegisterCtaRenderer {...common} />
        break
      case 'footer':
        node = <FooterRenderer {...common} />
        break
      case 'media_text':
        node = <MediaTextRenderer {...common} />
        break
      case 'image_showcase':
        node = <ImageShowcaseRenderer {...common} />
        break
      case 'form':
        node = (
          <FormRenderer
            {...common}
            eventSlug={eventSlug}
            pageId={block.page_id || currentPageId}
            blockId={block.id}
          />
        )
        break
      default:
        node = null
    }

    if (!node) return null

    const isChrome = block.type === 'header' || block.type === 'footer'
    const internalBgBlocks = ['media_text', 'image_showcase', 'form', 'section', 'hero', 'header', 'footer']
    const shellCls = blockShellClasses(block.options)
    let shellInline = blockShellStyle(block.options)
    if (internalBgBlocks.includes(block.type)) {
      shellInline = { ...shellInline }
      delete shellInline.background
      delete shellInline.backgroundColor
      delete shellInline.backgroundImage
      delete shellInline.backgroundSize
      delete shellInline.backgroundPosition
      delete shellInline.backgroundRepeat
    }
    const shellOverlay = internalBgBlocks.includes(block.type) ? null : blockShellOverlay(block.options)

    const blockBg = block.options.background as SiteBackground | undefined
    const legacyBgStyle = !internalBgBlocks.includes(block.type) ? backgroundStyle(blockBg) : {}
    const legacyOverlay = !internalBgBlocks.includes(block.type) ? backgroundOverlayStyle(blockBg) : null

    const widthCls = !isChrome && blockWidth ? widthClass(blockWidth) : ''
    const alignCls = !isChrome && blockAlign ? alignClass(blockAlign) : ''

    // Apply width outside shell so "full" truly bleeds edge-to-edge;
    // shell styles (radius/shadow) sit inside the width container.
    let wrappedNode = node

    const hasShellStyle = shellCls || Object.keys(shellInline).length > 0 || shellOverlay || Object.keys(legacyBgStyle).length > 0
    if (hasShellStyle) {
      wrappedNode = (
        <div className={`relative ${shellCls}`.trim()} style={{ ...shellInline, ...legacyBgStyle }}>
          {(shellOverlay || legacyOverlay) && <div style={shellOverlay || legacyOverlay} />}
          <div className="relative">{wrappedNode}</div>
        </div>
      )
    }

    if (!isChrome && (widthCls || alignCls)) {
      wrappedNode = (
        <div className={`${widthCls} ${alignCls}`.trim()}>
          {wrappedNode}
        </div>
      )
    }

    if (!interactive) {
      const customClass = typeof block.options.custom_class === 'string' ? block.options.custom_class : ''
      const customCss = typeof block.options.custom_css === 'string' ? block.options.custom_css : ''
      return (
        <BlockCustomStyles key={block.id} blockId={block.id} customClass={customClass} customCss={customCss}>
          {wrappedNode}
        </BlockCustomStyles>
      )
    }

    const label = blockTypeLabels[block.type]?.[locale] || block.type
    const visibleIndexBlocks = blocks.filter((b) => b.visible !== false)
    const visibleIndex = visibleIndexBlocks.findIndex((b) => b.id === block.id)

    const customClass = typeof block.options.custom_class === 'string' ? block.options.custom_class : ''
    const customCss = typeof block.options.custom_css === 'string' ? block.options.custom_css : ''

    return (
      <SortableBlockShell key={block.id} blockId={block.id}>
        {({ sortableHandleProps }) => (
          <BuilderSectionFrame
            blockId={block.id}
            blockType={block.type}
            label={label}
            selected={selectedId === block.id}
            visible={block.visible !== false}
            canMoveUp={visibleIndex > 0}
            canMoveDown={visibleIndex >= 0 && visibleIndex < visibleIndexBlocks.length - 1}
            onSelect={() => onSelect?.(block.id)}
            onDuplicate={onDuplicate ? () => onDuplicate(block.id) : undefined}
            onMoveUp={onMoveUp ? () => onMoveUp(block.id) : undefined}
            onMoveDown={onMoveDown ? () => onMoveDown(block.id) : undefined}
            onToggleVisibility={onToggleVisibility ? () => onToggleVisibility(block.id) : undefined}
            onRemove={onRemove ? () => onRemove(block.id) : undefined}
            onChangeLayout={
              block.type === 'section' && onChangeSectionLayout
                ? () => onChangeSectionLayout(block.id)
                : undefined
            }
            onOpenStyle={onOpenSectionStyle ? () => onOpenSectionStyle(block.id) : undefined}
            sortableHandleProps={sortableHandleProps}
          >
            <BlockCustomStyles blockId={block.id} customClass={customClass} customCss={customCss}>
              {wrappedNode}
            </BlockCustomStyles>
          </BuilderSectionFrame>
        )}
      </SortableBlockShell>
    )
  }

  if (!interactive) {
    return (
      <div
        id="top"
        dir={locale === 'ar' ? 'rtl' : 'ltr'}
        lang={locale}
        className="min-h-full bg-background text-[var(--ink)]"
        style={{ direction: locale === 'ar' ? 'rtl' : 'ltr' }}
      >
        {canvasBlocks.map((block) => renderBlock(block))}
      </div>
    )
  }

  return (
    <div
      id="top"
      dir={locale === 'ar' ? 'rtl' : 'ltr'}
      lang={locale}
      className="min-h-full bg-background text-[var(--ink)]"
      style={{ direction: locale === 'ar' ? 'rtl' : 'ltr' }}
    >
      <SortableContext items={canvasBlocks.map((b) => b.id)} strategy={verticalListSortingStrategy}>
        <BlockDropSlot id="block-slot-0" index={0} onHover={onBlockSlotHover} />
        {canvasBlocks.map((block, index) => (
          <div key={block.id}>
            {renderBlock(block)}
            <BlockDropSlot id={`block-slot-${index + 1}`} index={index + 1} onHover={onBlockSlotHover} />
          </div>
        ))}
      </SortableContext>
      {onAppendSection && (
        <button
          type="button"
          onClick={onAppendSection}
          className="mt-6 w-full rounded-2xl border-2 border-dashed border-slate-300 bg-white/60 py-5 text-sm font-bold text-slate-500 transition hover:border-indigo-500 hover:text-indigo-600"
        >
          ＋ {locale === 'ar' ? 'إضافة سيكشن' : 'Add section'}
        </button>
      )}
    </div>
  )
}
