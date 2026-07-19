import { useState, useMemo, useCallback, useId } from 'react'
import { router } from '@inertiajs/react'
import {
  DndContext,
  DragOverlay,
  closestCenter,
  useSensor,
  useSensors,
  PointerSensor,
  type DragStartEvent,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  verticalListSortingStrategy,
  useSortable,
  arrayMove,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import {
  Type, Mail, Phone, Hash, Calendar, List, CheckSquare,
  CircleDot, ToggleLeft, EyeOff, Heading1, Minus, AlignLeft,
  Plus, Trash2, GripVertical, Code, Palette, Save, Eye,
  Copy, X, ChevronRight,
} from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import {
  REGISTRATION_SYSTEM_FIELDS,
  ADDABLE_REGISTRATION_FIELD_TYPES,
  splitRegistrationFields,
} from '@/lib/registrationSystemFields'

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

type FieldOptionRow = { id: string; label_en: string; label_ar: string }

type FormField = {
  id: string
  key: string
  type: string
  label_en: string
  label_ar: string
  placeholder_en?: string
  placeholder_ar?: string
  required: boolean
  width: 'full' | 'half' | 'third'
  system?: boolean
  options?: FieldOptionRow[]
  content?: string
}

type ThemeConfig = {
  primary_color: string
  accent_color: string
  background_color: string
  font_family: string
}

type EventRow = { id: string; name: { en: string; ar: string }; slug?: string }

type Props = {
  event: EventRow
  tenantId: string
  formName: string
  privacyNoticeVersion: string
  termsVersion: string
  fields: Array<{
    key: string; type: string; label_en: string; label_ar: string
    required: boolean; system?: boolean; options?: FieldOptionRow[]
    placeholder_en?: string; placeholder_ar?: string; width?: string
    content?: string
  }>
  hasUnpublishedChanges?: boolean
  theme?: ThemeConfig | null
}

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const FIELD_TYPE_ICONS: Record<string, typeof Type> = {
  text: Type, email: Mail, phone: Phone, number: Hash, date: Calendar,
  select: List, multi_select: CheckSquare, radio: CircleDot,
  checkbox: ToggleLeft, consent: ToggleLeft, hidden: EyeOff,
  heading: Heading1, divider: Minus, paragraph: AlignLeft,
}

const PALETTE_CATEGORIES = [
  {
    label: 'Input Fields',
    types: ['text', 'number', 'date', 'select', 'multi_select', 'radio', 'checkbox'],
  },
  { label: 'Special', types: ['consent', 'hidden'] },
  { label: 'Layout', types: ['heading', 'divider', 'paragraph'] },
]

const TYPE_LABELS: Record<string, { en: string; ar: string }> = {
  text: { en: 'Text', ar: 'نص' },
  email: { en: 'Email', ar: 'بريد' },
  phone: { en: 'Phone', ar: 'هاتف' },
  number: { en: 'Number', ar: 'رقم' },
  date: { en: 'Date', ar: 'تاريخ' },
  select: { en: 'Dropdown', ar: 'قائمة' },
  multi_select: { en: 'Multi Select', ar: 'اختيار متعدد' },
  radio: { en: 'Radio', ar: 'اختيار واحد' },
  checkbox: { en: 'Checkbox', ar: 'مربع اختيار' },
  consent: { en: 'Consent', ar: 'موافقة' },
  hidden: { en: 'Hidden', ar: 'مخفي' },
  heading: { en: 'Heading', ar: 'عنوان' },
  divider: { en: 'Divider', ar: 'فاصل' },
  paragraph: { en: 'Paragraph', ar: 'فقرة' },
}

const FONT_OPTIONS = ['Inter', 'Cairo', 'Tajawal', 'system-ui']

const CHOICE_TYPES = new Set(['select', 'multi_select', 'radio', 'checkbox'])
const LAYOUT_TYPES = new Set(['heading', 'divider', 'paragraph'])

let fieldIdCounter = 0
const nextFieldId = () => `f_${Date.now()}_${++fieldIdCounter}`

function slugify(label: string): string {
  const base = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40)
  return base.length >= 2 ? base : `field_${Date.now()}`
}

/* ------------------------------------------------------------------ */
/*  Sortable Field Card                                                */
/* ------------------------------------------------------------------ */

function SortableFieldCard({
  field, selected, onSelect, locale,
}: {
  field: FormField; selected: boolean; onSelect: () => void; locale: 'en' | 'ar'
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: field.id })
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
  }

  const Icon = FIELD_TYPE_ICONS[field.type] ?? Type
  const widthClass = field.width === 'half' ? 'w-1/2' : field.width === 'third' ? 'w-1/3' : 'w-full'

  return (
    <div ref={setNodeRef} style={style} className={`${widthClass} p-1`}>
      <div
        onClick={onSelect}
        className={`group relative rounded-xl border p-3 transition-all cursor-pointer ${
          selected
            ? 'border-[var(--brand)] ring-2 ring-[var(--brand)]/15 bg-[var(--brand-soft)]'
            : 'border-[var(--border)] hover:border-[var(--brand)]/30 bg-[var(--surface-elevated)]'
        } ${field.system ? 'opacity-75' : ''}`}
      >
        <div className="flex items-center gap-2">
          {!field.system && (
            <button
              type="button"
              className="cursor-grab text-[var(--muted)] opacity-40 hover:opacity-100 active:cursor-grabbing"
              {...attributes}
              {...listeners}
            >
              <GripVertical size={14} />
            </button>
          )}
          <Icon size={14} className="text-[var(--muted)] shrink-0" />
          <span className="text-sm font-medium text-[var(--ink)] truncate flex-1">
            {locale === 'ar' ? field.label_ar : field.label_en}
          </span>
          {field.system && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-[var(--surface)] text-[var(--muted)] font-medium uppercase border border-[var(--border)]">
              System
            </span>
          )}
          {field.required && !field.system && (
            <span className="text-[var(--danger,#ef4444)] text-xs font-bold">*</span>
          )}
        </div>

        {/* Field preview */}
        {field.type === 'heading' ? (
          <div className="mt-2 text-base font-semibold text-[var(--ink)]">
            {field.content || (locale === 'ar' ? field.label_ar : field.label_en)}
          </div>
        ) : field.type === 'divider' ? (
          <hr className="mt-3 border-[var(--border)]" />
        ) : field.type === 'paragraph' ? (
          <p className="mt-2 text-xs text-[var(--muted)]">{field.content || 'Paragraph text...'}</p>
        ) : field.type !== 'hidden' && field.type !== 'consent' ? (
          <div className="mt-2">
            <div className="h-9 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 flex items-center">
              <span className="text-xs text-[var(--muted)]">
                {locale === 'ar' ? (field.placeholder_ar || field.label_ar) : (field.placeholder_en || field.label_en)}
              </span>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Main Component                                                     */
/* ------------------------------------------------------------------ */

export default function RegistrationBuilder({
  event,
  tenantId,
  formName: initialFormName,
  privacyNoticeVersion: initialPrivacy,
  termsVersion: initialTerms,
  fields: initialFields,
  hasUnpublishedChanges = false,
  theme: initialTheme,
}: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const dndId = useId()

  const [formName, setFormName] = useState(initialFormName)
  const [privacyNoticeVersion] = useState(initialPrivacy)
  const [termsVersion] = useState(initialTerms)
  const [submitting, setSubmitting] = useState(false)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [activeDragId, setActiveDragId] = useState<string | null>(null)

  const [theme, setTheme] = useState<ThemeConfig>(initialTheme ?? {
    primary_color: '#3b82f6',
    accent_color: '#8b5cf6',
    background_color: '#ffffff',
    font_family: 'Inter',
  })
  const [showThemePanel, setShowThemePanel] = useState(false)
  const [showEmbedModal, setShowEmbedModal] = useState(false)

  const [fields, setFields] = useState<FormField[]>(() => {
    const all = initialFields.map((f, i) => ({
      id: `field_${i}_${f.key}`,
      key: f.key,
      type: f.type,
      label_en: f.label_en,
      label_ar: f.label_ar,
      placeholder_en: f.placeholder_en ?? '',
      placeholder_ar: f.placeholder_ar ?? '',
      required: f.required,
      width: (f.width as 'full' | 'half' | 'third') ?? 'full',
      system: f.system,
      options: f.options,
      content: f.content ?? '',
    }))
    const { customFields } = splitRegistrationFields(all)
    const systemFields: FormField[] = REGISTRATION_SYSTEM_FIELDS.map((sf, i) => ({
      id: `sys_${i}_${sf.key}`,
      key: sf.key,
      type: sf.type,
      label_en: sf.label_en,
      label_ar: sf.label_ar,
      required: true,
      width: 'full' as const,
      system: true,
    }))
    return [...systemFields, ...customFields]
  })

  const selected = fields.find((f) => f.id === selectedId) ?? null

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
  )

  const canvasStyle = useMemo(() => ({
    backgroundColor: theme.background_color || '#ffffff',
    fontFamily: theme.font_family || 'Inter',
    '--reg-primary': theme.primary_color || '#3b82f6',
    '--reg-accent': theme.accent_color || '#8b5cf6',
  } as React.CSSProperties), [theme])

  /* ---- DnD handlers ---- */

  const handleDragStart = useCallback((event: DragStartEvent) => {
    setActiveDragId(String(event.active.id))
  }, [])

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    setActiveDragId(null)
    const { active, over } = event
    if (!over || active.id === over.id) return

    setFields((prev) => {
      const oldIndex = prev.findIndex((f) => f.id === active.id)
      const newIndex = prev.findIndex((f) => f.id === over.id)
      if (oldIndex === -1 || newIndex === -1) return prev
      const systemCount = prev.filter((f) => f.system).length
      if (oldIndex < systemCount || newIndex < systemCount) return prev
      return arrayMove(prev, oldIndex, newIndex)
    })
  }, [])

  /* ---- Field CRUD ---- */

  const addField = (type: string) => {
    const labelEn = TYPE_LABELS[type]?.en ?? type
    const labelAr = TYPE_LABELS[type]?.ar ?? type
    const key = LAYOUT_TYPES.has(type) ? `${type}_${Date.now()}` : slugify(labelEn)
    const newField: FormField = {
      id: nextFieldId(),
      key,
      type,
      label_en: labelEn,
      label_ar: labelAr,
      required: false,
      width: 'full',
      options: CHOICE_TYPES.has(type) ? [
        { id: 'opt_1', label_en: 'Option 1', label_ar: 'خيار 1' },
        { id: 'opt_2', label_en: 'Option 2', label_ar: 'خيار 2' },
      ] : undefined,
      content: '',
    }
    setFields((prev) => [...prev, newField])
    setSelectedId(newField.id)
  }

  const deleteField = (id: string) => {
    setFields((prev) => prev.filter((f) => f.id !== id))
    if (selectedId === id) setSelectedId(null)
  }

  const updateField = (id: string, patch: Partial<FormField>) => {
    setFields((prev) => prev.map((f) => (f.id === id ? { ...f, ...patch } : f)))
  }

  /* ---- Save ---- */

  const handleSave = async () => {
    setSubmitting(true)
    const payload = {
      name: formName,
      fields: fields.map((f) => {
        const row: Record<string, unknown> = {
          key: f.key,
          type: f.type,
          label_en: f.label_en,
          label_ar: f.label_ar,
          required: f.required,
          width: f.width,
          visibility: f.type === 'hidden' ? 'internal' : 'public',
        }
        if (f.placeholder_en) row.placeholder_en = f.placeholder_en
        if (f.placeholder_ar) row.placeholder_ar = f.placeholder_ar
        if (f.content) row.content = f.content
        if (CHOICE_TYPES.has(f.type) && f.options) {
          row.options = f.options.map((o) => ({
            value: o.id, label_en: o.label_en, label_ar: o.label_ar,
          }))
        }
        return row
      }),
      privacy_notice_version: privacyNoticeVersion,
      terms_version: termsVersion,
      theme,
    }

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/registration-form`, {
        method: 'PUT', tenantId, idempotency: true, body: payload,
      })
      await apiFetch(`/api/v1/tenant/events/${event.id}/branding`, {
        method: 'PUT', tenantId, idempotency: true,
        body: { theme_config: theme },
      })
      toast(t('registrationBuilderSavedPublished'), 'success')
      router.reload()
    } catch (caught) {
      const msg = caught instanceof ApiFetchError ? caught.message : 'Failed to save'
      toast(msg, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  /* ---- Embed ---- */
  const embedUrl = `${window.location.origin}/${locale}/events/${event.slug || event.id}/register`
  const embedCode = `<iframe src="${embedUrl}" width="100%" height="800" frameborder="0" style="border:none;max-width:680px;margin:0 auto;display:block;"></iframe>`

  /* ---------------------------------------------------------------- */
  /*  Render                                                           */
  /* ---------------------------------------------------------------- */

  return (
    <DashboardLayout title={t('registrationBuilderTitle')}>
      <div className="-m-4 sm:-m-6 lg:-m-8 flex h-[calc(100vh-57px)] flex-col overflow-hidden">
        {/* Top Toolbar */}
        <div className="flex flex-wrap items-center gap-2 border-b border-[var(--border,#e2e8f0)] bg-[var(--surface-elevated,#ffffff)] px-3 py-2 sm:px-4">
          <input
            value={formName}
            onChange={(e) => setFormName(e.target.value)}
            className="w-44 sm:w-52 rounded-lg border border-[var(--border,#e2e8f0)] bg-[var(--surface,#f8fafc)] px-2.5 py-1.5 text-sm font-medium text-[var(--ink,#1e293b)] focus:border-[var(--brand,#3b82f6)] focus:outline-none focus:ring-1 focus:ring-[var(--brand,#3b82f6)]/20"
            placeholder={t('registrationBuilderFormName')}
          />
          <div className="flex-1" />

          <button
            type="button"
            onClick={() => setShowThemePanel(true)}
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border,#e2e8f0)] px-3 py-1.5 text-sm text-[var(--muted,#64748b)] transition hover:border-[var(--brand,#3b82f6)]/30 hover:bg-[var(--brand-soft,#eff6ff)] hover:text-[var(--brand,#3b82f6)]"
          >
            <Palette size={14} />
            {t('registrationBuilderTheme')}
          </button>

          <button
            type="button"
            onClick={() => setShowEmbedModal(true)}
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border,#e2e8f0)] px-3 py-1.5 text-sm text-[var(--muted,#64748b)] transition hover:border-[var(--brand,#3b82f6)]/30 hover:bg-[var(--brand-soft,#eff6ff)] hover:text-[var(--brand,#3b82f6)]"
          >
            <Code size={14} />
            {t('registrationBuilderEmbed')}
          </button>

          <a
            href={`/${locale}/events/${event.slug || event.id}/register`}
            target="_blank"
            rel="noopener noreferrer"
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border,#e2e8f0)] px-3 py-1.5 text-sm text-[var(--muted,#64748b)] transition hover:border-[var(--brand,#3b82f6)]/30 hover:bg-[var(--brand-soft,#eff6ff)] hover:text-[var(--brand,#3b82f6)]"
          >
            <Eye size={14} />
            {t('registrationBuilderPreview')}
          </a>

          <button
            type="button"
            onClick={handleSave}
            disabled={submitting}
            className="flex items-center gap-1.5 rounded-lg bg-[var(--brand,#3b82f6)] px-4 py-1.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50"
          >
            <Save size={14} />
            {submitting ? t('registrationBuilderSaving') : t('registrationBuilderSavePublish')}
          </button>
        </div>

        {hasUnpublishedChanges && (
          <div className="border-b border-amber-500/20 bg-amber-500/10 px-4 py-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
            {t('registrationBuilderUnsavedChanges')}
          </div>
        )}

        {/* Main 3-panel layout */}
        <div className="flex flex-1 overflow-hidden min-h-0">
          {/* LEFT: Field Palette */}
          <aside className="hidden md:block w-56 lg:w-60 flex-shrink-0 overflow-y-auto border-e border-[var(--border)] bg-[var(--surface)] p-3">
            <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
              {t('registrationBuilderAddField')}
            </h3>
            {PALETTE_CATEGORIES.map((cat) => (
              <div key={cat.label} className="mb-4">
                <p className="mb-1.5 text-[11px] font-semibold uppercase text-[var(--muted)]">{cat.label}</p>
                <div className="space-y-0.5">
                  {cat.types.map((type) => {
                    const Icon = FIELD_TYPE_ICONS[type] ?? Type
                    return (
                      <button
                        key={type}
                        type="button"
                        onClick={() => addField(type)}
                        className="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-sm text-[var(--ink)] transition hover:bg-[var(--surface-elevated)] hover:shadow-sm"
                      >
                        <Icon size={14} className="text-[var(--muted)] shrink-0" />
                        <span className="truncate">{locale === 'ar' ? TYPE_LABELS[type]?.ar : TYPE_LABELS[type]?.en}</span>
                        <Plus size={12} className="ms-auto text-[var(--muted)] opacity-50" />
                      </button>
                    )
                  })}
                </div>
              </div>
            ))}
          </aside>

          {/* CENTER: Canvas */}
          <div className="flex-1 overflow-y-auto bg-[var(--surface)] p-4 lg:p-6">
            <div
              className="mx-auto max-w-2xl rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-5 lg:p-6 shadow-sm"
              style={canvasStyle}
            >
              {/* Form header preview */}
              <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-[var(--ink)]" style={{ color: theme.primary_color }}>
                  {locale === 'ar' ? event.name.ar : event.name.en}
                </h2>
                <p className="mt-1 text-sm text-[var(--muted)]">{formName}</p>
              </div>

              <DndContext
                id={dndId}
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragStart={handleDragStart}
                onDragEnd={handleDragEnd}
              >
                <SortableContext
                  items={fields.map((f) => f.id)}
                  strategy={verticalListSortingStrategy}
                >
                  <div className="flex flex-wrap -m-1">
                    {fields.map((field) => (
                      <SortableFieldCard
                        key={field.id}
                        field={field}
                        selected={field.id === selectedId}
                        onSelect={() => setSelectedId(field.id)}
                        locale={locale}
                      />
                    ))}
                  </div>
                </SortableContext>

                <DragOverlay>
                  {activeDragId ? (
                    <div className="rounded-lg border border-[var(--brand)]/30 bg-[var(--brand-soft)] p-3 shadow-lg opacity-90">
                      <span className="text-sm font-medium text-[var(--brand)]">
                        {fields.find((f) => f.id === activeDragId)?.label_en ?? ''}
                      </span>
                    </div>
                  ) : null}
                </DragOverlay>
              </DndContext>

              {fields.filter((f) => !f.system).length === 0 && (
                <div className="mt-4 rounded-xl border-2 border-dashed border-[var(--border)] p-8 text-center">
                  <Plus size={24} className="mx-auto mb-2 text-[var(--muted)] opacity-40" />
                  <p className="text-sm text-[var(--muted)]">
                    {t('registrationBuilderClickToAdd')}
                  </p>
                </div>
              )}
            </div>
          </div>

          {/* RIGHT: Property Inspector */}
          <aside className={`${selected ? 'w-72' : 'w-0 lg:w-72'} flex-shrink-0 overflow-y-auto overflow-x-hidden border-s border-[var(--border)] bg-[var(--surface-elevated)] transition-all duration-200`}>
            <div className="p-4 min-w-[288px]">
            {selected ? (
              <div className="space-y-4">
                <div className="flex items-center justify-between">
                  <h3 className="text-sm font-semibold text-[var(--ink)]">
                    {t('registrationBuilderFieldProperties')}
                  </h3>
                  <button type="button" onClick={() => setSelectedId(null)} className="rounded-md p-1 text-[var(--muted)] transition hover:bg-[var(--surface)] hover:text-[var(--ink)]">
                    <X size={16} />
                  </button>
                </div>

                {selected.system ? (
                  <p className="rounded-lg bg-[var(--surface)] p-3 text-xs text-[var(--muted)]">
                    {t('registrationBuilderSystemField')}
                  </p>
                ) : (
                  <>
                    {/* Label EN */}
                    <div>
                      <label className="mb-1 block text-xs font-medium text-[var(--muted)]">Label (EN)</label>
                      <input
                        value={selected.label_en}
                        onChange={(e) => updateField(selected.id, { label_en: e.target.value })}
                        className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
                      />
                    </div>

                    {/* Label AR */}
                    <div>
                      <label className="mb-1 block text-xs font-medium text-[var(--muted)]">Label (AR)</label>
                      <input
                        value={selected.label_ar}
                        onChange={(e) => updateField(selected.id, { label_ar: e.target.value })}
                        className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
                        dir="rtl"
                      />
                    </div>

                    {/* Placeholder */}
                    {!LAYOUT_TYPES.has(selected.type) && selected.type !== 'consent' && (
                      <>
                        <div>
                          <label className="mb-1 block text-xs font-medium text-[var(--muted)]">Placeholder (EN)</label>
                          <input
                            value={selected.placeholder_en ?? ''}
                            onChange={(e) => updateField(selected.id, { placeholder_en: e.target.value })}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
                          />
                        </div>
                        <div>
                          <label className="mb-1 block text-xs font-medium text-[var(--muted)]">Placeholder (AR)</label>
                          <input
                            value={selected.placeholder_ar ?? ''}
                            onChange={(e) => updateField(selected.id, { placeholder_ar: e.target.value })}
                            className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
                            dir="rtl"
                          />
                        </div>
                      </>
                    )}

                    {/* Content (heading/paragraph) */}
                    {(selected.type === 'heading' || selected.type === 'paragraph') && (
                      <div>
                        <label className="mb-1 block text-xs font-medium text-[var(--muted)]">Content</label>
                        <textarea
                          value={selected.content ?? ''}
                          onChange={(e) => updateField(selected.id, { content: e.target.value })}
                          rows={3}
                          className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20 resize-none"
                        />
                      </div>
                    )}

                    {/* Required toggle */}
                    {!LAYOUT_TYPES.has(selected.type) && (
                      <label className="flex items-center gap-2.5 cursor-pointer">
                        <input
                          type="checkbox"
                          checked={selected.required}
                          onChange={(e) => updateField(selected.id, { required: e.target.checked })}
                          className="h-4 w-4 rounded border-[var(--border)] bg-[var(--surface)] text-[var(--brand)] focus:ring-[var(--brand)]/20"
                        />
                        <span className="text-sm text-[var(--ink)]">
                          {t('registrationBuilderRequired')}
                        </span>
                      </label>
                    )}

                    {/* Width selector */}
                    <div>
                      <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                        {t('registrationBuilderWidth')}
                      </label>
                      <div className="flex gap-1">
                        {(['full', 'half', 'third'] as const).map((w) => (
                          <button
                            key={w}
                            type="button"
                            onClick={() => updateField(selected.id, { width: w })}
                            className={`flex-1 rounded-lg px-2 py-1.5 text-xs font-medium transition ${
                              selected.width === w
                                ? 'bg-[var(--brand)] text-white shadow-sm'
                                : 'bg-[var(--surface)] text-[var(--muted)] hover:text-[var(--ink)] border border-[var(--border)]'
                            }`}
                          >
                            {w === 'full' ? '1/1' : w === 'half' ? '1/2' : '1/3'}
                          </button>
                        ))}
                      </div>
                    </div>

                    {/* Options (for choice fields) */}
                    {CHOICE_TYPES.has(selected.type) && (
                      <div>
                        <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                          {t('registrationBuilderOptions')}
                        </label>
                        <div className="space-y-2">
                          {(selected.options ?? []).map((opt, i) => (
                            <div key={opt.id} className="flex gap-1.5 items-center">
                              <input
                                value={opt.label_en}
                                onChange={(e) => {
                                  const opts = [...(selected.options ?? [])]
                                  opts[i] = { ...opts[i], label_en: e.target.value }
                                  updateField(selected.id, { options: opts })
                                }}
                                placeholder="EN"
                                className="flex-1 rounded-md border border-[var(--border)] bg-[var(--surface)] px-2 py-1 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                              />
                              <input
                                value={opt.label_ar}
                                onChange={(e) => {
                                  const opts = [...(selected.options ?? [])]
                                  opts[i] = { ...opts[i], label_ar: e.target.value }
                                  updateField(selected.id, { options: opts })
                                }}
                                placeholder="AR"
                                dir="rtl"
                                className="flex-1 rounded-md border border-[var(--border)] bg-[var(--surface)] px-2 py-1 text-xs text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                              />
                              <button
                                type="button"
                                onClick={() => {
                                  const opts = (selected.options ?? []).filter((_, idx) => idx !== i)
                                  updateField(selected.id, { options: opts })
                                }}
                                className="rounded p-0.5 text-[var(--danger,#ef4444)] opacity-60 hover:opacity-100"
                              >
                                <X size={12} />
                              </button>
                            </div>
                          ))}
                          <button
                            type="button"
                            onClick={() => {
                              const opts = [...(selected.options ?? []), { id: `opt_${Date.now()}`, label_en: '', label_ar: '' }]
                              updateField(selected.id, { options: opts })
                            }}
                            className="flex items-center gap-1 text-xs font-medium text-[var(--brand)] hover:underline"
                          >
                            <Plus size={12} /> {t('registrationBuilderAddOption')}
                          </button>
                        </div>
                      </div>
                    )}

                    {/* Delete */}
                    <button
                      type="button"
                      onClick={() => deleteField(selected.id)}
                      className="mt-6 flex w-full items-center justify-center gap-1.5 rounded-lg border border-[var(--danger,#ef4444)]/20 bg-[var(--danger-soft,#fef2f2)] px-3 py-2 text-xs font-medium text-[var(--danger,#ef4444)] transition hover:bg-[var(--danger,#ef4444)]/10"
                    >
                      <Trash2 size={13} />
                      {t('registrationBuilderDeleteField')}
                    </button>
                  </>
                )}
              </div>
            ) : (
              <div className="flex h-64 items-center justify-center text-center px-4">
                <div>
                  <ChevronRight size={24} className="mx-auto mb-2 text-[var(--muted)] opacity-30" />
                  <p className="text-sm text-[var(--muted)]">
                    {t('registrationBuilderSelectField')}
                  </p>
                </div>
              </div>
            )}
            </div>
          </aside>
        </div>
      </div>

      {/* Brand Theme Slide-over */}
      {showThemePanel && (
        <div className="fixed inset-0 z-50 flex">
          <div className="flex-1 bg-black/20 backdrop-blur-sm" onClick={() => setShowThemePanel(false)} />
          <div className="w-80 bg-[var(--surface-elevated)] shadow-2xl p-5 overflow-y-auto border-s border-[var(--border)]">
            <div className="flex items-center justify-between mb-5">
              <h3 className="text-base font-semibold text-[var(--ink)]">
                {t('registrationBuilderBrandTheme')}
              </h3>
              <button type="button" onClick={() => setShowThemePanel(false)} className="rounded-md p-1 text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--ink)]">
                <X size={18} />
              </button>
            </div>

            <div className="space-y-5">
              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderPrimaryColor')}
                </label>
                <div className="flex gap-2 items-center">
                  <input
                    type="color"
                    value={theme.primary_color}
                    onChange={(e) => setTheme((t) => ({ ...t, primary_color: e.target.value }))}
                    className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] p-0.5"
                  />
                  <input
                    value={theme.primary_color}
                    onChange={(e) => setTheme((t) => ({ ...t, primary_color: e.target.value }))}
                    className="flex-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderAccentColor')}
                </label>
                <div className="flex gap-2 items-center">
                  <input
                    type="color"
                    value={theme.accent_color}
                    onChange={(e) => setTheme((t) => ({ ...t, accent_color: e.target.value }))}
                    className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] p-0.5"
                  />
                  <input
                    value={theme.accent_color}
                    onChange={(e) => setTheme((t) => ({ ...t, accent_color: e.target.value }))}
                    className="flex-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderBackgroundColor')}
                </label>
                <div className="flex gap-2 items-center">
                  <input
                    type="color"
                    value={theme.background_color}
                    onChange={(e) => setTheme((t) => ({ ...t, background_color: e.target.value }))}
                    className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] p-0.5"
                  />
                  <input
                    value={theme.background_color}
                    onChange={(e) => setTheme((t) => ({ ...t, background_color: e.target.value }))}
                    className="flex-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderFontFamily')}
                </label>
                <select
                  value={theme.font_family}
                  onChange={(e) => setTheme((t) => ({ ...t, font_family: e.target.value }))}
                  className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-2 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                >
                  {FONT_OPTIONS.map((f) => (
                    <option key={f} value={f}>{f}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="mt-6 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3">
              <p className="text-xs text-[var(--muted)]">
                {t('registrationBuilderThemeNote')}
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Embed Code Modal */}
      {showEmbedModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
          <div className="absolute inset-0 bg-black/20 backdrop-blur-sm" onClick={() => setShowEmbedModal(false)} />
          <div className="relative w-full max-w-lg rounded-2xl bg-[var(--surface-elevated)] border border-[var(--border)] p-6 shadow-2xl">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-lg font-semibold text-[var(--ink)]">
                {t('registrationBuilderEmbedCode')}
              </h3>
              <button type="button" onClick={() => setShowEmbedModal(false)} className="rounded-md p-1 text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--ink)]">
                <X size={18} />
              </button>
            </div>

            <p className="mb-3 text-sm text-[var(--muted)]">
              {t('registrationBuilderEmbedInstructions')}
            </p>

            <div className="relative rounded-xl border border-[var(--border)] bg-[var(--surface)] p-4">
              <pre className="overflow-x-auto text-xs text-[var(--ink)] whitespace-pre-wrap break-all font-mono">
                {embedCode}
              </pre>
              <button
                type="button"
                onClick={() => {
                  navigator.clipboard.writeText(embedCode)
                  toast(t('registrationBuilderCopied'), 'success')
                }}
                className="absolute top-2 end-2 flex items-center gap-1 rounded-lg bg-[var(--surface-elevated)] px-2.5 py-1 text-xs font-medium text-[var(--ink)] shadow-sm border border-[var(--border)] hover:bg-[var(--brand-soft)] hover:text-[var(--brand)] transition"
              >
                <Copy size={12} />
                {t('registrationBuilderCopy')}
              </button>
            </div>

            <div className="mt-4 rounded-xl border border-[var(--brand)]/20 bg-[var(--brand-soft)] p-3">
              <p className="text-xs text-[var(--brand)]">
                <strong>URL:</strong> {embedUrl}
              </p>
            </div>
          </div>
        </div>
      )}
    </DashboardLayout>
  )
}
