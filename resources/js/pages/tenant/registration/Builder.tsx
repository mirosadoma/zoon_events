import { useState, useMemo, useCallback, useId, type CSSProperties } from 'react'
import { router } from '@inertiajs/react'
import {
  DndContext,
  closestCenter,
  useSensor,
  useSensors,
  PointerSensor,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  rectSortingStrategy,
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
import { PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import {
  isRegistrationSystemFieldKey,
  mergeRegistrationFieldsWithSystemOrder,
} from '@/lib/registrationSystemFields'
import {
  normalizeRegistrationTheme,
  toPersistedRegistrationTheme,
  resolveRegistrationThemeMode,
  resolveRegistrationFontFamily,
  registrationThemeCssVars,
  registrationCardBackgroundStyle,
  registrationFontFamily,
  REGISTRATION_FONT_OPTIONS_EN,
  REGISTRATION_FONT_OPTIONS_AR,
  type RegistrationThemeConfig,
  type RegistrationThemeModeColors,
} from '@/lib/registrationThemeBackground'

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

type FieldOptionRow = { id: string; label_en: string; label_ar: string; value?: string }

function normalizeFieldOptions(options: Array<Partial<FieldOptionRow> & { value?: string }> | undefined): FieldOptionRow[] | undefined {
  if (!options) {
    return undefined
  }

  return options.map((option, index) => {
    const id = String(option.id ?? option.value ?? `opt_${index + 1}`)

    return {
      id,
      value: String(option.value ?? id),
      label_en: option.label_en ?? '',
      label_ar: option.label_ar ?? '',
    }
  })
}

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
  choice_style?: string | null
  choice_color?: string | null
}

type EventRow = { id: string; name: { en: string; ar: string }; slug?: string }

type EventPreview = {
  name?: { en: string; ar: string }
  description?: { en: string; ar: string }
  timezone?: string | null
  start_at?: string | null
  end_at?: string | null
  main_image?: string | null
  images?: string[]
  venues?: Array<{
    id: string
    name: { en: string; ar: string }
    city?: { en: string; ar: string }
    start_at?: string | null
    end_at?: string | null
  }>
  categories?: Array<{ id: string; name: { en: string; ar: string } }>
}

type Props = {
  event: EventRow
  eventPreview?: EventPreview | null
  tenantId: string
  formName: string
  privacyNoticeVersion: string
  termsVersion: string
  fields: Array<{
    key: string; type: string; label_en: string; label_ar: string
    required: boolean; system?: boolean; options?: FieldOptionRow[]
    placeholder_en?: string; placeholder_ar?: string; width?: string
    content?: string
    choice_style?: string | null
    choice_color?: string | null
  }>
  hasUnpublishedChanges?: boolean
  theme?: Partial<RegistrationThemeConfig> | null
}

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const FIELD_TYPE_ICONS: Record<string, typeof Type> = {
  text: Type, email: Mail, phone: Phone, number: Hash, date: Calendar,
  select: List, multi_select: CheckSquare, radio: CircleDot,
  checkbox: ToggleLeft, consent: ToggleLeft, hidden: EyeOff,
  heading: Heading1, divider: Minus, paragraph: AlignLeft,
  event_logo: Type, event_name: Type, event_venue: Type, event_dates: Calendar, event_description: AlignLeft,
  event_categories: List, event_venue_select: Calendar,
}

const PALETTE_CATEGORIES = [
  {
    label: 'Event Info',
    types: [
      'event_logo',
      'event_name',
      'event_venue',
      'event_dates',
      'event_description',
      'event_categories',
      'event_venue_select',
    ],
  },
  {
    label: 'Input Fields',
    types: ['text', 'number', 'date', 'select', 'multi_select', 'radio', 'checkbox'],
  },
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
  event_logo: { en: 'Event Logo', ar: 'شعار الفعالية' },
  event_name: { en: 'Event Name', ar: 'اسم الفعالية' },
  event_venue: { en: 'Event Venue', ar: 'مكان الفعالية' },
  event_dates: { en: 'Event Dates', ar: 'تواريخ الفعالية' },
  event_description: { en: 'Event Description', ar: 'وصف الفعالية' },
  event_categories: { en: 'Event Categories', ar: 'أقسام الفعالية' },
  event_venue_select: { en: 'Venue / Date Select', ar: 'اختيار المكان / التاريخ' },
}

const CHOICE_TYPES = new Set(['select', 'multi_select', 'radio', 'checkbox'])
const LAYOUT_TYPES = new Set(['heading', 'divider', 'paragraph'])
const EVENT_DISPLAY_TYPES = new Set([
  'event_logo',
  'event_name',
  'event_venue',
  'event_dates',
  'event_description',
  'event_categories',
  'event_venue_select',
])
const STYLED_CHOICE_TYPES = new Set(['checkbox', 'radio'])

const CHECKBOX_STYLES = [
  { value: 'square', labelKey: 'registrationBuilderChoiceStyleSquare' as const },
  { value: 'toggle', labelKey: 'registrationBuilderChoiceStyleToggle' as const },
  { value: 'pill', labelKey: 'registrationBuilderChoiceStylePill' as const },
  { value: 'card', labelKey: 'registrationBuilderChoiceStyleCard' as const },
]

const RADIO_STYLES = [
  { value: 'circle', labelKey: 'registrationBuilderChoiceStyleCircle' as const },
  { value: 'toggle', labelKey: 'registrationBuilderChoiceStyleToggle' as const },
  { value: 'pill', labelKey: 'registrationBuilderChoiceStylePill' as const },
  { value: 'card', labelKey: 'registrationBuilderChoiceStyleCard' as const },
  { value: 'button', labelKey: 'registrationBuilderChoiceStyleButton' as const },
]

const CHOICE_COLOR_PRESETS = ['#2563EB', '#0F766E', '#C2410C', '#7C3AED', '#BE123C', '#111827']

function defaultChoiceStyle(type: string): string {
  return type === 'radio' ? 'circle' : 'square'
}

function ChoiceStylePreview({
  style,
  color,
  fieldType,
}: {
  style: string
  color: string
  fieldType: string
}) {
  const accent = color || '#2563EB'
  const isRadio = fieldType === 'radio'

  if (style === 'toggle') {
    return (
      <span className="flex w-full items-center justify-center gap-2 py-0.5" aria-hidden="true">
        <span
          className="relative inline-flex h-4 w-8 shrink-0 items-center rounded-full"
          style={{ backgroundColor: accent }}
        >
          <span className="absolute end-0.5 h-3 w-3 rounded-full bg-white shadow-sm" />
        </span>
        <span className="h-1.5 w-8 rounded-full bg-[var(--border)]" />
      </span>
    )
  }

  if (style === 'pill') {
    return (
      <span className="flex w-full flex-wrap items-center justify-center gap-1 py-0.5" aria-hidden="true">
        <span
          className="rounded-full px-2 py-0.5 text-[9px] font-semibold text-white"
          style={{ backgroundColor: accent }}
        >
          A
        </span>
        <span className="rounded-full border border-[var(--border)] bg-[var(--surface)] px-2 py-0.5 text-[9px] text-[var(--muted)]">
          B
        </span>
      </span>
    )
  }

  if (style === 'card') {
    return (
      <span className="flex w-full items-center justify-center gap-1 py-0.5" aria-hidden="true">
        <span
          className="flex flex-1 items-center gap-1 rounded-md border px-1.5 py-1"
          style={{ borderColor: accent, backgroundColor: `${accent}18` }}
        >
          <span
            className={`inline-block h-2.5 w-2.5 shrink-0 ${isRadio ? 'rounded-full' : 'rounded-[2px]'}`}
            style={{ backgroundColor: accent }}
          />
          <span className="h-1 w-full rounded-full" style={{ backgroundColor: accent, opacity: 0.45 }} />
        </span>
        <span className="flex flex-1 items-center gap-1 rounded-md border border-[var(--border)] bg-[var(--surface)] px-1.5 py-1">
          <span className={`inline-block h-2.5 w-2.5 shrink-0 border border-[var(--border)] ${isRadio ? 'rounded-full' : 'rounded-[2px]'}`} />
          <span className="h-1 w-full rounded-full bg-[var(--border)]" />
        </span>
      </span>
    )
  }

  if (style === 'button') {
    return (
      <span className="flex w-full items-center justify-center gap-1 py-0.5" aria-hidden="true">
        <span
          className="flex-1 rounded-md py-1 text-center text-[9px] font-semibold text-white"
          style={{ backgroundColor: accent }}
        >
          A
        </span>
        <span className="flex-1 rounded-md border border-[var(--border)] bg-[var(--surface)] py-1 text-center text-[9px] text-[var(--muted)]">
          B
        </span>
      </span>
    )
  }

  // square / circle
  const isCircle = style === 'circle'
  return (
    <span className="flex w-full items-center justify-center gap-3 py-0.5" aria-hidden="true">
      <span className="flex items-center gap-1">
        <span
          className={`inline-grid h-3.5 w-3.5 place-items-center ${isCircle ? 'rounded-full' : 'rounded-[3px]'}`}
          style={{ backgroundColor: accent }}
        >
          {isCircle ? (
            <span className="h-1.5 w-1.5 rounded-full bg-white" />
          ) : (
            <span className="h-1.5 w-1 border-b-2 border-e-2 border-white rotate-45 -translate-y-px" />
          )}
        </span>
        <span className="h-1 w-6 rounded-full bg-[var(--border)]" />
      </span>
      <span className="flex items-center gap-1 opacity-60">
        <span className={`inline-block h-3.5 w-3.5 border border-[var(--border)] ${isCircle ? 'rounded-full' : 'rounded-[3px]'} bg-[var(--surface)]`} />
        <span className="h-1 w-6 rounded-full bg-[var(--border)]" />
      </span>
    </span>
  )
}

let fieldIdCounter = 0
const nextFieldId = () => `f_${Date.now()}_${++fieldIdCounter}`

function slugify(label: string): string {
  const base = label.toLowerCase().replace(/[^a-z0-9]+/g, '_').replace(/^_+|_+$/g, '').slice(0, 40)
  return base.length >= 2 ? base : `field_${Date.now()}`
}

function uniqueFieldKey(labelOrType: string, existingKeys: Set<string>): string {
  const base = slugify(labelOrType)
  if (!existingKeys.has(base)) {
    return base
  }

  let suffix = 2
  while (existingKeys.has(`${base}_${suffix}`)) {
    suffix += 1
  }

  return `${base}_${suffix}`
}

function ensureUniqueCustomFieldKeys(fields: FormField[]): FormField[] {
  const seen = new Set<string>()

  return fields.map((field) => {
    if (field.system || isRegistrationSystemFieldKey(field.key) || EVENT_DISPLAY_TYPES.has(field.type)) {
      seen.add(field.key)
      return field
    }

    let key = field.key
    if (!/^[a-z][a-z0-9_]{1,63}$/.test(key) || seen.has(key)) {
      key = uniqueFieldKey(field.label_en || field.type, seen)
    }
    seen.add(key)

    return key === field.key ? field : { ...field, key }
  })
}

const DEFAULT_EVENT_IMAGE = 'data:image/svg+xml,' + encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="360" viewBox="0 0 640 360">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#1e293b"/>
      <stop offset="100%" stop-color="#334155"/>
    </linearGradient>
  </defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <rect x="40" y="40" width="560" height="280" rx="24" fill="#0f172a" opacity="0.35"/>
  <circle cx="320" cy="150" r="42" fill="#94a3b8" opacity="0.55"/>
  <path d="M220 250c28-48 72-72 100-72s72 24 100 72" fill="#94a3b8" opacity="0.45"/>
  <text x="320" y="300" text-anchor="middle" fill="#e2e8f0" font-family="system-ui,sans-serif" font-size="22" font-weight="600">Event image</text>
</svg>
`.trim())

function localizedPreview(
  value: { en?: string; ar?: string } | undefined,
  locale: 'en' | 'ar',
  fallback: string,
): string {
  if (!value) return fallback
  const text = locale === 'ar' ? (value.ar || value.en) : (value.en || value.ar)
  return text && text.trim() !== '' ? text : fallback
}

function formatPreviewDate(value?: string | null, locale: 'en' | 'ar' = 'en'): string | null {
  if (!value) return null
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return null
  return date.toLocaleDateString(locale === 'ar' ? 'ar-SA' : 'en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

/* ------------------------------------------------------------------ */
/*  Sortable Field Card                                                */
/* ------------------------------------------------------------------ */

function SortableFieldCard({
  field, selected, onSelect, locale, eventPreview,
}: {
  field: FormField
  selected: boolean
  onSelect: () => void
  locale: 'en' | 'ar'
  eventPreview?: EventPreview | null
}) {
  const {
    attributes,
    listeners,
    setNodeRef,
    setActivatorNodeRef,
    transform,
    transition,
    isDragging,
  } = useSortable({ id: field.id })

  const style: CSSProperties = {
    transform: CSS.Translate.toString(transform),
    transition: isDragging ? undefined : transition,
    zIndex: isDragging ? 40 : undefined,
    position: isDragging ? 'relative' : undefined,
    opacity: isDragging ? 0.92 : 1,
  }

  const Icon = FIELD_TYPE_ICONS[field.type] ?? Type
  const widthClass = field.width === 'half' ? 'w-1/2' : field.width === 'third' ? 'w-1/3' : 'w-full'
  const gallery = eventPreview?.images ?? []
  const mainImage = eventPreview?.main_image || gallery[0] || DEFAULT_EVENT_IMAGE
  const venues = eventPreview?.venues ?? []
  const categories = eventPreview?.categories ?? []
  const startLabel = formatPreviewDate(eventPreview?.start_at, locale)
    ?? formatPreviewDate(venues[0]?.start_at, locale)
  const endLabel = formatPreviewDate(eventPreview?.end_at, locale)
    ?? formatPreviewDate(venues[0]?.end_at, locale)

  return (
    <div ref={setNodeRef} style={style} className={`${widthClass} p-1`}>
      <div
        onClick={onSelect}
        className={`group relative rounded-xl border p-3 transition-colors cursor-pointer ${
          isDragging
            ? 'border-[var(--brand)] bg-[var(--surface-elevated)] shadow-xl ring-2 ring-[var(--brand)]/20'
            : selected
              ? 'border-[var(--brand)] ring-2 ring-[var(--brand)]/20 bg-[color-mix(in_srgb,var(--brand)_12%,var(--surface-elevated))]'
              : 'border-[var(--border)] hover:border-[color-mix(in_srgb,var(--brand)_40%,var(--border))] bg-[var(--surface-elevated)]'
        }`}
      >
        <div className="flex items-center gap-2">
          <button
            type="button"
            ref={setActivatorNodeRef}
            className="cursor-grab touch-none text-[var(--muted)] opacity-50 hover:opacity-100 active:cursor-grabbing"
            {...attributes}
            {...listeners}
          >
            <GripVertical size={14} />
          </button>
          <Icon size={14} className="text-[var(--muted)] shrink-0" />
          <span className="text-sm font-medium text-[var(--ink)] truncate flex-1">
            {locale === 'ar' ? field.label_ar : field.label_en}
          </span>
          {field.system && (
            <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-[var(--surface)] text-[var(--muted)] font-medium uppercase border border-[var(--border)]">
              System
            </span>
          )}
          {field.required && (
            <span className="text-[var(--danger)] text-xs font-bold">*</span>
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
        ) : field.type === 'event_logo' ? (
          <div className="mt-2 overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface)]">
            <img
              src={mainImage}
              alt=""
              className="h-28 w-full object-cover"
            />
          </div>
        ) : field.type === 'event_name' ? (
          <div className="mt-2 text-sm font-semibold text-[var(--ink)]">
            {localizedPreview(eventPreview?.name, locale, locale === 'ar' ? 'اسم الفعالية التجريبي' : 'Sample Event Name')}
          </div>
        ) : field.type === 'event_venue' ? (
          <div className="mt-2 text-xs text-[var(--muted)]">
            {venues.length > 0
              ? venues.map((venue) => localizedPreview(venue.name, locale, 'Venue')).join(', ')
              : (locale === 'ar' ? 'قاعة رئيسية — الرياض' : 'Main Hall — Riyadh')}
          </div>
        ) : field.type === 'event_dates' ? (
          <div className="mt-2 text-xs text-[var(--muted)]">
            {startLabel || endLabel
              ? [startLabel, endLabel].filter(Boolean).join(' — ')
              : (locale === 'ar' ? '١٥ أغسطس ٢٠٢٦ — ١٧ أغسطس ٢٠٢٦' : 'Aug 15, 2026 — Aug 17, 2026')}
          </div>
        ) : field.type === 'event_description' ? (
          <div className="mt-2 text-xs text-[var(--muted)] line-clamp-3">
            {localizedPreview(
              eventPreview?.description,
              locale,
              locale === 'ar'
                ? 'وصف تجريبي للفعالية يظهر هنا حتى يتمكن المنظم من معاينة التخطيط.'
                : 'Sample event description appears here so organizers can preview the layout.',
            )}
          </div>
        ) : field.type === 'event_categories' ? (
          <div className="mt-2 flex flex-wrap gap-1.5">
            {(categories.length > 0
              ? categories
              : [
                  { id: 'sample-1', name: { en: 'VIP', ar: 'كبار الشخصيات' } },
                  { id: 'sample-2', name: { en: 'General', ar: 'عام' } },
                ]
            ).map((category) => (
              <span
                key={category.id}
                className="rounded-full border border-[var(--border)] bg-[var(--surface)] px-2 py-0.5 text-[10px] text-[var(--ink)]"
              >
                {localizedPreview(category.name, locale, 'Category')}
              </span>
            ))}
          </div>
        ) : field.type === 'event_venue_select' ? (
          <div className="mt-2">
            <div className="h-9 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 flex items-center">
              <span className="text-xs text-[var(--muted)] truncate">
                {venues.length > 0
                  ? localizedPreview(venues[0].name, locale, 'Venue')
                  : (locale === 'ar' ? 'اختر المكان / التاريخ' : 'Select venue / date')}
              </span>
            </div>
          </div>
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
  eventPreview = null,
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

  const [theme, setTheme] = useState(() => normalizeRegistrationTheme(initialTheme))
  const [themePanelMode, setThemePanelMode] = useState<'light' | 'dark'>('light')
  const [showThemePanel, setShowThemePanel] = useState(false)
  const [showEmbedModal, setShowEmbedModal] = useState(false)
  const [uploadingBackground, setUploadingBackground] = useState(false)

  const patchMode = useCallback((mode: 'light' | 'dark', patch: Partial<RegistrationThemeModeColors>) => {
    setTheme((current) => {
      const normalized = normalizeRegistrationTheme(current)
      return normalizeRegistrationTheme({
        ...normalized,
        [mode]: { ...normalized[mode], ...patch },
      })
    })
  }, [])

  const activeModeColors = theme[themePanelMode]

  const [fields, setFields] = useState<FormField[]>(() => {
    const merged = mergeRegistrationFieldsWithSystemOrder(
      initialFields.map((f, i) => ({
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
        options: normalizeFieldOptions(f.options),
        content: f.content ?? '',
        choice_style: f.choice_style ?? (STYLED_CHOICE_TYPES.has(f.type) ? defaultChoiceStyle(f.type) : null),
        choice_color: f.choice_color ?? null,
      })),
    )

    return merged.map((field, index) => ({
      id: field.id ?? `field_${index}_${field.key}`,
      key: field.key,
      type: field.type,
      label_en: field.label_en,
      label_ar: field.label_ar,
      placeholder_en: field.placeholder_en ?? '',
      placeholder_ar: field.placeholder_ar ?? '',
      required: Boolean(field.required),
      width: (field.width as 'full' | 'half' | 'third') ?? 'full',
      system: Boolean(field.system) || isRegistrationSystemFieldKey(field.key),
      options: normalizeFieldOptions(field.options as FieldOptionRow[] | undefined),
      content: field.content ?? '',
      choice_style: field.choice_style ?? (STYLED_CHOICE_TYPES.has(field.type) ? defaultChoiceStyle(field.type) : null),
      choice_color: field.choice_color ?? null,
    }))
  })

  const selected = fields.find((f) => f.id === selectedId) ?? null

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
  )

  const previewIsDark = themePanelMode === 'dark'

  const pageBackgroundStyle = useMemo((): CSSProperties => {
    return registrationCardBackgroundStyle(theme, { isDark: previewIsDark })
      ?? { backgroundColor: 'var(--surface)' }
  }, [theme, previewIsDark])

  const canvasStyle = useMemo((): CSSProperties => {
    const modeColors = resolveRegistrationThemeMode(theme, previewIsDark)
    const fontStack = resolveRegistrationFontFamily(theme, locale) || 'Inter, system-ui, sans-serif'
    const cssVars = registrationThemeCssVars(theme, { isDark: previewIsDark, locale }) ?? {}

    return {
      ...cssVars,
      fontFamily: fontStack,
      '--reg-font': fontStack,
      '--reg-primary': modeColors.primary_color || 'var(--brand)',
      '--reg-accent': modeColors.accent_color || 'var(--brand)',
    } as CSSProperties
  }, [theme, previewIsDark, locale])

  /* ---- DnD handlers ---- */

  const handleDragEnd = useCallback((event: DragEndEvent) => {
    const { active, over } = event
    if (!over || active.id === over.id) return

    setFields((prev) => {
      const oldIndex = prev.findIndex((f) => f.id === active.id)
      const newIndex = prev.findIndex((f) => f.id === over.id)
      if (oldIndex === -1 || newIndex === -1) return prev
      return arrayMove(prev, oldIndex, newIndex)
    })
  }, [])

  /* ---- Field CRUD ---- */

  const usedEventInfoTypes = useMemo(
    () => new Set(fields.filter((f) => EVENT_DISPLAY_TYPES.has(f.type)).map((f) => f.type)),
    [fields],
  )

  const addField = (type: string) => {
    if (EVENT_DISPLAY_TYPES.has(type) && usedEventInfoTypes.has(type)) {
      return
    }

    const labelEn = TYPE_LABELS[type]?.en ?? type
    const labelAr = TYPE_LABELS[type]?.ar ?? type
    const existingKeys = new Set(fields.map((field) => field.key))
    const key = EVENT_DISPLAY_TYPES.has(type)
      ? type
      : uniqueFieldKey(labelEn, existingKeys)
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
      choice_style: STYLED_CHOICE_TYPES.has(type) ? defaultChoiceStyle(type) : null,
      choice_color: STYLED_CHOICE_TYPES.has(type) ? '#2563EB' : null,
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
    const uniqueFields = ensureUniqueCustomFieldKeys(fields)
    if (uniqueFields.some((field, index) => field.key !== fields[index]?.key)) {
      setFields(uniqueFields)
    }

    const payload = {
      name: formName,
      fields: uniqueFields.map((f) => {
        const row: Record<string, unknown> = {
          key: f.key,
          type: f.type,
          label_en: f.label_en,
          label_ar: f.label_ar,
          required: f.system ? true : f.required,
          width: f.width,
          visibility: f.type === 'hidden' ? 'internal' : 'public',
          system: Boolean(f.system),
        }
        if (f.placeholder_en) row.placeholder_en = f.placeholder_en
        if (f.placeholder_ar) row.placeholder_ar = f.placeholder_ar
        if (f.content) row.content = f.content
        if (STYLED_CHOICE_TYPES.has(f.type)) {
          row.choice_style = f.choice_style || defaultChoiceStyle(f.type)
          row.choice_color = f.choice_color || '#2563EB'
        }
        if (CHOICE_TYPES.has(f.type) && f.options) {
          row.options = f.options.map((o, index) => {
            const value = String(o.id || o.value || `opt_${index + 1}`)

            return {
              value,
              label_en: o.label_en,
              label_ar: o.label_ar,
            }
          })
        }
        return row
      }),
      privacy_notice_version: privacyNoticeVersion,
      terms_version: termsVersion,
      theme: {
        primary_color: theme.light.primary_color,
        accent_color: theme.light.accent_color,
        background_color: theme.light.background_color,
        background_mode: theme.light.background_mode,
        background_gradient: theme.light.background_mode === 'gradient' ? theme.light.background_gradient : null,
        background_image_path: theme.light.background_image_path,
        font_family: theme.font_family_en,
      },
    }

    const persisted = toPersistedRegistrationTheme(theme)
    const persistedLight = (persisted.light ?? {}) as Record<string, unknown>
    const persistedDark = (persisted.dark ?? {}) as Record<string, unknown>

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/registration-form`, {
        method: 'PUT', tenantId, idempotency: true, body: payload,
      })
      await apiFetch(`/api/v1/tenant/events/${event.id}/branding`, {
        method: 'PUT', tenantId, idempotency: true,
        body: {
          theme_config: {
            ...persisted,
            light: {
              ...persistedLight,
              clear_background_image:
                persistedLight.background_mode !== 'image' || !persistedLight.background_image_path,
            },
            dark: {
              ...persistedDark,
              clear_background_image:
                persistedDark.background_mode !== 'image' || !persistedDark.background_image_path,
            },
          },
        },
      })
      toast(t('registrationBuilderSavedPublished'), 'success')
      router.reload()
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        const firstError = Object.values(caught.errors)[0]
        toast(firstError || caught.message, 'error')
      } else {
        toast('Failed to save', 'error')
      }
    } finally {
      setSubmitting(false)
    }
  }

  const handleBackgroundUpload = async (file: File | null) => {
    if (!file) return
    setUploadingBackground(true)
    try {
      const body = new FormData()
      body.append('background_image', file)
      body.append('theme_mode', themePanelMode)
      const result = await apiFetch<{ theme_config: Partial<RegistrationThemeConfig> }>(
        `/api/v1/tenant/events/${event.id}/branding/background`,
        { method: 'POST', tenantId, idempotency: true, body },
      )
      setTheme(normalizeRegistrationTheme(result.theme_config))
      toast(t('saved'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setUploadingBackground(false)
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
      <PageHeader
        title={t('registrationBuilderTitle')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: locale === 'ar' ? (event.name.ar || event.name.en) : (event.name.en || event.name.ar), href: `/tenant/events/${event.id}` },
          { label: t('registrationForm') },
        ]}
      />
      <div className="-mx-4 sm:-mx-6 lg:-mx-8 mb-[-1rem] sm:mb-[-1.5rem] lg:mb-[-2rem] flex h-[calc(100vh-12rem)] flex-col overflow-hidden sm:h-[calc(100vh-13rem)]">
        {/* Top Toolbar */}
        <div className="flex flex-wrap items-center gap-2 border-b border-[var(--border)] bg-[var(--surface-elevated)] px-3 py-2 sm:px-4">
          <input
            value={formName}
            onChange={(e) => setFormName(e.target.value)}
            className="w-44 sm:w-52 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm font-medium text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none focus:ring-1 focus:ring-[var(--brand)]/20"
            placeholder={t('registrationBuilderFormName')}
          />
          <div className="flex-1" />

          <button
            type="button"
            onClick={() => setShowThemePanel(true)}
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm text-[var(--muted)] transition hover:border-[var(--brand)]/40 hover:bg-[color-mix(in_srgb,var(--brand)_10%,transparent)] hover:text-[var(--brand)]"
          >
            <Palette size={14} />
            {t('registrationBuilderTheme')}
          </button>

          <button
            type="button"
            onClick={() => setShowEmbedModal(true)}
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm text-[var(--muted)] transition hover:border-[var(--brand)]/40 hover:bg-[color-mix(in_srgb,var(--brand)_10%,transparent)] hover:text-[var(--brand)]"
          >
            <Code size={14} />
            {t('registrationBuilderEmbed')}
          </button>

          <a
            href={`/${locale}/tenant/events/${event.id}/registration-preview`}
            target="_blank"
            rel="noopener noreferrer"
            className="hidden sm:flex items-center gap-1.5 rounded-lg border border-[var(--border)] px-3 py-1.5 text-sm text-[var(--muted)] transition hover:border-[var(--brand)]/40 hover:bg-[color-mix(in_srgb,var(--brand)_10%,transparent)] hover:text-[var(--brand)]"
          >
            <Eye size={14} />
            {t('registrationBuilderPreview')}
          </a>

          <button
            type="button"
            onClick={handleSave}
            disabled={submitting}
            className="flex items-center gap-1.5 rounded-lg bg-[var(--brand)] px-4 py-1.5 text-sm font-medium text-white transition hover:opacity-90 disabled:opacity-50"
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
                    const alreadyAdded = EVENT_DISPLAY_TYPES.has(type) && usedEventInfoTypes.has(type)
                    return (
                      <button
                        key={type}
                        type="button"
                        disabled={alreadyAdded}
                        onClick={() => addField(type)}
                        className={`flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-sm transition ${
                          alreadyAdded
                            ? 'cursor-not-allowed text-[var(--muted)] opacity-50'
                            : 'text-[var(--ink)] hover:bg-[var(--surface-elevated)] hover:shadow-sm'
                        }`}
                      >
                        <Icon size={14} className="text-[var(--muted)] shrink-0" />
                        <span className="truncate">{locale === 'ar' ? TYPE_LABELS[type]?.ar : TYPE_LABELS[type]?.en}</span>
                        {!alreadyAdded && (
                          <Plus size={12} className="ms-auto text-[var(--muted)] opacity-50" />
                        )}
                      </button>
                    )
                  })}
                </div>
              </div>
            ))}
          </aside>

          {/* CENTER: Canvas */}
          <div className="flex-1 overflow-y-auto p-4 lg:p-6">
            <div
              className="registration-builder-canvas mx-auto max-w-2xl rounded-2xl border border-[var(--border)] bg-[var(--surface-elevated)]/95 p-5 lg:p-6 shadow-lg backdrop-blur-sm"
              style={{
                ...canvasStyle,
                ...(pageBackgroundStyle || {}),
              }}
            >

              <DndContext
                id={dndId}
                sensors={sensors}
                collisionDetection={closestCenter}
                onDragEnd={handleDragEnd}
              >
                <SortableContext
                  items={fields.map((f) => f.id)}
                  strategy={rectSortingStrategy}
                >
                  <div className="flex flex-wrap -m-1">
                    {fields.map((field) => (
                      <SortableFieldCard
                        key={field.id}
                        field={field}
                        selected={field.id === selectedId}
                        onSelect={() => setSelectedId(field.id)}
                        locale={locale}
                        eventPreview={eventPreview}
                      />
                    ))}
                  </div>
                </SortableContext>
              </DndContext>

              {fields.length === 0 && (
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
          <aside className={`flex-shrink-0 overflow-y-auto overflow-x-hidden border-s border-[var(--border)] bg-[var(--surface-elevated)] transition-all duration-200`}>
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
                  <div className="space-y-4">
                    <p className="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3 text-xs text-[var(--muted)]">
                      {t('registrationBuilderSystemField')}
                    </p>
                    <div>
                      <label className="mb-1 block text-xs font-medium text-[var(--muted)]">
                        {t('registrationBuilderWidth')}
                      </label>
                      <div className="flex gap-1">
                        {(['full', 'half', 'third'] as const).map((w) => (
                          <button
                            key={w}
                            type="button"
                            onClick={() => updateField(selected.id, { width: w })}
                            className={`flex-1 rounded-lg border px-2 py-1.5 text-xs font-medium capitalize transition ${
                              selected.width === w
                                ? 'border-[var(--brand)] bg-[color-mix(in_srgb,var(--brand)_12%,transparent)] text-[var(--brand)]'
                                : 'border-[var(--border)] text-[var(--muted)] hover:border-[var(--brand)]/40'
                            }`}
                          >
                            {w}
                          </button>
                        ))}
                      </div>
                    </div>
                  </div>
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
                    {!LAYOUT_TYPES.has(selected.type) && !EVENT_DISPLAY_TYPES.has(selected.type) && selected.type !== 'consent' && (
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
                    {!LAYOUT_TYPES.has(selected.type) && !EVENT_DISPLAY_TYPES.has(selected.type) && (
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

                    {/* Choice style + color (checkbox / radio) */}
                    {STYLED_CHOICE_TYPES.has(selected.type) && (
                      <>
                        <div>
                          <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                            {t('registrationBuilderChoiceStyle')}
                          </label>
                          <div className="grid grid-cols-1 gap-1.5">
                            {(selected.type === 'radio' ? RADIO_STYLES : CHECKBOX_STYLES).map((style) => {
                              const active = (selected.choice_style || defaultChoiceStyle(selected.type)) === style.value
                              return (
                                <button
                                  key={style.value}
                                  type="button"
                                  onClick={() => updateField(selected.id, { choice_style: style.value })}
                                  className={`rounded-xl px-2.5 py-2 text-start transition ${
                                    active
                                      ? 'border-2 border-[var(--brand)] bg-[var(--brand)]/5 shadow-sm'
                                      : 'border border-[var(--border)] bg-[var(--surface)] hover:border-[var(--brand)]/40'
                                  }`}
                                >
                                  <span className="mb-1.5 block text-[11px] font-semibold text-[var(--ink)]">
                                    {t(style.labelKey)}
                                  </span>
                                  <ChoiceStylePreview
                                    style={style.value}
                                    color={selected.choice_color || '#2563EB'}
                                    fieldType={selected.type}
                                  />
                                </button>
                              )
                            })}
                          </div>
                        </div>

                        <div>
                          <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                            {t('registrationBuilderChoiceColor')}
                          </label>
                          <div className="flex flex-wrap items-center gap-2">
                            {CHOICE_COLOR_PRESETS.map((color) => {
                              const active = (selected.choice_color || '').toUpperCase() === color
                              return (
                                <button
                                  key={color}
                                  type="button"
                                  title={color}
                                  onClick={() => updateField(selected.id, { choice_color: color })}
                                  className={`h-7 w-7 rounded-full border-2 transition ${
                                    active ? 'border-[var(--ink)] scale-110' : 'border-transparent'
                                  }`}
                                  style={{ backgroundColor: color }}
                                />
                              )
                            })}
                            <label className="relative h-7 w-7 overflow-hidden rounded-full border border-[var(--border)] cursor-pointer">
                              <span className="sr-only">{t('registrationBuilderChoiceColorCustom')}</span>
                              <input
                                type="color"
                                value={selected.choice_color || '#2563EB'}
                                onChange={(e) => updateField(selected.id, { choice_color: e.target.value.toUpperCase() })}
                                className="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                              />
                              <span
                                className="block h-full w-full"
                                style={{ backgroundColor: selected.choice_color || '#2563EB' }}
                              />
                            </label>
                          </div>
                        </div>
                      </>
                    )}

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
          <div className="flex-1 bg-black/40 backdrop-blur-sm" onClick={() => setShowThemePanel(false)} />
          <div className="w-80 sm:w-96 bg-[var(--surface-elevated)] shadow-2xl p-5 mt-16 overflow-y-auto border-s border-[var(--border)]">
            <div className="flex items-center justify-between mb-5">
              <h3 className="text-base font-semibold text-[var(--ink)]">
                {t('registrationBuilderBrandTheme')}
              </h3>
              <button type="button" onClick={() => setShowThemePanel(false)} className="rounded-md p-1 text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--ink)]">
                <X size={18} />
              </button>
            </div>

            <div className="mb-5 flex gap-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1">
              {([
                { id: 'light' as const, label: t('registrationBuilderThemeLight') },
                { id: 'dark' as const, label: t('registrationBuilderThemeDark') },
              ]).map((mode) => (
                <button
                  key={mode.id}
                  type="button"
                  onClick={() => setThemePanelMode(mode.id)}
                  className={`flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition ${
                    themePanelMode === mode.id
                      ? 'bg-[var(--surface-elevated)] text-[var(--ink)] shadow-sm'
                      : 'text-[var(--muted)] hover:text-[var(--ink)]'
                  }`}
                >
                  {mode.label}
                </button>
              ))}
            </div>

            <div className="space-y-5">
              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderPrimaryColor')}
                </label>
                <div className="flex gap-2 items-center">
                  <input
                    type="color"
                    value={activeModeColors.primary_color}
                    onChange={(e) => patchMode(themePanelMode, { primary_color: e.target.value })}
                    className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] bg-transparent p-0.5"
                  />
                  <input
                    value={activeModeColors.primary_color}
                    onChange={(e) => patchMode(themePanelMode, { primary_color: e.target.value })}
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
                    value={activeModeColors.accent_color}
                    onChange={(e) => patchMode(themePanelMode, { accent_color: e.target.value })}
                    className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] bg-transparent p-0.5"
                  />
                  <input
                    value={activeModeColors.accent_color}
                    onChange={(e) => patchMode(themePanelMode, { accent_color: e.target.value })}
                    className="flex-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  />
                </div>
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderPageBackground')}
                </label>
                <div className="mb-3 flex gap-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1">
                  {([
                    { id: 'solid', label: t('registrationBuilderBgSolid') },
                    { id: 'gradient', label: t('registrationBuilderBgGradient') },
                    { id: 'image', label: t('registrationBuilderBgImage') },
                  ] as const).map((mode) => (
                    <button
                      key={mode.id}
                      type="button"
                      onClick={() => patchMode(themePanelMode, { background_mode: mode.id })}
                      className={`flex-1 rounded-md px-2 py-1.5 text-xs font-medium transition ${
                        activeModeColors.background_mode === mode.id
                          ? 'bg-[var(--surface-elevated)] text-[var(--ink)] shadow-sm'
                          : 'text-[var(--muted)] hover:text-[var(--ink)]'
                      }`}
                    >
                      {mode.label}
                    </button>
                  ))}
                </div>

                {activeModeColors.background_mode === 'solid' ? (
                  <div className="flex gap-2 items-center">
                    <input
                      type="color"
                      value={activeModeColors.background_color}
                      onChange={(e) => patchMode(themePanelMode, { background_color: e.target.value })}
                      className="h-9 w-9 cursor-pointer rounded-lg border border-[var(--border)] bg-transparent p-0.5"
                    />
                    <input
                      value={activeModeColors.background_color}
                      onChange={(e) => patchMode(themePanelMode, { background_color: e.target.value })}
                      className="flex-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1.5 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                    />
                  </div>
                ) : null}

                {activeModeColors.background_mode === 'gradient' && activeModeColors.background_gradient ? (
                  <div className="space-y-3">
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-xs text-[var(--muted)]">{t('registrationBuilderBgFrom')}</span>
                      <input
                        type="color"
                        value={activeModeColors.background_gradient.stops?.[0]?.color ?? activeModeColors.background_color}
                        onChange={(e) => patchMode(themePanelMode, {
                          background_gradient: {
                            type: 'linear',
                            angle: activeModeColors.background_gradient?.angle ?? 160,
                            stops: [
                              { color: e.target.value, position: 0 },
                              activeModeColors.background_gradient?.stops?.[1] ?? { color: '#e2e8f0', position: 100 },
                            ],
                          },
                        })}
                        className="h-8 w-12 cursor-pointer rounded border border-[var(--border)] bg-transparent"
                      />
                    </div>
                    <div className="flex items-center justify-between gap-3">
                      <span className="text-xs text-[var(--muted)]">{t('registrationBuilderBgTo')}</span>
                      <input
                        type="color"
                        value={activeModeColors.background_gradient.stops?.[1]?.color ?? '#e2e8f0'}
                        onChange={(e) => patchMode(themePanelMode, {
                          background_gradient: {
                            type: 'linear',
                            angle: activeModeColors.background_gradient?.angle ?? 160,
                            stops: [
                              activeModeColors.background_gradient?.stops?.[0] ?? { color: activeModeColors.background_color, position: 0 },
                              { color: e.target.value, position: 100 },
                            ],
                          },
                        })}
                        className="h-8 w-12 cursor-pointer rounded border border-[var(--border)] bg-transparent"
                      />
                    </div>
                    <label className="flex items-center justify-between gap-3 text-xs text-[var(--muted)]">
                      <span>{t('registrationBuilderBgAngle')}</span>
                      <input
                        type="number"
                        min={0}
                        max={360}
                        value={Math.round(activeModeColors.background_gradient.angle ?? 160)}
                        onChange={(e) => patchMode(themePanelMode, {
                          background_gradient: {
                            type: 'linear',
                            angle: Number(e.target.value) || 0,
                            stops: activeModeColors.background_gradient?.stops ?? [
                              { color: activeModeColors.background_color, position: 0 },
                              { color: '#e2e8f0', position: 100 },
                            ],
                          },
                        })}
                        className="w-20 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2 py-1 text-sm text-[var(--ink)]"
                      />
                    </label>
                    <div className="h-10 w-full rounded-lg border border-[var(--border)]" style={pageBackgroundStyle} />
                  </div>
                ) : null}

                {activeModeColors.background_mode === 'image' ? (
                  <div className="space-y-3">
                    {activeModeColors.background_image_url ? (
                      <div
                        className="h-24 w-full rounded-lg border border-[var(--border)] bg-cover bg-center"
                        style={{ backgroundImage: `url(${activeModeColors.background_image_url})` }}
                      />
                    ) : (
                      <div className="flex h-24 items-center justify-center rounded-lg border border-dashed border-[var(--border)] text-xs text-[var(--muted)]">
                        {t('registrationBuilderNoBackgroundImage')}
                      </div>
                    )}
                    <label className="flex cursor-pointer items-center justify-center rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-xs font-medium text-[var(--ink)] transition hover:border-[var(--brand)]/40">
                      {uploadingBackground ? t('registrationBuilderUploading') : t('registrationBuilderUploadBackground')}
                      <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        disabled={uploadingBackground}
                        onChange={(e) => void handleBackgroundUpload(e.target.files?.[0] ?? null)}
                      />
                    </label>
                    {activeModeColors.background_image_path ? (
                      <button
                        type="button"
                        className="w-full rounded-lg border border-[var(--danger)]/20 px-3 py-2 text-xs font-medium text-[var(--danger)]"
                        onClick={() => patchMode(themePanelMode, {
                          background_mode: 'solid',
                          background_image_path: null,
                          background_image_url: null,
                        })}
                      >
                        {t('registrationBuilderClearBackground')}
                      </button>
                    ) : null}
                  </div>
                ) : null}
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderFontFamilyEn')}
                </label>
                <select
                  value={theme.font_family_en}
                  onChange={(e) => setTheme((current) => normalizeRegistrationTheme({
                    ...current,
                    font_family_en: e.target.value,
                  }))}
                  className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-2 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  style={{ fontFamily: registrationFontFamily(theme.font_family_en) }}
                >
                  {REGISTRATION_FONT_OPTIONS_EN.map((font) => (
                    <option key={font} value={font} style={{ fontFamily: registrationFontFamily(font) }}>
                      {font}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="mb-1.5 block text-xs font-medium text-[var(--muted)]">
                  {t('registrationBuilderFontFamilyAr')}
                </label>
                <select
                  value={theme.font_family_ar}
                  onChange={(e) => setTheme((current) => normalizeRegistrationTheme({
                    ...current,
                    font_family_ar: e.target.value,
                  }))}
                  className="w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-2 text-sm text-[var(--ink)] focus:border-[var(--brand)] focus:outline-none"
                  style={{ fontFamily: registrationFontFamily(theme.font_family_ar) }}
                >
                  {REGISTRATION_FONT_OPTIONS_AR.map((font) => (
                    <option key={font} value={font} style={{ fontFamily: registrationFontFamily(font) }}>
                      {font}
                    </option>
                  ))}
                </select>
                <p
                  className="mt-2 rounded-lg border border-[var(--border)] bg-[var(--surface)] px-2.5 py-2 text-sm text-[var(--ink)]"
                  style={{ fontFamily: resolveRegistrationFontFamily(theme, locale) }}
                >
                  {t('registrationBuilderFontPreview')}
                </p>
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
