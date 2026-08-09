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
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import LogoEditor from '../LogoEditor'
import { Plus, Trash2, GripVertical } from 'lucide-react'

export type NavLink = {
  id: string
  label_en: string
  label_ar: string
  href: string
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
  onLinksChange: (links: NavLink[]) => void
  onCtaLabelsChange: (labels: { en: string; ar: string }) => void
}

function mergeLinks(enValue: unknown, arValue: unknown): NavLink[] {
  const enItems = Array.isArray(enValue) ? enValue : []
  const arItems = Array.isArray(arValue) ? arValue : []

  const arMap = new Map<string, Record<string, unknown>>()
  arItems.forEach((item, index) => {
    if (typeof item === 'object' && item !== null) {
      const rec = item as Record<string, unknown>
      const id = typeof rec.id === 'string' ? rec.id : `link_${index}`
      arMap.set(id, rec)
    }
  })

  if (enItems.length === 0 && arItems.length > 0) {
    return arItems
      .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
      .map((item, index) => {
        const id = typeof item.id === 'string' ? item.id : `link_${index}`
        return {
          id,
          label_en: typeof item.label_en === 'string' ? item.label_en : (typeof item.label === 'string' ? item.label : ''),
          label_ar: typeof item.label_ar === 'string' ? item.label_ar : (typeof item.label === 'string' ? item.label : ''),
          href: typeof item.href === 'string' ? item.href : '#',
        }
      })
  }

  return enItems
    .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
    .map((item, index) => {
      const id = typeof item.id === 'string' ? item.id : `link_${index}`
      const arItem = arMap.get(id)
      return {
        id,
        label_en: typeof item.label_en === 'string' ? item.label_en : (typeof item.label === 'string' ? item.label : ''),
        label_ar:
          typeof arItem?.label_ar === 'string'
            ? arItem.label_ar
            : typeof arItem?.label === 'string'
              ? arItem.label
              : typeof item.label_ar === 'string'
                ? item.label_ar
                : '',
        href: typeof item.href === 'string' ? item.href : (typeof arItem?.href === 'string' ? arItem.href : '#'),
      }
    })
}

function extractLogoValue(refs: Record<string, unknown>, options: Record<string, unknown>): LogoValue {
  return {
    url: typeof refs.logo_url === 'string' ? refs.logo_url : (typeof options.logo_url === 'string' ? options.logo_url : ''),
    path: typeof refs.logo_path === 'string' ? refs.logo_path : '',
    position: typeof options.logo_position === 'string' ? (options.logo_position as 'left' | 'center' | 'right') : 'left',
    size: typeof options.logo_size === 'string' ? (options.logo_size as 'sm' | 'md' | 'lg' | 'custom') : 'md',
    max_height: typeof options.logo_max_height === 'number' ? options.logo_max_height : undefined,
    max_height_unit: typeof options.logo_max_height_unit === 'string' ? options.logo_max_height_unit : 'px',
  }
}

function SortableNavLinkRow({
  link,
  onUpdate,
  onRemove,
}: {
  link: NavLink
  onUpdate: (patch: Partial<NavLink>) => void
  onRemove: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: link.id })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.55 : 1,
  }

  return (
    <div ref={setNodeRef} style={style} className="rounded-md border border-[var(--border)] bg-background p-3 space-y-2">
      <div className="flex items-center gap-2">
        <button
          type="button"
          className="cursor-grab touch-none rounded p-1 text-muted-foreground hover:text-foreground active:cursor-grabbing"
          {...attributes}
          {...listeners}
        >
          <GripVertical className="h-4 w-4" />
        </button>
        <span className="text-xs font-medium text-muted-foreground">#{link.id}</span>
        <button
          type="button"
          className="ms-auto rounded p-1.5 text-red-500 hover:bg-red-50"
          onClick={onRemove}
        >
          <Trash2 className="h-4 w-4" />
        </button>
      </div>
      <div className="grid gap-2 sm:grid-cols-2">
        <TextInput
          label="Label (EN)"
          name={`link_en_${link.id}`}
          value={link.label_en}
          onChange={(e) => onUpdate({ label_en: e.target.value })}
        />
        <TextInput
          label="النص (AR)"
          name={`link_ar_${link.id}`}
          value={link.label_ar}
          onChange={(e) => onUpdate({ label_ar: e.target.value })}
          dir="rtl"
        />
      </div>
      <TextInput
        label="URL / #anchor"
        name={`link_href_${link.id}`}
        value={link.href}
        onChange={(e) => onUpdate({ href: e.target.value })}
        placeholder="#about or https://…"
      />
    </div>
  )
}

export default function HeaderEditor({
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
  onLinksChange,
  onCtaLabelsChange,
}: Props) {
  const brand = typeof content.brand === 'string' ? content.brand : ''
  const ctaLabelEn = typeof contentEn.cta_label === 'string' ? contentEn.cta_label : ''
  const ctaLabelAr = typeof contentAr.cta_label === 'string' ? contentAr.cta_label : ''
  const links = mergeLinks(contentEn.links, contentAr.links)
  const style = typeof options.style === 'string' ? options.style : 'solid'
  const showLogo = options.show_logo !== false
  const logoValue = extractLogoValue(refs, options)

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const setLinks = useCallback(
    (next: NavLink[]) => onLinksChange(next),
    [onLinksChange],
  )

  function updateLink(id: string, patch: Partial<NavLink>) {
    setLinks(links.map((link) => (link.id === id ? { ...link, ...patch } : link)))
  }

  function addLink() {
    setLinks([
      ...links,
      {
        id: `l_${Math.random().toString(36).slice(2, 8)}`,
        label_en: 'New link',
        label_ar: 'رابط جديد',
        href: '#',
      },
    ])
  }

  function removeLink(id: string) {
    setLinks(links.filter((link) => link.id !== id))
  }

  function handleDragEnd(event: DragEndEvent) {
    const { active, over } = event
    if (over && active.id !== over.id) {
      const oldIndex = links.findIndex((l) => l.id === active.id)
      const newIndex = links.findIndex((l) => l.id === over.id)
      if (oldIndex !== -1 && newIndex !== -1) {
        setLinks(arrayMove(links, oldIndex, newIndex))
      }
    }
  }

  function handleLogoChange(newLogo: LogoValue) {
    onRefsChange({
      logo_url: newLogo.url || '',
      logo_path: newLogo.path || '',
    })
    onOptionsChange({
      logo_position: newLogo.position || 'left',
      logo_size: newLogo.size || 'md',
      logo_max_height: newLogo.max_height,
      logo_max_height_unit: newLogo.max_height_unit || 'px',
    })
  }

  return (
    <div className="space-y-4 p-4 rounded-lg border border-[var(--border)] bg-[var(--surface)]">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'رأس الصفحة' : 'Header'}</h3>

      <TextInput
        label={locale === 'ar' ? 'اسم العلامة' : 'Brand name'}
        name="brand"
        value={brand}
        onChange={(e) => onContentChange({ brand: e.target.value })}
      />

      <SelectInput
        label={locale === 'ar' ? 'النمط' : 'Style'}
        name="header_style"
        value={style}
        onChange={(e) => onOptionsChange({ style: e.target.value })}
        options={[
          { value: 'solid', label: locale === 'ar' ? 'صلب' : 'Solid' },
          { value: 'transparent', label: locale === 'ar' ? 'شفاف' : 'Transparent' },
          { value: 'minimal', label: locale === 'ar' ? 'بسيط' : 'Minimal' },
          { value: 'centered', label: locale === 'ar' ? 'وسط (قديم)' : 'Centered (legacy)' },
        ]}
      />

      <div className="space-y-3 rounded-md border border-violet-400/30 bg-violet-500/10 p-3">
        <p className="text-xs font-semibold text-violet-200">
          {locale === 'ar' ? 'شبكة الهيدر (Grid / Layout)' : 'Header grid / layout'}
        </p>
        <SelectInput
          label={locale === 'ar' ? 'ترتيب العناصر' : 'Element layout'}
          name="header_layout"
          value={typeof options.layout === 'string' ? options.layout : 'brand_start'}
          onChange={(e) => {
            const next = e.target.value
            const patch: Record<string, unknown> = { layout: next }
            // Keep legacy logo_position in sync for older renders
            if (next === 'brand_end') patch.logo_position = 'right'
            else if (next === 'stacked_center') patch.logo_position = 'center'
            else if (next === 'brand_start') patch.logo_position = 'left'
            // 3-zone layouts look best with CTA in its own end zone
            if (next === 'three_zone' || next === 'nav_center' || next === 'spread') {
              if (options.cta_placement !== 'beside_brand') {
                patch.cta_placement = 'own_zone'
              }
            }
            onOptionsChange(patch)
          }}
          options={[
            { value: 'brand_start', label: locale === 'ar' ? 'علامة ← قائمة' : 'Brand → Nav' },
            { value: 'brand_end', label: locale === 'ar' ? 'قائمة ← علامة' : 'Nav → Brand' },
            { value: 'three_zone', label: locale === 'ar' ? '3 مناطق: علامة | قائمة | زر' : '3 zones: Brand | Nav | CTA' },
            { value: 'nav_center', label: locale === 'ar' ? 'قائمة في الوسط' : 'Nav centered' },
            { value: 'stacked_center', label: locale === 'ar' ? 'علامة فوق · قائمة تحت' : 'Brand above · Nav below' },
            { value: 'spread', label: locale === 'ar' ? 'موزّع بالتساوي' : 'Even spread' },
          ]}
        />
        <div className="grid gap-2 sm:grid-cols-2">
          <SelectInput
            label={locale === 'ar' ? 'أقصى عرض' : 'Max width'}
            name="grid_max_width"
            value={typeof options.grid_max_width === 'string' ? options.grid_max_width : '6xl'}
            onChange={(e) => onOptionsChange({ grid_max_width: e.target.value })}
            options={[
              { value: '4xl', label: '4XL' },
              { value: '5xl', label: '5XL' },
              { value: '6xl', label: '6XL' },
              { value: '7xl', label: '7XL' },
              { value: 'full', label: locale === 'ar' ? 'كامل' : 'Full' },
            ]}
          />
          <SelectInput
            label={locale === 'ar' ? 'مسافة المناطق' : 'Zone gap'}
            name="zone_gap"
            value={typeof options.zone_gap === 'string' ? options.zone_gap : 'md'}
            onChange={(e) => onOptionsChange({ zone_gap: e.target.value })}
            options={[
              { value: 'sm', label: locale === 'ar' ? 'صغير' : 'Small' },
              { value: 'md', label: locale === 'ar' ? 'متوسط' : 'Medium' },
              { value: 'lg', label: locale === 'ar' ? 'كبير' : 'Large' },
              { value: 'xl', label: 'XL' },
            ]}
          />
          <SelectInput
            label={locale === 'ar' ? 'مسافة روابط القائمة' : 'Nav link gap'}
            name="nav_gap"
            value={typeof options.nav_gap === 'string' ? options.nav_gap : 'md'}
            onChange={(e) => onOptionsChange({ nav_gap: e.target.value })}
            options={[
              { value: 'sm', label: locale === 'ar' ? 'ضيق' : 'Tight' },
              { value: 'md', label: locale === 'ar' ? 'متوسط' : 'Medium' },
              { value: 'lg', label: locale === 'ar' ? 'واسع' : 'Wide' },
            ]}
          />
          <SelectInput
            label={locale === 'ar' ? 'محاذاة القائمة' : 'Nav align'}
            name="nav_align"
            value={typeof options.nav_align === 'string' ? options.nav_align : 'end'}
            onChange={(e) => onOptionsChange({ nav_align: e.target.value })}
            options={[
              { value: 'start', label: locale === 'ar' ? 'بداية' : 'Start' },
              { value: 'center', label: locale === 'ar' ? 'وسط' : 'Center' },
              { value: 'end', label: locale === 'ar' ? 'نهاية' : 'End' },
            ]}
          />
          <SelectInput
            label={locale === 'ar' ? 'موضع زر الدعوة' : 'CTA placement'}
            name="cta_placement"
            value={typeof options.cta_placement === 'string' ? options.cta_placement : 'with_nav'}
            onChange={(e) => onOptionsChange({ cta_placement: e.target.value })}
            options={[
              { value: 'with_nav', label: locale === 'ar' ? 'مع القائمة' : 'With nav' },
              { value: 'own_zone', label: locale === 'ar' ? 'منطقة مستقلة' : 'Own zone' },
              { value: 'beside_brand', label: locale === 'ar' ? 'بجانب العلامة' : 'Beside brand' },
            ]}
          />
        </div>
        <CheckboxInput
          label={locale === 'ar' ? 'إظهار اسم العلامة بجانب الشعار' : 'Show brand text next to logo'}
          name="show_brand_text"
          checked={options.show_brand_text !== false}
          onChange={(e) => onOptionsChange({ show_brand_text: e.target.checked })}
        />
        <p className="text-[10px] text-white/50">
          {locale === 'ar'
            ? 'غيّر ترتيب المناطق ومسافاتها بدون ما تلمس محتوى الروابط.'
            : 'Change zone order and spacing without editing link content.'}
        </p>
      </div>

      <div className="flex flex-wrap gap-4">
        <CheckboxInput
          label={locale === 'ar' ? 'تثبيت الرأس' : 'Sticky header'}
          name="sticky"
          checked={options.sticky !== false}
          onChange={(e) => onOptionsChange({ sticky: e.target.checked })}
        />
        <CheckboxInput
          label={locale === 'ar' ? 'إظهار زر التسجيل' : 'Show CTA'}
          name="show_cta"
          checked={options.show_cta !== false}
          onChange={(e) => onOptionsChange({ show_cta: e.target.checked })}
        />
        <CheckboxInput
          label={locale === 'ar' ? 'قائمة الجوال' : 'Mobile menu'}
          name="mobile_menu"
          checked={options.mobile_menu !== false}
          onChange={(e) => onOptionsChange({ mobile_menu: e.target.checked })}
        />
        <CheckboxInput
          label={locale === 'ar' ? 'إظهار الشعار' : 'Show logo'}
          name="show_logo"
          checked={showLogo}
          onChange={(e) => onOptionsChange({ show_logo: e.target.checked })}
        />
      </div>

      {options.show_cta !== false && (
        <div className="grid gap-2 sm:grid-cols-2">
          <TextInput
            label="CTA label (EN)"
            name="cta_label_en"
            value={ctaLabelEn}
            onChange={(e) => onCtaLabelsChange({ en: e.target.value, ar: ctaLabelAr })}
            placeholder="Register"
          />
          <TextInput
            label="نص زر الدعوة (AR)"
            name="cta_label_ar"
            value={ctaLabelAr}
            onChange={(e) => onCtaLabelsChange({ en: ctaLabelEn, ar: e.target.value })}
            placeholder="تسجيل"
            dir="rtl"
          />
        </div>
      )}

      {showLogo && (
        <LogoEditor
          value={logoValue}
          onChange={handleLogoChange}
          locale={locale}
          tenantId={tenantId}
          eventId={eventId}
        />
      )}

      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium">{locale === 'ar' ? 'روابط القائمة' : 'Navigation links'}</p>
            <p className="text-xs text-muted-foreground">
              {locale === 'ar' ? 'اسحب لإعادة الترتيب · EN + AR' : 'Drag to reorder · EN + AR labels'}
            </p>
          </div>
          <button type="button" className="button-secondary text-sm inline-flex items-center gap-1" onClick={addLink}>
            <Plus className="h-4 w-4" />
            {locale === 'ar' ? 'إضافة' : 'Add'}
          </button>
        </div>

        {links.length === 0 && (
          <p className="text-sm text-muted-foreground">
            {locale === 'ar' ? 'لا توجد روابط بعد.' : 'No links yet.'}
          </p>
        )}

        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={links.map((l) => l.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {links.map((link) => (
                <SortableNavLinkRow
                  key={link.id}
                  link={link}
                  onUpdate={(patch) => updateLink(link.id, patch)}
                  onRemove={() => removeLink(link.id)}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>
      </div>
    </div>
  )
}
