import { useState, useCallback, useRef } from 'react'
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
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical, Plus, Trash2, ChevronDown, ChevronUp, Upload } from 'lucide-react'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import BackgroundEditor from '../BackgroundEditor'
import LengthUnitField from '../builder/LengthUnitField'
import BuilderColorField from '../builder/BuilderColorField'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'
import { parseCssLength } from '@/lib/cssLength'
import {
  createEmptyShowcaseItem,
  isCarouselDisplay,
  normalizeShowcaseItems,
  type ShowcaseItem,
} from '@/lib/showcaseCarousel'

type ContentUpdates =
  | Record<string, unknown>
  | ((content: Record<string, unknown>) => Record<string, unknown>)

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  onContentChange: (updates: ContentUpdates) => void
  onOptionsChange: (updates: Record<string, unknown>) => void
  onRefsChange: (updates: Record<string, unknown>) => void
}

function SortableItem({
  item,
  locale,
  eventId,
  tenantId,
  useGlobalHeight = false,
  onUpdate,
  onRemove,
}: {
  item: ShowcaseItem
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  useGlobalHeight?: boolean
  onUpdate: (updates: Partial<ShowcaseItem> | ((item: ShowcaseItem) => Partial<ShowcaseItem>)) => void
  onRemove: () => void
}) {
  const [expanded, setExpanded] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadError, setUploadError] = useState<string | null>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const layout = item.layout ?? 'content'
  const itemBackground: SiteBackground = item.background ?? { type: 'none' }

  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: item.id,
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  const handleImageUpload = useCallback(
    async (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0]
      if (!file) return
      setUploading(true)
      setUploadError(null)
      try {
        const formData = new FormData()
        formData.append('file', file)
        const response = await apiFetch<{ path: string; url: string }>(
          `/api/v1/tenant/events/${eventId}/site/media`,
          { method: 'POST', tenantId, body: formData },
        )
        const url = response.url || response.path
        if (!url) {
          throw new Error(locale === 'ar' ? 'لم يُرجع الخادم رابط الصورة' : 'Upload returned no image URL')
        }
        onUpdate((current) => {
          const currentLayout = current.layout ?? 'content'
          if (currentLayout === 'content') {
            return {
              background: {
                ...(current.background ?? { type: 'none' }),
                type: 'image',
                image: url,
                overlay: current.background?.overlay ?? 30,
              },
            }
          }
          return { src: url }
        })
      } catch (err) {
        const message =
          err instanceof ApiFetchError
            ? err.message
            : err instanceof Error
              ? err.message
              : locale === 'ar'
                ? 'فشل رفع الصورة'
                : 'Failed to upload image'
        setUploadError(message)
        console.error('Failed to upload image:', err)
      } finally {
        setUploading(false)
        e.target.value = ''
      }
    },
    [eventId, locale, onUpdate, tenantId],
  )

  const layoutLabel =
    layout === 'image_only'
      ? locale === 'ar'
        ? 'صورة فقط'
        : 'Image only'
      : layout === 'image_overlay'
        ? locale === 'ar'
          ? 'صورة + محتوى'
          : 'Image + content'
        : locale === 'ar'
          ? 'محتوى + خلفية'
          : 'Content + bg'

  const showContentFields = layout !== 'image_only'
  const previewSrc =
    item.src ||
    (itemBackground.type === 'image' && itemBackground.image ? itemBackground.image : '')
  const showItemBackground = layout === 'content' || layout === 'image_overlay'

  return (
    <div ref={setNodeRef} style={style} className="rounded-lg border border-[var(--border)] bg-background">
      <div className="flex items-center gap-2 p-3">
        <button type="button" {...attributes} {...listeners} className="cursor-grab text-muted-foreground">
          <GripVertical className="h-4 w-4" />
        </button>
        {previewSrc ? (
          <img src={previewSrc} alt="" className="h-10 w-10 shrink-0 rounded object-cover" />
        ) : (
          <div
            className="flex h-10 w-10 shrink-0 items-center justify-center rounded text-[9px] text-white/80"
            style={{
              background:
                itemBackground.type === 'gradient'
                  ? `linear-gradient(135deg, ${itemBackground.color ?? '#888'}, ${itemBackground.color_end ?? '#444'})`
                  : itemBackground.type === 'solid'
                    ? itemBackground.color
                    : '#334155',
            }}
          >
            {layout === 'image_only' ? 'IMG' : 'BG'}
          </div>
        )}
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-medium">{item.title || (locale === 'ar' ? 'بدون عنوان' : 'Untitled')}</p>
          <p className="truncate text-[10px] text-muted-foreground">{layoutLabel}</p>
        </div>
        <button type="button" onClick={() => setExpanded(!expanded)} className="rounded p-1 hover:bg-muted">
          {expanded ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
        </button>
        <button type="button" onClick={onRemove} className="rounded p-1 hover:bg-red-100 dark:hover:bg-red-900/30">
          <Trash2 className="h-4 w-4 text-red-500" />
        </button>
      </div>

      {expanded && (
        <div className="space-y-3 border-t border-[var(--border)] p-3">
          <SelectInput
            label={locale === 'ar' ? 'نوع الشريحة' : 'Slide type'}
            name={`layout_${item.id}`}
            value={layout}
            onChange={(e) => onUpdate({ layout: e.target.value as ShowcaseItem['layout'] })}
            options={[
              { value: 'content', label: locale === 'ar' ? 'محتوى + خلفية (لون/تدرج/صورة)' : 'Content + background' },
              { value: 'image_overlay', label: locale === 'ar' ? 'صورة وعليها محتوى' : 'Image with overlay content' },
              { value: 'image_only', label: locale === 'ar' ? 'صورة فقط' : 'Image only' },
            ]}
          />

          <div className="space-y-2">
            <p className="text-xs font-medium text-muted-foreground">
              {locale === 'ar' ? 'صورة الشريحة' : 'Slide image'}
            </p>
            <div className="flex flex-wrap items-center gap-3">
              {previewSrc && <img src={previewSrc} alt="" className="h-16 w-16 rounded object-cover" />}
              <button
                type="button"
                className="button-secondary inline-flex items-center gap-1.5 text-xs"
                disabled={uploading}
                onClick={() => fileInputRef.current?.click()}
              >
                <Upload className="h-3.5 w-3.5" />
                {uploading
                  ? locale === 'ar'
                    ? 'جاري الرفع...'
                    : 'Uploading...'
                  : locale === 'ar'
                    ? 'رفع صورة'
                    : 'Upload image'}
              </button>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/jpeg,image/jpg,image/png,image/gif,image/webp"
                onChange={handleImageUpload}
                disabled={uploading}
                className="sr-only"
              />
              {previewSrc && (
                <button
                  type="button"
                  className="text-xs text-red-400 hover:text-red-300"
                  onClick={() =>
                    layout === 'content'
                      ? onUpdate({ background: { ...(item.background ?? {}), type: 'none', image: '' } })
                      : onUpdate({ src: '' })
                  }
                >
                  {locale === 'ar' ? 'إزالة' : 'Remove'}
                </button>
              )}
            </div>
            {uploadError && <p className="text-xs text-red-400">{uploadError}</p>}
            <p className="text-[10px] text-muted-foreground">
              {layout === 'content'
                ? locale === 'ar'
                  ? 'سيتم ضبط نوع الخلفية إلى صورة تلقائياً.'
                  : 'Sets the slide background to this image.'
                : locale === 'ar'
                  ? 'JPEG / PNG / GIF / WebP — حتى 10MB'
                  : 'JPEG / PNG / GIF / WebP — up to 10MB'}
            </p>
          </div>

          {showItemBackground && (
            <div className="rounded-lg border border-violet-400/25 bg-violet-500/10 p-3">
              <BackgroundEditor
                compact
                idPrefix={`slide_bg_${item.id}`}
                value={itemBackground}
                onChange={(bg) => onUpdate({ background: bg })}
                locale={locale}
                tenantId={tenantId}
                eventId={eventId}
                label={
                  locale === 'ar'
                    ? layout === 'image_overlay'
                      ? 'خلفية احتياطية (إن لم توجد صورة)'
                      : 'خلفية الشريحة (لون / تدرج)'
                    : layout === 'image_overlay'
                      ? 'Fallback background'
                      : 'Slide background (color / gradient)'
                }
              />
            </div>
          )}

          {showContentFields && (
            <>
              <TextInput
                label={locale === 'ar' ? 'العنوان' : 'Title'}
                name={`title_${item.id}`}
                value={item.title ?? ''}
                onChange={(e) => onUpdate({ title: e.target.value })}
              />
              <TextareaInput
                label={locale === 'ar' ? 'الوصف' : 'Description'}
                name={`desc_${item.id}`}
                value={item.description ?? ''}
                onChange={(e) => onUpdate({ description: e.target.value })}
                rows={2}
              />
              <TextareaInput
                label={locale === 'ar' ? 'نص إضافي' : 'Extra text'}
                name={`extra_${item.id}`}
                value={item.extra_text ?? ''}
                onChange={(e) => onUpdate({ extra_text: e.target.value })}
                rows={2}
              />
              <SelectInput
                label={locale === 'ar' ? 'مكان النص الإضافي' : 'Extra text position'}
                name={`extra_pos_${item.id}`}
                value={item.extra_text_position ?? 'below_description'}
                onChange={(e) => onUpdate({ extra_text_position: e.target.value as ShowcaseItem['extra_text_position'] })}
                options={[
                  { value: 'above_title', label: locale === 'ar' ? 'فوق العنوان' : 'Above title' },
                  { value: 'below_title', label: locale === 'ar' ? 'تحت العنوان' : 'Below title' },
                  { value: 'below_description', label: locale === 'ar' ? 'تحت الوصف' : 'Below description' },
                  { value: 'below_button', label: locale === 'ar' ? 'تحت الزر' : 'Below button' },
                ]}
              />
              <CheckboxInput
                label={locale === 'ar' ? 'إظهار خط فاصل' : 'Show divider line'}
                id={`div_${item.id}`}
                checked={item.show_divider === true}
                onChange={(e) => onUpdate({ show_divider: e.target.checked })}
              />
              {item.show_divider && (
                <SelectInput
                  label={locale === 'ar' ? 'مكان الخط' : 'Divider position'}
                  name={`div_pos_${item.id}`}
                  value={item.divider_position ?? 'after_title'}
                  onChange={(e) => onUpdate({ divider_position: e.target.value as ShowcaseItem['divider_position'] })}
                  options={[
                    { value: 'after_title', label: locale === 'ar' ? 'بعد العنوان' : 'After title' },
                    { value: 'after_description', label: locale === 'ar' ? 'بعد الوصف' : 'After description' },
                    { value: 'after_extra', label: locale === 'ar' ? 'بعد النص الإضافي' : 'After extra text' },
                    { value: 'before_button', label: locale === 'ar' ? 'قبل الزر' : 'Before button' },
                  ]}
                />
              )}
              <div className="grid grid-cols-2 gap-2">
                <TextInput
                  label={locale === 'ar' ? 'نص الزر' : 'Button'}
                  name={`btn_${item.id}`}
                  value={item.button_label ?? ''}
                  onChange={(e) => onUpdate({ button_label: e.target.value })}
                />
                <TextInput
                  label={locale === 'ar' ? 'رابط الزر' : 'Button link'}
                  name={`href_${item.id}`}
                  value={item.button_href ?? ''}
                  onChange={(e) => onUpdate({ button_href: e.target.value })}
                />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <SelectInput
                  label={locale === 'ar' ? 'محاذاة أفقية' : 'Horizontal align'}
                  name={`align_${item.id}`}
                  value={item.content_align ?? 'center'}
                  onChange={(e) => onUpdate({ content_align: e.target.value as ShowcaseItem['content_align'] })}
                  options={[
                    { value: 'start', label: locale === 'ar' ? 'بداية' : 'Start' },
                    { value: 'center', label: locale === 'ar' ? 'وسط' : 'Center' },
                    { value: 'end', label: locale === 'ar' ? 'نهاية' : 'End' },
                  ]}
                />
                <SelectInput
                  label={locale === 'ar' ? 'محاذاة رأسية' : 'Vertical align'}
                  name={`valign_${item.id}`}
                  value={item.content_v_align ?? 'center'}
                  onChange={(e) => onUpdate({ content_v_align: e.target.value as ShowcaseItem['content_v_align'] })}
                  options={[
                    { value: 'start', label: locale === 'ar' ? 'أعلى' : 'Top' },
                    { value: 'center', label: locale === 'ar' ? 'وسط' : 'Middle' },
                    { value: 'end', label: locale === 'ar' ? 'أسفل' : 'Bottom' },
                  ]}
                />
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="mb-1 block text-xs text-muted-foreground">{locale === 'ar' ? 'لون النص' : 'Text color'}</label>
                  <input
                    type="color"
                    value={item.text_color || '#ffffff'}
                    onChange={(e) => onUpdate({ text_color: e.target.value })}
                    className="h-9 w-full cursor-pointer rounded border border-[var(--border)]"
                  />
                </div>
              </div>
              {!useGlobalHeight && (
                <LengthUnitField
                  label={locale === 'ar' ? 'الحد الأدنى للارتفاع' : 'Min height'}
                  name={`mh_${item.id}`}
                  value={item.min_height}
                  unit={item.min_height_unit}
                  locale={locale}
                  preferredPx={360}
                  fallback={{ value: 360, unit: 'px' }}
                  onChange={(next) =>
                    onUpdate({
                      min_height: next.value,
                      min_height_unit: next.unit,
                    })
                  }
                />
              )}
              {useGlobalHeight && (
                <p className="text-[10px] text-muted-foreground">
                  {locale === 'ar'
                    ? 'ارتفاع الشريحة يُضبط من إعدادات السلايدر العامة.'
                    : 'Slide height is controlled from carousel settings above.'}
                </p>
              )}
            </>
          )}
        </div>
      )}
    </div>
  )
}

export default function ImageShowcaseEditor({
  content,
  options,
  locale,
  eventId,
  tenantId,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const items = normalizeShowcaseItems(content.items)
  const display = typeof options.display === 'string' ? options.display : 'grid'
  const columns = typeof options.columns === 'number' ? options.columns : 3
  const autoplay = options.autoplay === true
  const autoplayMs = typeof options.autoplay_ms === 'number' ? options.autoplay_ms : 4000
  const showArrows = options.show_arrows !== false
  const showDots = options.show_dots !== false
  const arrowsStyle = typeof options.arrows_style === 'string' ? options.arrows_style : 'circle'
  const dotsStyle = typeof options.dots_style === 'string' ? options.dots_style : 'dots'
  const dotsColor = typeof options.dots_color === 'string' ? options.dots_color : '#ffffff59'
  const dotsActiveColor = typeof options.dots_active_color === 'string' ? options.dots_active_color : '#8b5cf6'
  const arrowsColor = typeof options.arrows_color === 'string' ? options.arrows_color : '#ffffff'
  const pauseOnHover = options.pause_on_hover !== false
  const loop = options.loop !== false
  const dragToSlide = options.drag_to_slide === true
  const background = (options.background as SiteBackground) || { type: 'none' }
  const isCarousel = isCarouselDisplay(display)
  const slideHeight = parseCssLength(options.slide_height, options.slide_height_unit, {
    value: 400,
    unit: 'px',
  })
  const imageFit = typeof options.image_fit === 'string' ? options.image_fit : 'cover'

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const patchItems = useCallback(
    (mapper: (current: ShowcaseItem[]) => ShowcaseItem[]) => {
      onContentChange((current) => ({
        ...current,
        items: mapper(normalizeShowcaseItems(current.items)),
      }))
    },
    [onContentChange],
  )

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event
      if (over && active.id !== over.id) {
        patchItems((current) => {
          const oldIndex = current.findIndex((i) => i.id === active.id)
          const newIndex = current.findIndex((i) => i.id === over.id)
          if (oldIndex < 0 || newIndex < 0) return current
          return arrayMove(current, oldIndex, newIndex)
        })
      }
    },
    [patchItems],
  )

  const addItem = useCallback(() => {
    patchItems((current) => [...current, createEmptyShowcaseItem()])
  }, [patchItems])

  const updateItem = useCallback(
    (id: string, updates: Partial<ShowcaseItem> | ((item: ShowcaseItem) => Partial<ShowcaseItem>)) => {
      patchItems((current) =>
        current.map((item) => {
          if (item.id !== id) return item
          const patch = typeof updates === 'function' ? updates(item) : updates
          return { ...item, ...patch }
        }),
      )
    },
    [patchItems],
  )

  const removeItem = useCallback(
    (id: string) => {
      patchItems((current) => current.filter((item) => item.id !== id))
    },
    [patchItems],
  )

  return (
    <div className="space-y-4 rounded-lg bg-muted/30 p-4">
      <h3 className="text-lg font-semibold">
        {isCarousel
          ? locale === 'ar'
            ? 'سلايدر / كاروسيل'
            : 'Carousel'
          : locale === 'ar'
            ? 'معرض الصور'
            : 'Image Showcase'}
      </h3>

      <TextInput
        label={locale === 'ar' ? 'عنوان القسم' : 'Section title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
      />
      <TextareaInput
        label={locale === 'ar' ? 'عنوان فرعي' : 'Subtitle'}
        name="subtitle"
        value={subtitle}
        onChange={(e) => onContentChange({ subtitle: e.target.value })}
        rows={2}
      />

      <div className="grid grid-cols-2 gap-3">
        <SelectInput
          label={locale === 'ar' ? 'العرض' : 'Display'}
          name="display"
          value={isCarousel ? 'carousel' : 'grid'}
          onChange={(e) => onOptionsChange({ display: e.target.value })}
          options={[
            { value: 'grid', label: locale === 'ar' ? 'شبكة' : 'Grid' },
            { value: 'carousel', label: locale === 'ar' ? 'سلايدر' : 'Carousel' },
          ]}
        />
        {!isCarousel && (
          <SelectInput
            label={locale === 'ar' ? 'الأعمدة' : 'Columns'}
            name="columns"
            value={String(columns)}
            onChange={(e) => onOptionsChange({ columns: parseInt(e.target.value, 10) })}
            options={[
              { value: '2', label: '2' },
              { value: '3', label: '3' },
              { value: '4', label: '4' },
            ]}
          />
        )}
      </div>

      {isCarousel && (
        <div className="space-y-4 rounded-lg border border-white/10 bg-white/[0.03] p-3">
          <div>
            <p className="text-sm font-semibold text-white/90">
              {locale === 'ar' ? 'إعدادات السلايدر' : 'Carousel controls'}
            </p>
            <p className="mt-0.5 text-[11px] text-white/45">
              {locale === 'ar'
                ? 'الحجم، التشغيل، والأسهم والنقاط لكل الشرائح.'
                : 'Size, playback, and navigation for all slides.'}
            </p>
          </div>

          {/* Size */}
          <div className="space-y-3 border-t border-white/10 pt-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-white/40">
              {locale === 'ar' ? 'الحجم' : 'Size'}
            </p>
            <LengthUnitField
              label={locale === 'ar' ? 'ارتفاع الشرائح' : 'Slide height'}
              name="slide_height"
              value={slideHeight.value}
              unit={slideHeight.unit}
              locale={locale}
              preferredPx={400}
              fallback={{ value: 400, unit: 'px' }}
              onChange={(next) =>
                onOptionsChange({
                  slide_height: next.value,
                  slide_height_unit: next.unit,
                })
              }
              hint={
                locale === 'ar'
                  ? 'مثال: 400px أو 50vh أو 100%'
                  : 'e.g. 400px, 50vh, or 100%'
              }
            />
            <SelectInput
              label={locale === 'ar' ? 'ملاءمة الصورة' : 'Image fit'}
              name="image_fit"
              value={imageFit === 'contain' || imageFit === 'fill' ? imageFit : 'cover'}
              onChange={(e) => onOptionsChange({ image_fit: e.target.value })}
              options={[
                { value: 'cover', label: locale === 'ar' ? 'تغطية (Cover)' : 'Cover' },
                { value: 'contain', label: locale === 'ar' ? 'احتواء (Contain)' : 'Contain' },
                { value: 'fill', label: locale === 'ar' ? 'تمدد (Fill)' : 'Fill' },
              ]}
            />
          </div>

          {/* Behavior */}
          <div className="space-y-3 border-t border-white/10 pt-3">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-white/40">
              {locale === 'ar' ? 'التشغيل' : 'Playback'}
            </p>
            <div className="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
              <CheckboxInput
                label={locale === 'ar' ? 'تشغيل تلقائي' : 'Autoplay'}
                id="autoplay"
                checked={autoplay}
                onChange={(e) => onOptionsChange({ autoplay: e.target.checked })}
              />
              <CheckboxInput
                label={locale === 'ar' ? 'إيقاف عند المرور' : 'Pause on hover'}
                id="pause_on_hover"
                checked={pauseOnHover}
                onChange={(e) => onOptionsChange({ pause_on_hover: e.target.checked })}
              />
              <CheckboxInput
                label={locale === 'ar' ? 'تكرار مستمر' : 'Loop'}
                id="loop"
                checked={loop}
                onChange={(e) => onOptionsChange({ loop: e.target.checked })}
              />
              <CheckboxInput
                label={locale === 'ar' ? 'سحب للتنقل' : 'Drag to slide'}
                id="drag_to_slide"
                checked={dragToSlide}
                onChange={(e) => onOptionsChange({ drag_to_slide: e.target.checked })}
              />
            </div>
            {autoplay && (
              <TextInput
                label={locale === 'ar' ? 'مدة الشريحة (مللي ثانية)' : 'Slide duration (ms)'}
                name="autoplay_ms"
                type="number"
                min={1500}
                step={100}
                value={String(autoplayMs)}
                onChange={(e) => onOptionsChange({ autoplay_ms: Number(e.target.value) || 4000 })}
              />
            )}
          </div>

          {/* Arrows */}
          <div className="space-y-3 border-t border-white/10 pt-3">
            <div className="flex items-center justify-between gap-3">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-white/40">
                {locale === 'ar' ? 'الأسهم' : 'Arrows'}
              </p>
              <CheckboxInput
                label={locale === 'ar' ? 'إظهار' : 'Show'}
                id="show_arrows"
                checked={showArrows}
                onChange={(e) => onOptionsChange({ show_arrows: e.target.checked })}
              />
            </div>
            {showArrows && (
              <div className="grid grid-cols-1 gap-3">
                <SelectInput
                  label={locale === 'ar' ? 'الشكل' : 'Style'}
                  name="arrows_style"
                  value={arrowsStyle}
                  onChange={(e) => onOptionsChange({ arrows_style: e.target.value })}
                  options={[
                    { value: 'circle', label: locale === 'ar' ? 'دائري' : 'Circle' },
                    { value: 'square', label: locale === 'ar' ? 'مربع' : 'Square' },
                    { value: 'minimal', label: locale === 'ar' ? 'بسيط' : 'Minimal' },
                  ]}
                />
                <BuilderColorField
                  label={locale === 'ar' ? 'اللون' : 'Color'}
                  value={arrowsColor.startsWith('#') ? arrowsColor.slice(0, 7) : '#ffffff'}
                  onChange={(value) => onOptionsChange({ arrows_color: value || '#ffffff' })}
                />
              </div>
            )}
          </div>

          {/* Dots */}
          <div className="space-y-3 border-t border-white/10 pt-3">
            <div className="flex items-center justify-between gap-3">
              <p className="text-[11px] font-semibold uppercase tracking-wide text-white/40">
                {locale === 'ar' ? 'النقاط' : 'Dots'}
              </p>
              <CheckboxInput
                label={locale === 'ar' ? 'إظهار' : 'Show'}
                id="show_dots"
                checked={showDots}
                onChange={(e) => onOptionsChange({ show_dots: e.target.checked })}
              />
            </div>
            {showDots && (
              <div className="space-y-3">
                <SelectInput
                  label={locale === 'ar' ? 'الشكل' : 'Style'}
                  name="dots_style"
                  value={dotsStyle}
                  onChange={(e) => onOptionsChange({ dots_style: e.target.value })}
                  options={[
                    { value: 'dots', label: locale === 'ar' ? 'نقاط' : 'Dots' },
                    { value: 'bars', label: locale === 'ar' ? 'خطوط' : 'Bars' },
                    { value: 'numbers', label: locale === 'ar' ? 'أرقام' : 'Numbers' },
                  ]}
                />
                <div className="grid grid-cols-1 gap-3">
                  <BuilderColorField
                    label={locale === 'ar' ? 'لون عادي' : 'Inactive'}
                    value={dotsColor.startsWith('#') ? dotsColor.slice(0, 7) : '#94a3b8'}
                    onChange={(value) => onOptionsChange({ dots_color: value || '#94a3b8' })}
                  />
                  <BuilderColorField
                    label={locale === 'ar' ? 'لون نشط' : 'Active'}
                    value={dotsActiveColor.startsWith('#') ? dotsActiveColor.slice(0, 7) : '#8b5cf6'}
                    onChange={(value) => onOptionsChange({ dots_active_color: value || '#8b5cf6' })}
                  />
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      <div className="rounded-lg border border-violet-400/30 bg-violet-500/10 p-3">
        <BackgroundEditor
          compact
          idPrefix="showcase_section_bg"
          value={background}
          onChange={(bg) => onOptionsChange({ background: bg })}
          locale={locale}
          tenantId={tenantId}
          eventId={eventId}
          label={locale === 'ar' ? 'خلفية السيكشن كله' : 'Whole section background'}
        />
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <p className="text-sm font-medium">
            {locale === 'ar' ? 'الشرائح' : 'Slides'} ({items.length})
          </p>
          <button type="button" onClick={addItem} className="button-secondary py-1.5 text-xs">
            <Plus className="me-1 inline h-4 w-4" />
            {locale === 'ar' ? 'إضافة شريحة' : 'Add slide'}
          </button>
        </div>

        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={items.map((i) => i.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {items.map((item) => (
                <SortableItem
                  key={item.id}
                  item={item}
                  locale={locale}
                  eventId={eventId}
                  tenantId={tenantId}
                  useGlobalHeight={isCarousel}
                  onUpdate={(updates) => updateItem(item.id, updates)}
                  onRemove={() => removeItem(item.id)}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>

        {items.length === 0 && (
          <p className="py-4 text-center text-sm text-muted-foreground">
            {locale === 'ar' ? 'لا توجد شرائح. أضف شريحة للبدء.' : 'No slides yet. Add one to start.'}
          </p>
        )}
      </div>
    </div>
  )
}
