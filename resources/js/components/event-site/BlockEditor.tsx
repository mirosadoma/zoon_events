import { useCallback } from 'react'
import HeroEditor from './editors/HeroEditor'
import AboutEditor from './editors/AboutEditor'
import AgendaEditor from './editors/AgendaEditor'
import SpeakersEditor from './editors/SpeakersEditor'
import VenueEditor from './editors/VenueEditor'
import FaqEditor from './editors/FaqEditor'
import SponsorsEditor from './editors/SponsorsEditor'
import GalleryEditor from './editors/GalleryEditor'
import RegisterCtaEditor from './editors/RegisterCtaEditor'
import HeaderEditor from './editors/HeaderEditor'
import FooterEditor, { splitFooterColumns } from './editors/FooterEditor'
import SectionEditor from './editors/SectionEditor'
import MediaTextEditor from './editors/MediaTextEditor'
import ImageShowcaseEditor from './editors/ImageShowcaseEditor'
import FormEditor from './editors/FormEditor'
import StylePanel from './StylePanel'
import SelectInput from '@/components/forms/SelectInput'
import { convertElementsToFreeform } from '@/lib/sectionFreeformLayout'
import { normalizeShowcaseItems, type ShowcaseItem } from '@/lib/showcaseCarousel'

type SiteBlock = {
  id: string
  type: string
  visible: boolean
  page_id?: string
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
}

type BlockPatch = Partial<SiteBlock> | ((prev: SiteBlock) => Partial<SiteBlock>)

type ContentUpdates =
  | Record<string, unknown>
  | ((content: Record<string, unknown>) => Record<string, unknown>)

type Props = {
  block: SiteBlock
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  pages?: Array<{ id: string; slug: string; title_en: string; title_ar: string }>
  onChange: (updates: BlockPatch) => void
  mode?: 'content' | 'style' | 'full'
  selectedSectionElementId?: string | null
}

/** Keep slide structure/media shared across locales; preserve other-locale text fields. */
function syncShowcaseItemsForOtherLocale(
  nextItems: ShowcaseItem[],
  otherItemsRaw: unknown,
): ShowcaseItem[] {
  const otherById = new Map(normalizeShowcaseItems(otherItemsRaw).map((item) => [item.id, item]))
  return nextItems.map((item) => {
    const prev = otherById.get(item.id)
    if (!prev) return { ...item }
    return {
      ...item,
      title: prev.title,
      description: prev.description,
      extra_text: prev.extra_text,
      button_label: prev.button_label,
      button_href: prev.button_href || item.button_href,
    }
  })
}

export default function BlockEditor({ block, locale, eventId, tenantId, pages, onChange, mode = 'full', selectedSectionElementId = null }: Props) {
  const contentKey = locale === 'ar' ? 'content_ar' : 'content_en'
  const content = block[contentKey] as Record<string, unknown>

  const updateContent = useCallback(
    (updates: ContentUpdates) => {
      onChange((prev) => {
        const key = locale === 'ar' ? 'content_ar' : 'content_en'
        const otherKey = locale === 'ar' ? 'content_en' : 'content_ar'
        const current = (prev[key] ?? {}) as Record<string, unknown>
        const next =
          typeof updates === 'function' ? updates(current) : { ...current, ...updates }

        if (prev.type === 'image_showcase' && 'items' in next) {
          const items = normalizeShowcaseItems(next.items)
          const other = (prev[otherKey] ?? {}) as Record<string, unknown>
          return {
            [key]: { ...next, items },
            [otherKey]: {
              ...other,
              items: syncShowcaseItemsForOtherLocale(items, other.items),
            },
          }
        }

        return { [key]: next }
      })
    },
    [locale, onChange],
  )

  const updateOptions = useCallback(
    (updates: Record<string, unknown>) => {
      onChange((prev) => {
        if (prev.type === 'section' && updates.layout_mode === 'freeform') {
          const convertContent = (sectionContent: Record<string, unknown>) => {
            const raw = sectionContent.elements
            if (!Array.isArray(raw)) return sectionContent
            const elements = raw.filter(
              (item): item is Record<string, unknown> => typeof item === 'object' && item !== null,
            )
            return {
              ...sectionContent,
              elements: convertElementsToFreeform(elements).map((el, i) => ({ ...el, order: i })),
            }
          }
          return {
            options: { ...prev.options, ...updates },
            content_en: convertContent(prev.content_en as Record<string, unknown>),
            content_ar: convertContent(prev.content_ar as Record<string, unknown>),
          }
        }

        if (prev.type === 'section' && updates.layout_mode === 'grid') {
          const stripFreeform = (sectionContent: Record<string, unknown>) => {
            const raw = sectionContent.elements
            if (!Array.isArray(raw)) return sectionContent
            return {
              ...sectionContent,
              elements: raw.map((item) => {
                if (typeof item !== 'object' || item === null) return item
                const el = { ...(item as Record<string, unknown>) }
                delete el.x_pct
                delete el.y_pct
                delete el.width_pct
                delete el.height_pct
                delete el.z_index
                return el
              }),
            }
          }
          return {
            options: { ...prev.options, ...updates },
            content_en: stripFreeform(prev.content_en as Record<string, unknown>),
            content_ar: stripFreeform(prev.content_ar as Record<string, unknown>),
          }
        }

        return { options: { ...prev.options, ...updates } }
      })
    },
    [onChange],
  )

  const updateRefs = useCallback(
    (updates: Record<string, unknown>) => {
      onChange((prev) => ({ refs: { ...prev.refs, ...updates } }))
    },
    [onChange],
  )

  const updatePageId = useCallback(
    (pageId: string) => {
      onChange({ page_id: pageId })
    },
    [onChange],
  )

  const commonProps = {
    content,
    options: block.options,
    refs: block.refs,
    locale,
    eventId,
    tenantId,
    onContentChange: updateContent,
    onOptionsChange: updateOptions,
    onRefsChange: updateRefs,
  }

  if (mode === 'style') {
    return (
      <StylePanel
        options={block.options}
        locale={locale}
        eventId={eventId}
        tenantId={tenantId}
        blockType={block.type}
        onChange={updateOptions}
      />
    )
  }

  let editor: React.ReactNode = null

  switch (block.type) {
    case 'header':
      editor = (
        <HeaderEditor
          {...commonProps}
          contentEn={block.content_en}
          contentAr={block.content_ar}
          onLinksChange={(links) =>
            onChange({
              content_en: { ...block.content_en, links },
              content_ar: { ...block.content_ar, links },
            })
          }
          onCtaLabelsChange={({ en, ar }) =>
            onChange({
              content_en: { ...block.content_en, cta_label: en },
              content_ar: { ...block.content_ar, cta_label: ar },
            })
          }
        />
      )
      break
    case 'hero':
      editor = <HeroEditor {...commonProps} />
      break
    case 'about':
      editor = <AboutEditor {...commonProps} />
      break
    case 'agenda':
      editor = <AgendaEditor {...commonProps} />
      break
    case 'speakers':
      editor = <SpeakersEditor {...commonProps} />
      break
    case 'venue':
      editor = <VenueEditor {...commonProps} />
      break
    case 'faq':
      editor = <FaqEditor {...commonProps} />
      break
    case 'sponsors':
      editor = <SponsorsEditor {...commonProps} />
      break
    case 'gallery':
      editor = <GalleryEditor {...commonProps} />
      break
    case 'section':
      editor = <SectionEditor {...commonProps} selectedElementId={selectedSectionElementId} />
      break
    case 'register_cta':
      editor = <RegisterCtaEditor {...commonProps} />
      break
    case 'footer':
      editor = (
        <FooterEditor
          {...commonProps}
          contentEn={block.content_en}
          contentAr={block.content_ar}
          onBilingualTextChange={(field, labels) =>
            onChange({
              content_en: { ...block.content_en, [field]: labels.en },
              content_ar: { ...block.content_ar, [field]: labels.ar },
            })
          }
          onSocialLinksChange={(links) =>
            onChange({
              content_en: { ...block.content_en, social_links: links },
              content_ar: { ...block.content_ar, social_links: links },
            })
          }
          onColumnsChange={(columns) => {
            const { en, ar } = splitFooterColumns(columns)
            onChange({
              content_en: { ...block.content_en, columns: en },
              content_ar: { ...block.content_ar, columns: ar },
            })
          }}
        />
      )
      break
    case 'media_text':
      editor = <MediaTextEditor {...commonProps} />
      break
    case 'image_showcase':
      editor = <ImageShowcaseEditor {...commonProps} />
      break
    case 'form':
      editor = <FormEditor {...commonProps} />
      break
    default:
      editor = (
        <div className="p-4 rounded-lg bg-muted">
          <p className="text-muted-foreground">Unknown block type: {block.type}</p>
        </div>
      )
  }

  return (
    <div className="builder-inspector">
      {editor}

      {pages && pages.length > 0 && block.type !== 'header' && block.type !== 'footer' && (
        <div className="px-4 pb-4">
          <SelectInput
            label={locale === 'ar' ? 'الصفحة' : 'Page'}
            name="block_page"
            value={block.page_id || 'home'}
            onChange={(e) => updatePageId(e.target.value)}
            options={pages.map((page) => ({
              value: page.id,
              label: locale === 'ar' ? page.title_ar : page.title_en,
            }))}
          />
        </div>
      )}

      {mode === 'full' && (
        <div className="border-t border-white/10">
          <StylePanel
            options={block.options}
            locale={locale}
            eventId={eventId}
            tenantId={tenantId}
            blockType={block.type}
            onChange={updateOptions}
          />
        </div>
      )}
    </div>
  )
}
