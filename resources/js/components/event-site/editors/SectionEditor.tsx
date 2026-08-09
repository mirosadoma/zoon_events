import { useCallback, useState } from 'react'
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
import { Plus, Trash2, GripVertical, Upload } from 'lucide-react'
import LayoutPresetPicker, { presetLayout } from '../LayoutPresetPicker'
import ElementStylePanel from '../ElementStylePanel'
import LengthUnitField from '../builder/LengthUnitField'
import { apiFetch } from '@/lib/apiFetch'
import { defaultFreeformPlacement } from '@/lib/sectionFreeformLayout'
import { parseCssLength } from '@/lib/cssLength'

type SectionElement = {
  id: string
  kind: string
  col_span: number
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

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  onContentChange: (updates: Record<string, unknown>) => void
  onOptionsChange: (updates: Record<string, unknown>) => void
  onRefsChange: (updates: Record<string, unknown>) => void
  selectedElementId?: string | null
}

function asElements(value: unknown): SectionElement[] {
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
      align: typeof item.align === 'string' ? (item.align as 'start' | 'center' | 'end') : undefined,
      v_align: typeof item.v_align === 'string' ? (item.v_align as 'start' | 'center' | 'end') : undefined,
      title: typeof item.title === 'string' ? item.title : '',
      body: typeof item.body === 'string' ? item.body : '',
      label: typeof item.label === 'string' ? item.label : '',
      href: typeof item.href === 'string' ? item.href : '',
      src: typeof item.src === 'string' ? item.src : '',
      alt: typeof item.alt === 'string' ? item.alt : '',
      style: typeof item.style === 'object' && item.style !== null && !Array.isArray(item.style)
        ? (item.style as Record<string, unknown>)
        : undefined,
    }))
}

const KIND_OPTIONS = [
  { value: 'heading', labelEn: 'Heading', labelAr: 'عنوان' },
  { value: 'text', labelEn: 'Text', labelAr: 'نص' },
  { value: 'image', labelEn: 'Image', labelAr: 'صورة' },
  { value: 'button', labelEn: 'Button', labelAr: 'زر' },
  { value: 'card', labelEn: 'Card', labelAr: 'بطاقة' },
  { value: 'divider', labelEn: 'Divider', labelAr: 'فاصل' },
  { value: 'quote', labelEn: 'Quote', labelAr: 'اقتباس' },
  { value: 'video', labelEn: 'Video', labelAr: 'فيديو' },
  { value: 'list', labelEn: 'List', labelAr: 'قائمة' },
  { value: 'icon', labelEn: 'Icon', labelAr: 'أيقونة' },
  { value: 'html', labelEn: 'HTML', labelAr: 'HTML' },
  { value: 'spacer', labelEn: 'Spacer', labelAr: 'فراغ' },
]

const SPAN_OPTIONS = [
  { value: '1', label: '1/12' },
  { value: '2', label: '2/12' },
  { value: '3', label: '3/12 (25%)' },
  { value: '4', label: '4/12 (33%)' },
  { value: '5', label: '5/12' },
  { value: '6', label: '6/12 (50%)' },
  { value: '7', label: '7/12' },
  { value: '8', label: '8/12 (66%)' },
  { value: '9', label: '9/12 (75%)' },
  { value: '10', label: '10/12' },
  { value: '11', label: '11/12' },
  { value: '12', label: '12/12 (100%)' },
]

const ALIGN_OPTIONS = [
  { value: '', labelEn: 'Default', labelAr: 'افتراضي' },
  { value: 'start', labelEn: 'Start', labelAr: 'بداية' },
  { value: 'center', labelEn: 'Center', labelAr: 'وسط' },
  { value: 'end', labelEn: 'End', labelAr: 'نهاية' },
]

type SortableElementProps = {
  element: SectionElement
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  layoutFreeform: boolean
  selectedElementId?: string | null
  onUpdate: (patch: Partial<SectionElement>) => void
  onRemove: () => void
}

function SortableElement({ element, locale, eventId, tenantId, layoutFreeform, selectedElementId, onUpdate, onRemove }: SortableElementProps) {
  const [uploading, setUploading] = useState(false)
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: element.id })

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
      try {
        const formData = new FormData()
        formData.append('file', file)
        const response = await apiFetch<{ path: string; url: string }>(
          `/api/v1/tenant/events/${eventId}/site/media`,
          { method: 'POST', tenantId, body: formData },
        )
        onUpdate({ src: response.url })
      } catch (err) {
        console.error('Failed to upload image:', err)
      } finally {
        setUploading(false)
        e.target.value = ''
      }
    },
    [eventId, tenantId, onUpdate],
  )

  return (
    <div ref={setNodeRef} style={style} className={`space-y-3 rounded-md border p-3 bg-background ${element.id === selectedElementId ? 'border-violet-500 ring-1 ring-violet-400/40' : 'border-[var(--border)]'}`}>
      <div className="flex items-center gap-2">
        <button
          type="button"
          className="cursor-grab active:cursor-grabbing shrink-0 p-1 text-muted-foreground hover:text-foreground"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-4 w-4" />
        </button>

        <div className="min-w-0 flex-1 text-xs font-medium text-muted-foreground truncate">
          {KIND_OPTIONS.find((k) => k.value === element.kind)?.[locale === 'ar' ? 'labelAr' : 'labelEn'] ?? element.kind}
          {layoutFreeform ? (
            <span className="text-muted-foreground/70">
              {' '}
              · {Math.round(element.x_pct ?? 0)}%, {Math.round(element.y_pct ?? 0)}%
            </span>
          ) : (
            <span className="text-muted-foreground/70"> · {element.col_span}/12</span>
          )}
        </div>

        <button
          type="button"
          className="button-secondary shrink-0 p-2"
          onClick={onRemove}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <SelectInput
          label={locale === 'ar' ? 'النوع' : 'Kind'}
          name={`kind_${element.id}`}
          value={element.kind}
          onChange={(e) => onUpdate({ kind: e.target.value })}
          options={KIND_OPTIONS.map((kind) => ({
            value: kind.value,
            label: locale === 'ar' ? kind.labelAr : kind.labelEn,
          }))}
        />
        {layoutFreeform ? (
          <>
            <TextInput
              label={locale === 'ar' ? 'X %' : 'X %'}
              name={`x_${element.id}`}
              value={String(element.x_pct ?? 0)}
              onChange={(e) => onUpdate({ x_pct: Number(e.target.value) })}
            />
            <TextInput
              label={locale === 'ar' ? 'Y %' : 'Y %'}
              name={`y_${element.id}`}
              value={String(element.y_pct ?? 0)}
              onChange={(e) => onUpdate({ y_pct: Number(e.target.value) })}
            />
            <TextInput
              label={locale === 'ar' ? 'عرض %' : 'Width %'}
              name={`w_${element.id}`}
              value={String(element.width_pct ?? 30)}
              onChange={(e) => onUpdate({ width_pct: Number(e.target.value) })}
            />
            <TextInput
              label={locale === 'ar' ? 'ارتفاع %' : 'Height %'}
              name={`h_${element.id}`}
              value={String(element.height_pct ?? '')}
              onChange={(e) =>
                onUpdate({
                  height_pct: e.target.value === '' ? undefined : Number(e.target.value),
                })
              }
              placeholder={locale === 'ar' ? 'تلقائي' : 'Auto'}
            />
            <TextInput
              label={locale === 'ar' ? 'Z-index' : 'Z-index'}
              name={`z_${element.id}`}
              value={String(element.z_index ?? 1)}
              onChange={(e) => onUpdate({ z_index: Number(e.target.value) })}
            />
          </>
        ) : (
          <SelectInput
            label={locale === 'ar' ? 'العمود' : 'Span'}
            name={`span_${element.id}`}
            value={String(element.col_span)}
            onChange={(e) => onUpdate({ col_span: Number(e.target.value) })}
            options={SPAN_OPTIONS}
          />
        )}
        <SelectInput
          label={locale === 'ar' ? 'محاذاة H' : 'H-Align'}
          name={`align_${element.id}`}
          value={element.align || ''}
          onChange={(e) => onUpdate({ align: e.target.value as 'start' | 'center' | 'end' || undefined })}
          options={ALIGN_OPTIONS.map((opt) => ({
            value: opt.value,
            label: locale === 'ar' ? opt.labelAr : opt.labelEn,
          }))}
        />
        <SelectInput
          label={locale === 'ar' ? 'محاذاة V' : 'V-Align'}
          name={`v_align_${element.id}`}
          value={element.v_align || ''}
          onChange={(e) => onUpdate({ v_align: e.target.value as 'start' | 'center' | 'end' || undefined })}
          options={ALIGN_OPTIONS.map((opt) => ({
            value: opt.value,
            label: locale === 'ar' ? opt.labelAr : opt.labelEn,
          }))}
        />
      </div>

      {(element.kind === 'heading' || element.kind === 'card') && (
        <TextInput
          label={locale === 'ar' ? 'العنوان' : 'Title'}
          name={`title_${element.id}`}
          value={element.title ?? ''}
          onChange={(e) => onUpdate({ title: e.target.value })}
        />
      )}

      {(element.kind === 'text' || element.kind === 'card' || element.kind === 'heading') && (
        <TextareaInput
          label={locale === 'ar' ? 'المحتوى' : 'Content'}
          name={`body_${element.id}`}
          value={element.body ?? ''}
          onChange={(e) => onUpdate({ body: e.target.value })}
          rows={3}
        />
      )}

      {(element.kind === 'quote' || element.kind === 'html') && (
        <TextareaInput
          label={element.kind === 'html' ? (locale === 'ar' ? 'HTML' : 'HTML code') : (locale === 'ar' ? 'الاقتباس' : 'Quote')}
          name={`body_${element.id}`}
          value={element.body ?? ''}
          onChange={(e) => onUpdate({ body: e.target.value })}
          rows={element.kind === 'html' ? 4 : 3}
          dir={element.kind === 'html' ? 'ltr' : undefined}
        />
      )}

      {element.kind === 'video' && (
        <TextInput
          label={locale === 'ar' ? 'رابط الفيديو (YouTube/Vimeo)' : 'Video URL (YouTube/Vimeo)'}
          name={`href_${element.id}`}
          value={element.href ?? ''}
          onChange={(e) => onUpdate({ href: e.target.value })}
          placeholder="https://youtube.com/watch?v=..."
        />
      )}

      {element.kind === 'list' && (
        <TextareaInput
          label={locale === 'ar' ? 'عناصر القائمة (سطر لكل عنصر)' : 'List items (one per line)'}
          name={`body_${element.id}`}
          value={element.body ?? ''}
          onChange={(e) => onUpdate({ body: e.target.value })}
          rows={4}
        />
      )}

      {element.kind === 'icon' && (
        <TextInput
          label={locale === 'ar' ? 'أيقونة / emoji' : 'Icon / emoji'}
          name={`label_${element.id}`}
          value={element.label ?? ''}
          onChange={(e) => onUpdate({ label: e.target.value })}
          placeholder="★ or 🎉"
        />
      )}

      {element.kind === 'divider' && (
        <p className="text-xs text-muted-foreground">{locale === 'ar' ? 'خط فاصل بعرض العمود' : 'Horizontal rule across column span'}</p>
      )}

      {element.kind === 'button' && (
        <div className="grid gap-2 sm:grid-cols-2">
          <TextInput
            label={locale === 'ar' ? 'نص الزر' : 'Button label'}
            name={`label_${element.id}`}
            value={element.label ?? ''}
            onChange={(e) => onUpdate({ label: e.target.value })}
          />
          <TextInput
            label={locale === 'ar' ? 'الرابط' : 'URL'}
            name={`href_${element.id}`}
            value={element.href ?? ''}
            onChange={(e) => onUpdate({ href: e.target.value })}
          />
        </div>
      )}

      {element.kind === 'image' && (
        <div className="space-y-2">
          {element.src && (
            <img src={element.src} alt={element.alt || ''} className="h-24 w-full object-cover rounded" />
          )}
          <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
            <TextInput
              label={locale === 'ar' ? 'رابط الصورة' : 'Image URL'}
              name={`src_${element.id}`}
              value={element.src ?? ''}
              onChange={(e) => onUpdate({ src: e.target.value })}
            />
            <label className="flex items-end">
              <span className="button-secondary inline-flex items-center gap-1 cursor-pointer">
                <Upload className="h-4 w-4" />
                {uploading ? '...' : (locale === 'ar' ? 'رفع' : 'Upload')}
              </span>
              <input
                type="file"
                accept="image/*"
                onChange={handleImageUpload}
                disabled={uploading}
                className="hidden"
              />
            </label>
          </div>
          <TextInput
            label={locale === 'ar' ? 'النص البديل' : 'Alt text'}
            name={`alt_${element.id}`}
            value={element.alt ?? ''}
            onChange={(e) => onUpdate({ alt: e.target.value })}
          />
        </div>
      )}

      <ElementStylePanel
        style={element.style || {}}
        locale={locale}
        kind={element.kind}
        onChange={(style) => onUpdate({ style })}
      />
    </div>
  )
}

export default function SectionEditor({ content, options, locale, eventId, tenantId, onContentChange, onOptionsChange, selectedElementId = null }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const elements = asElements(content.elements)
  const gap = typeof options.gap === 'string' ? options.gap : 'md'
  const padding = typeof options.padding === 'string' ? options.padding : 'lg'
  const layoutPreset = typeof options.layout_preset === 'string' ? options.layout_preset : '2'
  const layoutMode = typeof options.layout_mode === 'string' ? options.layout_mode : 'grid'
  const layoutFreeform = layoutMode === 'freeform'
  const freeformHeight = parseCssLength(options.freeform_height, options.freeform_height_unit, {
    value: 480,
    unit: 'px',
  })
  const backgroundPreset = typeof options.background_preset === 'string' ? options.background_preset : ''

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  function setElements(next: SectionElement[]) {
    onContentChange({ elements: next.map((el, i) => ({ ...el, order: i })) })
  }

  function addElement(kind: string) {
    const placement = layoutFreeform ? defaultFreeformPlacement(elements.length) : {}
    setElements([
      ...elements,
      {
        id: `e_${Math.random().toString(36).slice(2, 8)}`,
        kind,
        col_span: kind === 'spacer' ? 12 : 6,
        order: elements.length,
        title: '',
        body: kind === 'text' || kind === 'card' ? (locale === 'ar' ? 'نص جديد' : 'New content') : '',
        label: kind === 'button' ? (locale === 'ar' ? 'زر' : 'Button') : '',
        href: kind === 'button' ? '#' : '',
        src: '',
        alt: '',
        ...placement,
      },
    ])
  }

  function clearFreeformPlacement<T extends SectionElement>(el: T): T {
    const next = { ...el }
    delete (next as { x_pct?: number }).x_pct
    delete (next as { y_pct?: number }).y_pct
    delete (next as { width_pct?: number }).width_pct
    delete (next as { height_pct?: number }).height_pct
    delete (next as { z_index?: number }).z_index
    return next
  }

  function handleLayoutModeChange(mode: string) {
    if (mode === 'freeform') {
      onOptionsChange({ layout_mode: 'freeform' })
      return
    }
    // BlockEditor strips freeform coordinates when switching back to grid.
    onOptionsChange({ layout_mode: 'grid' })
  }

  function updateElement(id: string, patch: Partial<SectionElement>) {
    setElements(elements.map((item) => (item.id === id ? { ...item, ...patch } : item)))
  }

  function removeElement(id: string) {
    setElements(elements.filter((item) => item.id !== id))
  }

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    if (over && active.id !== over.id) {
      const oldIndex = elements.findIndex((el) => el.id === active.id)
      const newIndex = elements.findIndex((el) => el.id === over.id)
      setElements(arrayMove(elements, oldIndex, newIndex))
    }
  }

  function handleLayoutPresetChange(preset: string) {
    onOptionsChange({ layout_preset: preset, layout_mode: 'grid' })
    const { spans, starts } = presetLayout(preset)

    if (elements.length === 0) {
      const newElements: SectionElement[] = spans.map((span, i) => ({
        id: `e_${Math.random().toString(36).slice(2, 8)}`,
        kind: 'card',
        col_span: span,
        col_start: starts[i],
        order: i,
        title: locale === 'ar' ? `بطاقة ${i + 1}` : `Card ${i + 1}`,
        body: locale === 'ar' ? 'محتوى جديد' : 'New content',
        label: '',
        href: '',
        src: '',
        alt: '',
      }))
      setElements(newElements)
    } else {
      const redistributed = elements.map((el, i) =>
        clearFreeformPlacement({
          ...el,
          col_span: i < spans.length ? spans[i % spans.length] : 12,
          col_start: i < starts.length ? starts[i % starts.length] : undefined,
        }),
      )
      setElements(redistributed)
    }
  }

  function applyEqualColumns(n: number) {
    const span = Math.floor(12 / n)
    if (elements.length === 0) {
      const newElements: SectionElement[] = Array.from({ length: n }, (_, i) => ({
        id: `e_${Math.random().toString(36).slice(2, 8)}`,
        kind: 'card',
        col_span: span,
        col_start: i * span + 1,
        order: i,
        title: locale === 'ar' ? `بطاقة ${i + 1}` : `Card ${i + 1}`,
        body: locale === 'ar' ? 'محتوى جديد' : 'New content',
        label: '',
        href: '',
        src: '',
        alt: '',
      }))
      setElements(newElements)
    } else {
      setElements(
        elements.map((el, i) =>
          clearFreeformPlacement({
            ...el,
            col_span: span,
            col_start: (i % n) * span + 1,
          }),
        ),
      )
    }
  }

  return (
    <div className="space-y-4 p-4 rounded-lg border border-[var(--border)] bg-[var(--surface)]">
      <h3 className="text-lg font-semibold">
        {layoutFreeform
          ? locale === 'ar'
            ? 'قسم حر (Freeform)'
            : 'Freeform section'
          : locale === 'ar'
            ? 'قسم شبكي'
            : 'Grid section'}
      </h3>

      <TextInput
        label={locale === 'ar' ? 'عنوان القسم' : 'Section title'}
        name="section_title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
      />
      <TextInput
        label={locale === 'ar' ? 'عنوان فرعي' : 'Subtitle'}
        name="section_subtitle"
        value={subtitle}
        onChange={(e) => onContentChange({ subtitle: e.target.value })}
      />

      <div className="space-y-2">
        <p className="text-sm font-medium text-[var(--ink)]">
          {locale === 'ar' ? 'وضع التخطيط' : 'Layout mode'}
        </p>
        <div className="grid grid-cols-2 gap-1.5">
          <button
            type="button"
            onClick={() => handleLayoutModeChange('grid')}
            className={`rounded-lg border px-3 py-2.5 text-xs font-semibold transition ${
              !layoutFreeform
                ? 'border-violet-400 bg-violet-600 text-white shadow'
                : 'border-white/15 bg-white/5 text-white/70 hover:border-violet-400/50 hover:text-white'
            }`}
          >
            {locale === 'ar' ? 'شبكة (Grid)' : 'Grid'}
          </button>
          <button
            type="button"
            onClick={() => handleLayoutModeChange('freeform')}
            className={`rounded-lg border px-3 py-2.5 text-xs font-semibold transition ${
              layoutFreeform
                ? 'border-violet-400 bg-violet-600 text-white shadow'
                : 'border-white/15 bg-white/5 text-white/70 hover:border-violet-400/50 hover:text-white'
            }`}
          >
            {locale === 'ar' ? 'حر (Freeform)' : 'Freeform'}
          </button>
        </div>
        <p className="text-[11px] leading-relaxed text-muted-foreground">
          {locale === 'ar'
            ? 'Freeform = اسحب العناصر بحرية داخل السيكشن (مثل Figma).'
            : 'Freeform = drag elements freely inside the section (Figma-like).'}
        </p>
      </div>

      {layoutFreeform && (
        <LengthUnitField
          label={locale === 'ar' ? 'ارتفاع السيكشن' : 'Section height'}
          name="section_freeform_height"
          value={freeformHeight.value}
          unit={freeformHeight.unit}
          locale={locale}
          preferredPx={480}
          fallback={{ value: 480, unit: 'px' }}
          onChange={(next) =>
            onOptionsChange({
              freeform_height: next.value,
              freeform_height_unit: next.unit,
            })
          }
        />
      )}

      {!layoutFreeform && (
        <LayoutPresetPicker
          value={layoutPreset}
          onChange={handleLayoutPresetChange}
          locale={locale}
          inspector
        />
      )}

      <p className="rounded-md border border-violet-400/25 bg-violet-500/10 px-3 py-2 text-[11px] leading-relaxed text-violet-200/90">
        {layoutFreeform
          ? locale === 'ar'
            ? 'على الصفحة: انقر العنصر واسحب الشارة للتحريك، المقبض الأيمن للعرض، والمقبض السفلي للارتفاع.'
            : 'On canvas: select an element, drag the badge to move, right handle for width, bottom handle for height.'
          : locale === 'ar'
            ? 'على الصفحة: انقر العنصر ثم اسحب الشارة لتحريكه أو المقبض الأيمن لتغيير العرض (شبكة 12 عمود).'
            : 'On canvas: click an element, drag the badge to move it, or the right handle to resize (12-column grid).'}
      </p>

      {!layoutFreeform && (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
          <span className="shrink-0 text-xs text-muted-foreground">
            {locale === 'ar' ? 'أعمدة متساوية:' : 'Equal columns:'}
          </span>
          <div className="flex flex-wrap gap-1">
            {[1, 2, 3, 4, 6].map((n) => (
              <button
                key={n}
                type="button"
                className="button-secondary min-w-[2rem] text-xs py-1 px-2.5"
                onClick={() => applyEqualColumns(n)}
              >
                {n}
              </button>
            ))}
          </div>
        </div>
      )}

      <div className="grid grid-cols-2 gap-2">
        <SelectInput
          label={locale === 'ar' ? 'خلفية سريعة' : 'Quick background'}
          name="section_bg_preset"
          value={backgroundPreset}
          onChange={(e) => onOptionsChange({ background_preset: e.target.value })}
          options={[
            { value: '', label: locale === 'ar' ? 'افتراضي' : 'Default' },
            { value: 'muted', label: locale === 'ar' ? 'رمادي فاتح' : 'Muted' },
            { value: 'brand', label: locale === 'ar' ? 'اللون الأساسي' : 'Brand' },
            { value: 'dark', label: locale === 'ar' ? 'داكن' : 'Dark' },
          ]}
        />
        <SelectInput
          label={locale === 'ar' ? 'المسافة' : 'Gap'}
          name="section_gap"
          value={gap}
          onChange={(e) => onOptionsChange({ gap: e.target.value })}
          options={[
            { value: 'sm', label: 'S' },
            { value: 'md', label: 'M' },
            { value: 'lg', label: 'L' },
          ]}
        />
        <SelectInput
          label={locale === 'ar' ? 'الحشو' : 'Padding'}
          name="section_padding"
          value={padding}
          onChange={(e) => onOptionsChange({ padding: e.target.value })}
          options={[
            { value: 'sm', label: 'S' },
            { value: 'md', label: 'M' },
            { value: 'lg', label: 'L' },
            { value: 'xl', label: 'XL' },
          ]}
        />
      </div>

      <div className="space-y-3">
        <p className="text-sm font-medium">
          {layoutFreeform
            ? locale === 'ar'
              ? 'عناصر حرة'
              : 'Freeform elements'
            : locale === 'ar'
              ? 'عناصر الشبكة'
              : 'Grid elements'}
        </p>
        <div className="grid grid-cols-2 gap-1.5">
          {KIND_OPTIONS.map((kind) => (
            <button
              key={kind.value}
              type="button"
              className="button-secondary flex items-center justify-center gap-1 text-[11px] py-1.5 px-2"
              onClick={() => addElement(kind.value)}
            >
              <Plus className="h-3 w-3 shrink-0" />
              <span className="truncate">{locale === 'ar' ? kind.labelAr : kind.labelEn}</span>
            </button>
          ))}
        </div>

        {elements.length === 0 && (
          <p className="text-sm text-muted-foreground">
            {locale === 'ar'
              ? 'أضف عناصر داخل شبكة من 12 عموداً.'
              : 'Add elements into a 12-column responsive grid.'}
          </p>
        )}

        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={elements.map((el) => el.id)} strategy={verticalListSortingStrategy}>
            {elements.map((element) => (
              <SortableElement
                key={element.id}
                element={element}
                locale={locale}
                eventId={eventId}
                tenantId={tenantId}
                layoutFreeform={layoutFreeform}
                selectedElementId={selectedElementId}
                onUpdate={(patch) => updateElement(element.id, patch)}
                onRemove={() => removeElement(element.id)}
              />
            ))}
          </SortableContext>
        </DndContext>
      </div>
    </div>
  )
}
