import { defaultFreeformPlacement } from '@/lib/sectionFreeformLayout'

export type SectionElementRecord = {
  id: string
  kind: string
  col_span: number
  col_start?: number
  order: number
  x_pct?: number
  y_pct?: number
  width_pct?: number
  height_pct?: number
  z_index?: number
  title?: string
  body?: string
  label?: string
  href?: string
  src?: string
  alt?: string
  style?: Record<string, unknown>
}

export function createSectionElement(
  kind: string,
  locale: 'en' | 'ar',
  order: number,
  eventName?: string,
  layoutMode?: 'grid' | 'freeform',
): SectionElementRecord {
  const isAr = locale === 'ar'
  const base: SectionElementRecord = {
    id: `e_${Math.random().toString(36).slice(2, 8)}`,
    kind,
    col_span: kind === 'spacer' || kind === 'divider' ? 12 : 6,
    order,
    title: '',
    body: '',
    label: '',
    href: '',
    src: '',
    alt: '',
  }

  if (kind === 'hero') {
    base.title = eventName ?? (isAr ? 'الحدث' : 'Event')
    base.col_span = 12
  }
  if (kind === 'heading' || kind === 'card') {
    base.title = isAr ? 'عنوان' : 'Heading'
  }
  if (kind === 'text' || kind === 'box' || kind === 'card') {
    base.body = isAr ? 'اكتب النص هنا.' : 'Write your text here.'
  }
  if (kind === 'button') {
    base.label = isAr ? 'سجّل الآن' : 'Register now'
    base.href = 'registration'
  }
  if (kind === 'list') {
    base.title = isAr ? 'أبرز النقاط' : 'Highlights'
    base.body = isAr ? '• عنصر القائمة' : '• List item'
  }
  if (kind === 'quote') {
    base.body = isAr ? 'اقتباس...' : 'Quote...'
  }
  if (kind === 'icon') {
    base.label = '★'
  }
  if (kind === 'shape') {
    base.col_span = 4
  }

  if (kind === 'divider' || kind === 'spacer') {
    base.col_span = 12
    base.style = { margin_top: 'md', margin_bottom: 'md' }
  }

  if (layoutMode === 'freeform') {
    const placement = defaultFreeformPlacement(order)
    base.x_pct = placement.x_pct
    base.y_pct = placement.y_pct
    base.width_pct = placement.width_pct
    base.z_index = placement.z_index
    if (kind === 'spacer' || kind === 'shape') {
      base.height_pct = 15
    }
  }

  return base
}

export function asElementArray(value: unknown): SectionElementRecord[] {
  if (!Array.isArray(value)) return []
  return value
    .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
    .map((item, index) => ({
      id: typeof item.id === 'string' ? item.id : `e_${index}`,
      kind: typeof item.kind === 'string' ? item.kind : 'text',
      col_span: typeof item.col_span === 'number' ? item.col_span : 6,
      col_start: typeof item.col_start === 'number' ? item.col_start : undefined,
      order: typeof item.order === 'number' ? item.order : index,
      x_pct: typeof item.x_pct === 'number' ? item.x_pct : undefined,
      y_pct: typeof item.y_pct === 'number' ? item.y_pct : undefined,
      width_pct: typeof item.width_pct === 'number' ? item.width_pct : undefined,
      height_pct: typeof item.height_pct === 'number' ? item.height_pct : undefined,
      z_index: typeof item.z_index === 'number' ? item.z_index : undefined,
      title: typeof item.title === 'string' ? item.title : '',
      body: typeof item.body === 'string' ? item.body : '',
      label: typeof item.label === 'string' ? item.label : '',
      href: typeof item.href === 'string' ? item.href : '',
      src: typeof item.src === 'string' ? item.src : '',
      alt: typeof item.alt === 'string' ? item.alt : '',
      style:
        typeof item.style === 'object' && item.style !== null && !Array.isArray(item.style)
          ? (item.style as Record<string, unknown>)
          : undefined,
    }))
}
