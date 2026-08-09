import type { SiteBackground } from '@/lib/siteBackgroundStyle'

export type ShowcaseLayout = 'content' | 'image_overlay' | 'image_only'

export type ExtraTextPosition = 'above_title' | 'below_title' | 'below_description' | 'below_button'

export type DividerPosition = 'after_title' | 'after_description' | 'after_extra' | 'before_button'

export type ContentAlign = 'start' | 'center' | 'end'
export type ContentVAlign = 'start' | 'center' | 'end'

export type ShowcaseItem = {
  id: string
  layout?: ShowcaseLayout
  src?: string
  background?: SiteBackground
  title?: string
  description?: string
  extra_text?: string
  extra_text_position?: ExtraTextPosition
  show_divider?: boolean
  divider_position?: DividerPosition
  button_label?: string
  button_href?: string
  content_align?: ContentAlign
  content_v_align?: ContentVAlign
  text_color?: string
  min_height?: number
  min_height_unit?: string
}

export type DotsStyle = 'dots' | 'bars' | 'numbers'
export type ArrowsStyle = 'circle' | 'square' | 'minimal'

export function isCarouselDisplay(display: string): boolean {
  return display === 'slider' || display === 'carousel'
}

export function normalizeShowcaseItem(raw: unknown): ShowcaseItem | null {
  if (!raw || typeof raw !== 'object') return null
  const item = raw as Record<string, unknown>
  const id = typeof item.id === 'string' ? item.id : ''
  if (!id) return null

  const layout =
    item.layout === 'image_overlay' || item.layout === 'image_only' || item.layout === 'content'
      ? item.layout
      : item.src
        ? 'image_overlay'
        : 'content'

  return {
    id,
    layout,
    src: typeof item.src === 'string' ? item.src : '',
    background:
      item.background && typeof item.background === 'object'
        ? (item.background as SiteBackground)
        : { type: 'none' },
    title: typeof item.title === 'string' ? item.title : '',
    description: typeof item.description === 'string' ? item.description : '',
    extra_text: typeof item.extra_text === 'string' ? item.extra_text : '',
    extra_text_position:
      item.extra_text_position === 'above_title' ||
      item.extra_text_position === 'below_title' ||
      item.extra_text_position === 'below_description' ||
      item.extra_text_position === 'below_button'
        ? item.extra_text_position
        : 'below_description',
    show_divider: item.show_divider === true,
    divider_position:
      item.divider_position === 'after_title' ||
      item.divider_position === 'after_description' ||
      item.divider_position === 'after_extra' ||
      item.divider_position === 'before_button'
        ? item.divider_position
        : 'after_title',
    button_label: typeof item.button_label === 'string' ? item.button_label : '',
    button_href: typeof item.button_href === 'string' ? item.button_href : '',
    content_align:
      item.content_align === 'start' || item.content_align === 'center' || item.content_align === 'end'
        ? item.content_align
        : 'center',
    content_v_align:
      item.content_v_align === 'start' || item.content_v_align === 'center' || item.content_v_align === 'end'
        ? item.content_v_align
        : 'center',
    text_color: typeof item.text_color === 'string' ? item.text_color : '',
    min_height: typeof item.min_height === 'number' ? item.min_height : 320,
    min_height_unit:
      item.min_height_unit === 'px' ||
      item.min_height_unit === '%' ||
      item.min_height_unit === 'vh' ||
      item.min_height_unit === 'vw' ||
      item.min_height_unit === 'rem' ||
      item.min_height_unit === 'em'
        ? item.min_height_unit
        : 'px',
  }
}

export function normalizeShowcaseItems(raw: unknown): ShowcaseItem[] {
  if (!Array.isArray(raw)) return []
  return raw.map(normalizeShowcaseItem).filter((item): item is ShowcaseItem => item !== null)
}

export function createEmptyShowcaseItem(): ShowcaseItem {
  return {
    id: `slide_${Date.now()}_${Math.random().toString(36).slice(2, 6)}`,
    layout: 'content',
    src: '',
    background: { type: 'solid', color: '#1e1b4b' },
    title: '',
    description: '',
    extra_text: '',
    extra_text_position: 'below_description',
    show_divider: false,
    divider_position: 'after_title',
    button_label: '',
    button_href: '',
    content_align: 'center',
    content_v_align: 'center',
    text_color: '#ffffff',
    min_height: 360,
    min_height_unit: 'px',
  }
}
