import { useCallback } from 'react'
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import LogoEditor from '../LogoEditor'
import { Plus, Trash2, GripVertical, ChevronUp, ChevronDown } from 'lucide-react'

type FooterLink = { id: string; label_en: string; label_ar: string; href: string }
type FooterColumn = { id: string; title_en: string; title_ar: string; links: FooterLink[] }
type SocialLink = {
  id: string
  platform: string
  url: string
}

type LogoValue = {
  url?: string
  path?: string
  position?: 'left' | 'center' | 'right'
  size?: 'sm' | 'md' | 'lg' | 'custom'
  max_height?: number
  max_height_unit?: string
}

type Props = {
  content: Record<string, unknown>
  contentEn: Record<string, unknown>
  contentAr: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  onContentChange: (updates: Record<string, unknown>) => void
  onOptionsChange: (updates: Record<string, unknown>) => void
  onRefsChange: (updates: Record<string, unknown>) => void
  onBilingualTextChange: (field: 'tagline' | 'copyright', labels: { en: string; ar: string }) => void
  onSocialLinksChange: (links: SocialLink[]) => void
  /** Sync column structure/order across EN+AR (preserves other-locale titles/labels). */
  onColumnsChange: (columns: FooterColumn[]) => void
}

const SOCIAL_PLATFORMS = [
  { value: 'facebook', labelEn: 'Facebook', labelAr: 'فيسبوك' },
  { value: 'instagram', labelEn: 'Instagram', labelAr: 'إنستغرام' },
  { value: 'x', labelEn: 'X / Twitter', labelAr: 'إكس / تويتر' },
  { value: 'linkedin', labelEn: 'LinkedIn', labelAr: 'لينكدإن' },
  { value: 'youtube', labelEn: 'YouTube', labelAr: 'يوتيوب' },
  { value: 'tiktok', labelEn: 'TikTok', labelAr: 'تيك توك' },
  { value: 'whatsapp', labelEn: 'WhatsApp', labelAr: 'واتساب' },
  { value: 'website', labelEn: 'Website', labelAr: 'موقع' },
  { value: 'custom', labelEn: 'Custom', labelAr: 'مخصص' },
]

export function asFooterColumns(value: unknown): FooterColumn[] {
  if (!Array.isArray(value)) return []
  return value
    .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
    .map((item, index) => {
      const legacyTitle = typeof item.title === 'string' ? item.title : ''
      return {
        id: typeof item.id === 'string' ? item.id : `col_${index}`,
        title_en:
          typeof item.title_en === 'string'
            ? item.title_en
            : legacyTitle,
        title_ar:
          typeof item.title_ar === 'string'
            ? item.title_ar
            : legacyTitle,
        links: Array.isArray(item.links)
          ? item.links
              .filter((link): link is Record<string, unknown> => typeof link === 'object' && link !== null)
              .map((link, linkIndex) => {
                const legacyLabel = typeof link.label === 'string' ? link.label : ''
                return {
                  id: typeof link.id === 'string' ? link.id : `link_${linkIndex}`,
                  label_en:
                    typeof link.label_en === 'string'
                      ? link.label_en
                      : legacyLabel,
                  label_ar:
                    typeof link.label_ar === 'string'
                      ? link.label_ar
                      : legacyLabel,
                  href: typeof link.href === 'string' ? link.href : '#',
                }
              })
          : [],
      }
    })
}

/** Merge EN + AR footer columns so the editor can edit both labels at once. */
export function mergeFooterColumns(enValue: unknown, arValue: unknown): FooterColumn[] {
  const enCols = asFooterColumns(enValue)
  const arCols = asFooterColumns(arValue)
  const arById = new Map(arCols.map((col) => [col.id, col]))

  if (enCols.length === 0 && arCols.length > 0) {
    return arCols
  }

  return enCols.map((enCol) => {
    const arCol = arById.get(enCol.id)
    const arLinks = new Map((arCol?.links ?? []).map((link) => [link.id, link]))
    return {
      id: enCol.id,
      title_en: enCol.title_en || enCol.title_ar || '',
      title_ar: arCol?.title_ar || arCol?.title_en || enCol.title_ar || '',
      links: enCol.links.map((enLink) => {
        const arLink = arLinks.get(enLink.id)
        return {
          id: enLink.id,
          label_en: enLink.label_en || enLink.label_ar || '',
          label_ar: arLink?.label_ar || arLink?.label_en || enLink.label_ar || '',
          href: enLink.href || arLink?.href || '#',
        }
      }),
    }
  })
}

/** Persist merged editor columns back into locale-specific content shapes. */
export function splitFooterColumns(columns: FooterColumn[]): {
  en: Array<{ id: string; title: string; title_en: string; title_ar: string; links: Array<Record<string, string>> }>
  ar: Array<{ id: string; title: string; title_en: string; title_ar: string; links: Array<Record<string, string>> }>
} {
  const mapLocale = (locale: 'en' | 'ar') =>
    columns.map((col) => ({
      id: col.id,
      title: locale === 'ar' ? col.title_ar : col.title_en,
      title_en: col.title_en,
      title_ar: col.title_ar,
      links: col.links.map((link) => ({
        id: link.id,
        label: locale === 'ar' ? link.label_ar : link.label_en,
        label_en: link.label_en,
        label_ar: link.label_ar,
        href: link.href,
      })),
    }))

  return { en: mapLocale('en'), ar: mapLocale('ar') }
}

function asSocialLinks(value: unknown): SocialLink[] {
  if (!Array.isArray(value)) return []
  return value
    .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
    .map((item, index) => ({
      id: typeof item.id === 'string' ? item.id : `social_${index}`,
      platform: typeof item.platform === 'string' ? item.platform : 'website',
      url: typeof item.url === 'string' ? item.url : '',
    }))
}

function extractLogoValue(refs: Record<string, unknown>, options: Record<string, unknown>): LogoValue {
  return {
    url: typeof refs.logo_url === 'string' ? refs.logo_url : '',
    path: typeof refs.logo_path === 'string' ? refs.logo_path : '',
    position: typeof options.logo_position === 'string' ? (options.logo_position as 'left' | 'center' | 'right') : 'left',
    size: typeof options.logo_size === 'string' ? (options.logo_size as 'sm' | 'md' | 'lg' | 'custom') : 'md',
    max_height: typeof options.logo_max_height === 'number' ? options.logo_max_height : undefined,
    max_height_unit: typeof options.logo_max_height_unit === 'string' ? options.logo_max_height_unit : 'px',
  }
}

function SortableColumnCard({
  column,
  index,
  total,
  isAr,
  onUpdate,
  onRemove,
  onMove,
  onAddLink,
  onUpdateLink,
  onRemoveLink,
  onReorderLinks,
}: {
  column: FooterColumn
  index: number
  total: number
  isAr: boolean
  onUpdate: (patch: Partial<FooterColumn>) => void
  onRemove: () => void
  onMove: (dir: -1 | 1) => void
  onAddLink: () => void
  onUpdateLink: (linkId: string, patch: Partial<FooterLink>) => void
  onRemoveLink: (linkId: string) => void
  onReorderLinks: (links: FooterLink[]) => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: column.id })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.55 : 1,
  }

  const linkSensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function handleLinkDragEnd(event: DragEndEvent) {
    const { active, over } = event
    if (!over || active.id === over.id) return
    const oldIndex = column.links.findIndex((l) => l.id === active.id)
    const newIndex = column.links.findIndex((l) => l.id === over.id)
    if (oldIndex < 0 || newIndex < 0) return
    onReorderLinks(arrayMove(column.links, oldIndex, newIndex))
  }

  return (
    <div ref={setNodeRef} style={style} className="space-y-2 rounded-md border border-[var(--border)] p-3 bg-background">
      <div className="flex items-center gap-1">
        <button
          type="button"
          className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground active:cursor-grabbing"
          title={isAr ? 'اسحب لإعادة الترتيب' : 'Drag to reorder'}
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-4 w-4" />
        </button>
        <span className="text-xs text-muted-foreground">
          {isAr ? `عمود ${index + 1}` : `Column ${index + 1}`}
        </span>
        <div className="ms-auto flex items-center gap-0.5">
          <button
            type="button"
            className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
            disabled={index === 0}
            onClick={() => onMove(-1)}
            title={isAr ? 'أعلى' : 'Move up'}
          >
            <ChevronUp className="h-4 w-4" />
          </button>
          <button
            type="button"
            className="rounded p-1 text-muted-foreground hover:bg-muted disabled:opacity-30"
            disabled={index >= total - 1}
            onClick={() => onMove(1)}
            title={isAr ? 'أسفل' : 'Move down'}
          >
            <ChevronDown className="h-4 w-4" />
          </button>
          <button type="button" className="rounded p-1.5 text-red-500 hover:bg-red-50" onClick={onRemove}>
            <Trash2 className="h-4 w-4" />
          </button>
        </div>
      </div>

      <div className="grid gap-2 sm:grid-cols-2">
        <TextInput
          label="Column title (EN)"
          name={`col_title_en_${column.id}`}
          value={column.title_en}
          onChange={(e) => onUpdate({ title_en: e.target.value })}
        />
        <TextInput
          label="عنوان العمود (AR)"
          name={`col_title_ar_${column.id}`}
          value={column.title_ar}
          onChange={(e) => onUpdate({ title_ar: e.target.value })}
          dir="rtl"
        />
      </div>

      <div className="space-y-2">
        <p className="text-xs font-medium text-muted-foreground">
          {isAr ? 'الروابط · اسحب لإعادة الترتيب' : 'Links · drag to reorder'}
        </p>
        <DndContext sensors={linkSensors} collisionDetection={closestCenter} onDragEnd={handleLinkDragEnd}>
          <SortableContext items={column.links.map((l) => l.id)} strategy={verticalListSortingStrategy}>
            {column.links.map((link) => (
              <SortableLinkRow
                key={link.id}
                link={link}
                isAr={isAr}
                onUpdate={(patch) => onUpdateLink(link.id, patch)}
                onRemove={() => onRemoveLink(link.id)}
              />
            ))}
          </SortableContext>
        </DndContext>
        <button type="button" className="button-secondary text-sm" onClick={onAddLink}>
          {isAr ? 'إضافة رابط' : 'Add link'}
        </button>
      </div>
    </div>
  )
}

function SortableLinkRow({
  link,
  isAr,
  onUpdate,
  onRemove,
}: {
  link: FooterLink
  isAr: boolean
  onUpdate: (patch: Partial<FooterLink>) => void
  onRemove: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: link.id })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.55 : 1,
  }

  return (
    <div
      ref={setNodeRef}
      style={style}
      className="space-y-2 rounded-md border border-white/10 bg-white/[0.04] p-2.5"
    >
      <div className="flex items-center gap-2">
        <button
          type="button"
          className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground active:cursor-grabbing"
          title={isAr ? 'اسحب لإعادة الترتيب' : 'Drag to reorder'}
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-4 w-4" />
        </button>
        <span className="min-w-0 flex-1 truncate text-[10px] text-white/35">#{link.id}</span>
        <button
          type="button"
          className="ms-auto rounded p-1.5 text-red-400/80 transition hover:bg-red-500/15 hover:text-red-300"
          onClick={onRemove}
          aria-label={isAr ? 'حذف' : 'Remove'}
          title={isAr ? 'حذف' : 'Remove'}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>

      <div className="grid gap-2 sm:grid-cols-2">
        <TextInput
          label="Label (EN)"
          name={`fl_label_en_${link.id}`}
          value={link.label_en}
          onChange={(e) => onUpdate({ label_en: e.target.value })}
        />
        <TextInput
          label="النص (AR)"
          name={`fl_label_ar_${link.id}`}
          value={link.label_ar}
          onChange={(e) => onUpdate({ label_ar: e.target.value })}
          dir="rtl"
        />
      </div>

      <TextInput
        label={isAr ? 'الرابط (URL)' : 'URL'}
        name={`fl_href_${link.id}`}
        value={link.href}
        onChange={(e) => onUpdate({ href: e.target.value })}
        placeholder="#about or https://…"
      />
    </div>
  )
}

export default function FooterEditor({
  content,
  contentEn,
  contentAr,
  options,
  refs,
  locale,
  eventId,
  tenantId,
  onContentChange,
  onOptionsChange,
  onRefsChange,
  onBilingualTextChange,
  onSocialLinksChange,
  onColumnsChange,
}: Props) {
  const isAr = locale === 'ar'
  void onContentChange // structural/text column updates go through onColumnsChange
  const design = typeof options.design === 'string' ? options.design : 'columns'
  const columns = mergeFooterColumns(contentEn.columns, contentAr.columns)
  const socialLinks = asSocialLinks(
    Array.isArray(contentEn.social_links) ? contentEn.social_links : content.social_links,
  )
  const showLogo = options.show_logo !== false
  const showSocial = Boolean(options.show_social)
  const showCopyright = options.show_copyright !== false
  const logoValue = extractLogoValue(refs, options)

  const gridCols = typeof options.grid_cols === 'number' ? options.grid_cols : 4
  const brandSpan = typeof options.brand_span === 'number' ? options.brand_span : 1
  const brandOrder = typeof options.brand_order === 'string' ? options.brand_order : 'start'
  const footerGap = typeof options.gap === 'string' ? options.gap : 'md'
  const gridMaxWidth = typeof options.grid_max_width === 'string' ? options.grid_max_width : '6xl'

  const taglineEn = typeof contentEn.tagline === 'string' ? contentEn.tagline : ''
  const taglineAr = typeof contentAr.tagline === 'string' ? contentAr.tagline : ''
  const copyrightEn = typeof contentEn.copyright === 'string' ? contentEn.copyright : ''
  const copyrightAr = typeof contentAr.copyright === 'string' ? contentAr.copyright : ''

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function setColumns(next: FooterColumn[]) {
    onColumnsChange(next)
  }

  function addColumn() {
    setColumns([
      ...columns,
      {
        id: `fc_${Math.random().toString(36).slice(2, 8)}`,
        title_en: 'New column',
        title_ar: 'عمود جديد',
        links: [],
      },
    ])
  }

  function moveColumn(index: number, dir: -1 | 1) {
    const target = index + dir
    if (target < 0 || target >= columns.length) return
    setColumns(arrayMove(columns, index, target))
  }

  function handleColumnDragEnd(event: DragEndEvent) {
    const { active, over } = event
    if (!over || active.id === over.id) return
    const oldIndex = columns.findIndex((c) => c.id === active.id)
    const newIndex = columns.findIndex((c) => c.id === over.id)
    if (oldIndex < 0 || newIndex < 0) return
    setColumns(arrayMove(columns, oldIndex, newIndex))
  }

  const handleLogoChange = useCallback(
    (newLogo: LogoValue) => {
      onRefsChange({
        logo_url: newLogo.url || '',
        logo_path: newLogo.path || '',
      })
      onOptionsChange({
        logo_position: newLogo.position || 'left',
        logo_size: newLogo.size || 'md',
        logo_max_height: newLogo.max_height,
        logo_max_height_unit: newLogo.max_height_unit || 'px',
        show_logo: true,
      })
    },
    [onRefsChange, onOptionsChange],
  )

  const platformOptions = SOCIAL_PLATFORMS.map((p) => ({
    value: p.value,
    label: isAr ? p.labelAr : p.labelEn,
  }))

  return (
    <div className="space-y-4 p-4 rounded-lg border border-[var(--border)] bg-[var(--surface)]">
      <h3 className="text-lg font-semibold">{isAr ? 'تذييل الصفحة' : 'Footer'}</h3>

      <SelectInput
        label={isAr ? 'تصميم التذييل' : 'Footer design'}
        name="footer_design"
        value={design}
        onChange={(e) => onOptionsChange({ design: e.target.value })}
        options={[
          { value: 'simple', label: isAr ? 'بسيط' : 'Simple' },
          { value: 'columns', label: isAr ? 'أعمدة' : 'Columns' },
          { value: 'centered', label: isAr ? 'وسط' : 'Centered' },
          { value: 'branded', label: isAr ? 'هوية' : 'Branded' },
        ]}
      />

      {(design === 'columns' || design === 'branded') && (
        <div className="space-y-3 rounded-md border border-violet-400/30 bg-violet-500/10 p-3">
          <p className="text-xs font-semibold text-violet-200">
            {isAr ? 'شبكة التذييل (Grid)' : 'Footer grid'}
          </p>
          <div className="grid gap-2 sm:grid-cols-2">
            <SelectInput
              label={isAr ? 'عدد الأعمدة' : 'Grid columns'}
              name="grid_cols"
              value={String(gridCols)}
              onChange={(e) => onOptionsChange({ grid_cols: Number(e.target.value) || 4 })}
              options={[
                { value: '2', label: '2' },
                { value: '3', label: '3' },
                { value: '4', label: '4' },
                { value: '5', label: '5' },
                { value: '6', label: '6' },
              ]}
            />
            <SelectInput
              label={isAr ? 'المسافة بين الأعمدة' : 'Gap'}
              name="footer_gap"
              value={footerGap}
              onChange={(e) => onOptionsChange({ gap: e.target.value })}
              options={[
                { value: 'sm', label: isAr ? 'صغير' : 'Small' },
                { value: 'md', label: isAr ? 'متوسط' : 'Medium' },
                { value: 'lg', label: isAr ? 'كبير' : 'Large' },
                { value: 'xl', label: isAr ? 'أكبر' : 'XL' },
              ]}
            />
            <SelectInput
              label={isAr ? 'عرض العلامة (أعمدة)' : 'Brand column span'}
              name="brand_span"
              value={String(brandSpan)}
              onChange={(e) => onOptionsChange({ brand_span: Number(e.target.value) || 1 })}
              options={[
                { value: '1', label: '1' },
                { value: '2', label: '2' },
                { value: '3', label: '3' },
              ]}
            />
            <SelectInput
              label={isAr ? 'موضع العلامة' : 'Brand position'}
              name="brand_order"
              value={brandOrder}
              onChange={(e) => onOptionsChange({ brand_order: e.target.value })}
              options={[
                { value: 'start', label: isAr ? 'البداية' : 'Start' },
                { value: 'end', label: isAr ? 'النهاية' : 'End' },
              ]}
            />
            <SelectInput
              label={isAr ? 'أقصى عرض للشبكة' : 'Grid max width'}
              name="grid_max_width"
              value={gridMaxWidth}
              onChange={(e) => onOptionsChange({ grid_max_width: e.target.value })}
              options={[
                { value: '4xl', label: '4XL' },
                { value: '5xl', label: '5XL' },
                { value: '6xl', label: '6XL' },
                { value: '7xl', label: '7XL' },
                { value: 'full', label: isAr ? 'كامل' : 'Full' },
              ]}
            />
          </div>
          <p className="text-[10px] text-white/50">
            {isAr
              ? 'عدد الأعمدة يتحكم في شبكة الفوتر بالكامل (العلامة + أعمدة الروابط).'
              : 'Grid columns control the whole footer row (brand + link columns).'}
          </p>
        </div>
      )}

      <div className="flex flex-wrap gap-4">
        <CheckboxInput
          label={isAr ? 'إظهار العلامة' : 'Show brand'}
          name="show_brand"
          checked={options.show_brand !== false}
          onChange={(e) => onOptionsChange({ show_brand: e.target.checked })}
        />
        <CheckboxInput
          label={isAr ? 'إظهار الصورة' : 'Show image'}
          name="show_logo"
          checked={showLogo}
          onChange={(e) => onOptionsChange({ show_logo: e.target.checked })}
        />
        <CheckboxInput
          label={isAr ? 'إظهار حقوق النشر' : 'Show copyright'}
          name="show_copyright"
          checked={showCopyright}
          onChange={(e) => onOptionsChange({ show_copyright: e.target.checked })}
        />
        <CheckboxInput
          label={isAr ? 'إظهار روابط اجتماعية' : 'Show social links'}
          name="show_social"
          checked={showSocial}
          onChange={(e) => {
            const checked = e.target.checked
            onOptionsChange({ show_social: checked })
            if (checked && socialLinks.length === 0) {
              onSocialLinksChange([
                { id: `social_${Math.random().toString(36).slice(2, 8)}`, platform: 'instagram', url: '' },
                { id: `social_${Math.random().toString(36).slice(2, 8)}`, platform: 'linkedin', url: '' },
              ])
            }
          }}
        />
      </div>

      {showLogo && (
        <LogoEditor
          value={logoValue}
          onChange={handleLogoChange}
          locale={locale}
          tenantId={tenantId}
          eventId={eventId}
        />
      )}

      <div className="grid gap-2 sm:grid-cols-2">
        <TextInput
          label="Tagline (EN)"
          name="tagline_en"
          value={taglineEn}
          onChange={(e) => onBilingualTextChange('tagline', { en: e.target.value, ar: taglineAr })}
          placeholder="Event"
        />
        <TextInput
          label="الشعار النصي (AR)"
          name="tagline_ar"
          value={taglineAr}
          onChange={(e) => onBilingualTextChange('tagline', { en: taglineEn, ar: e.target.value })}
          placeholder="الحدث"
          dir="rtl"
        />
      </div>

      {showCopyright && (
        <div className="grid gap-2 sm:grid-cols-2">
          <TextareaInput
            label="Copyright (EN)"
            name="copyright_en"
            value={copyrightEn}
            onChange={(e) => onBilingualTextChange('copyright', { en: e.target.value, ar: copyrightAr })}
            rows={2}
            placeholder="© 2026 Event Name"
          />
          <TextareaInput
            label="حقوق النشر (AR)"
            name="copyright_ar"
            value={copyrightAr}
            onChange={(e) => onBilingualTextChange('copyright', { en: copyrightEn, ar: e.target.value })}
            rows={2}
            placeholder="© 2026 اسم الحدث"
            dir="rtl"
          />
        </div>
      )}

      {showSocial && (
        <div className="space-y-3 rounded-lg border border-white/10 bg-white/[0.03] p-3">
          <div className="flex items-start justify-between gap-2">
            <div className="min-w-0">
              <p className="text-sm font-semibold text-white/90">
                {isAr ? 'روابط اجتماعية' : 'Social links'}
              </p>
              <p className="mt-0.5 text-[11px] text-white/45">
                {isAr ? 'أضف روابط المنصات لعرض الأيقونات' : 'Add platform URLs to show icons'}
              </p>
            </div>
            <button
              type="button"
              className="button-secondary inline-flex shrink-0 items-center gap-1 text-xs"
              onClick={() =>
                onSocialLinksChange([
                  ...socialLinks,
                  {
                    id: `social_${Math.random().toString(36).slice(2, 8)}`,
                    platform: 'instagram',
                    url: '',
                  },
                ])
              }
            >
              <Plus className="h-3.5 w-3.5" />
              {isAr ? 'إضافة' : 'Add'}
            </button>
          </div>

          {socialLinks.length === 0 && (
            <p className="rounded-md border border-dashed border-white/15 px-3 py-4 text-center text-xs text-white/40">
              {isAr ? 'لا توجد روابط بعد — اضغط إضافة' : 'No social links yet — click Add'}
            </p>
          )}

          <div className="space-y-2">
            {socialLinks.map((link, index) => {
              const platformLabel =
                SOCIAL_PLATFORMS.find((p) => p.value === link.platform)?.[isAr ? 'labelAr' : 'labelEn'] ??
                link.platform
              return (
                <div
                  key={link.id}
                  className="space-y-2 rounded-md border border-white/10 bg-white/[0.04] p-2.5"
                >
                  <div className="flex items-center gap-2">
                    <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-white/10 text-[10px] font-bold uppercase tracking-wide text-white/70">
                      {(link.platform || 'w').slice(0, 2)}
                    </span>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-xs font-medium text-white/80">{platformLabel}</p>
                      <p className="text-[10px] text-white/35">
                        {isAr ? `رابط ${index + 1}` : `Link ${index + 1}`}
                      </p>
                    </div>
                    <button
                      type="button"
                      className="rounded p-1.5 text-red-400/80 transition hover:bg-red-500/15 hover:text-red-300"
                      onClick={() => onSocialLinksChange(socialLinks.filter((item) => item.id !== link.id))}
                      aria-label={isAr ? 'حذف' : 'Remove'}
                    >
                      <Trash2 className="h-4 w-4" />
                    </button>
                  </div>
                  <SelectInput
                    label={isAr ? 'المنصة' : 'Platform'}
                    name={`social_platform_${link.id}`}
                    value={link.platform}
                    onChange={(e) =>
                      onSocialLinksChange(
                        socialLinks.map((item) =>
                          item.id === link.id ? { ...item, platform: e.target.value } : item,
                        ),
                      )
                    }
                    options={platformOptions}
                  />
                  <TextInput
                    label={isAr ? 'الرابط' : 'URL'}
                    name={`social_url_${link.id}`}
                    value={link.url}
                    onChange={(e) =>
                      onSocialLinksChange(
                        socialLinks.map((item) =>
                          item.id === link.id ? { ...item, url: e.target.value } : item,
                        ),
                      )
                    }
                    placeholder="https://"
                  />
                </div>
              )
            })}
          </div>
        </div>
      )}

      {(design === 'columns' || design === 'branded') && (
        <div className="space-y-3">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">{isAr ? 'أعمدة الروابط' : 'Link columns'}</p>
              <p className="text-xs text-muted-foreground">
                {isAr ? 'اسحب لإعادة الترتيب' : 'Drag to reorder columns'}
              </p>
            </div>
            <button type="button" className="button-secondary text-sm inline-flex items-center gap-1" onClick={addColumn}>
              <Plus className="h-4 w-4" />
              {isAr ? 'عمود' : 'Column'}
            </button>
          </div>

          <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleColumnDragEnd}>
            <SortableContext items={columns.map((c) => c.id)} strategy={verticalListSortingStrategy}>
              {columns.map((column, index) => (
                <SortableColumnCard
                  key={column.id}
                  column={column}
                  index={index}
                  total={columns.length}
                  isAr={isAr}
                  onUpdate={(patch) =>
                    setColumns(columns.map((item) => (item.id === column.id ? { ...item, ...patch } : item)))
                  }
                  onRemove={() => setColumns(columns.filter((item) => item.id !== column.id))}
                  onMove={(dir) => moveColumn(index, dir)}
                  onAddLink={() =>
                    setColumns(
                      columns.map((item) =>
                        item.id === column.id
                          ? {
                              ...item,
                              links: [
                                ...item.links,
                                {
                                  id: `fl_${Math.random().toString(36).slice(2, 8)}`,
                                  label_en: 'Link',
                                  label_ar: 'رابط',
                                  href: '#',
                                },
                              ],
                            }
                          : item,
                      ),
                    )
                  }
                  onUpdateLink={(linkId, patch) =>
                    setColumns(
                      columns.map((item) =>
                        item.id === column.id
                          ? {
                              ...item,
                              links: item.links.map((entry) =>
                                entry.id === linkId ? { ...entry, ...patch } : entry,
                              ),
                            }
                          : item,
                      ),
                    )
                  }
                  onRemoveLink={(linkId) =>
                    setColumns(
                      columns.map((item) =>
                        item.id === column.id
                          ? { ...item, links: item.links.filter((entry) => entry.id !== linkId) }
                          : item,
                      ),
                    )
                  }
                  onReorderLinks={(links) =>
                    setColumns(columns.map((item) => (item.id === column.id ? { ...item, links } : item)))
                  }
                />
              ))}
            </SortableContext>
          </DndContext>

          {columns.length === 0 && (
            <p className="text-sm text-muted-foreground">
              {isAr ? 'لا توجد أعمدة — اضغط إضافة عمود' : 'No columns yet — click Add column'}
            </p>
          )}
        </div>
      )}
    </div>
  )
}
