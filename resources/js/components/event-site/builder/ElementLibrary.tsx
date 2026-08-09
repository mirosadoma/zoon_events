import {
  Type,
  Image as ImageIcon,
  Square,
  MousePointerClick,
  Heading,
  AlignLeft,
  List,
  Video,
  Minus,
  Diamond,
  Info,
  PanelTop,
  PanelBottom,
  Images,
  FormInput,
  Sparkles,
  type LucideIcon,
} from 'lucide-react'
import PaletteDraggable from './PaletteDraggable'
import type { BuilderDragData } from '@/lib/siteBuilderDnd'

const LANDING_ELEMENTS: Array<{
  kind: string
  icon: LucideIcon
  labelEn: string
  labelAr: string
  hintEn: string
  hintAr: string
}> = [
  { kind: 'heading', icon: Heading, labelEn: 'Heading', labelAr: 'عنوان', hintEn: 'Title text', hintAr: 'نص عنوان' },
  { kind: 'text', icon: AlignLeft, labelEn: 'Text', labelAr: 'نص', hintEn: 'Paragraph', hintAr: 'فقرة' },
  { kind: 'image', icon: ImageIcon, labelEn: 'Image', labelAr: 'صورة', hintEn: 'Media', hintAr: 'وسائط' },
  { kind: 'button', icon: MousePointerClick, labelEn: 'Button', labelAr: 'زر', hintEn: 'CTA', hintAr: 'زر إجراء' },
  { kind: 'box', icon: Square, labelEn: 'Box', labelAr: 'صندوق', hintEn: 'Container', hintAr: 'حاوية' },
  { kind: 'card', icon: Type, labelEn: 'Card', labelAr: 'بطاقة', hintEn: 'Content card', hintAr: 'بطاقة محتوى' },
  { kind: 'details', icon: Info, labelEn: 'Details', labelAr: 'تفاصيل', hintEn: 'Event info', hintAr: 'معلومات الحدث' },
  { kind: 'list', icon: List, labelEn: 'List', labelAr: 'قائمة', hintEn: 'Bullets', hintAr: 'نقاط' },
  { kind: 'video', icon: Video, labelEn: 'Video', labelAr: 'فيديو', hintEn: 'Embed', hintAr: 'تضمين' },
  { kind: 'divider', icon: Minus, labelEn: 'Line', labelAr: 'فاصل', hintEn: 'Divider', hintAr: 'فاصل' },
  { kind: 'shape', icon: Diamond, labelEn: 'Shape', labelAr: 'شكل', hintEn: 'Decoration', hintAr: 'زخرفة' },
]

const QUICK_WIDGETS: Array<{
  id: string
  blockType: string
  icon: LucideIcon
  labelEn: string
  labelAr: string
  hintEn: string
  hintAr: string
}> = [
  { id: 'header', blockType: 'header', icon: PanelTop, labelEn: 'Header', labelAr: 'رأس', hintEn: 'Nav bar', hintAr: 'شريط تنقل' },
  { id: 'hero', blockType: 'hero', icon: Sparkles, labelEn: 'Hero', labelAr: 'بطل', hintEn: 'Banner', hintAr: 'بانر' },
  { id: 'footer', blockType: 'footer', icon: PanelBottom, labelEn: 'Footer', labelAr: 'تذييل', hintEn: 'Page end', hintAr: 'نهاية الصفحة' },
  { id: 'carousel', blockType: 'carousel', icon: Images, labelEn: 'Carousel', labelAr: 'سلايدر', hintEn: 'Slideshow', hintAr: 'عرض شرائح' },
  { id: 'form', blockType: 'form', icon: FormInput, labelEn: 'Form', labelAr: 'نموذج', hintEn: 'Collect data', hintAr: 'جمع بيانات' },
]

type Props = {
  locale: 'en' | 'ar'
  search: string
  onSearchChange: (value: string) => void
  onAddSection: () => void
  onPickElement: (kind: string) => void
  onPickWidget: (blockType: string) => void
}

export default function ElementLibrary({
  locale,
  search,
  onSearchChange,
  onAddSection,
  onPickElement,
  onPickWidget,
}: Props) {
  const isAr = locale === 'ar'
  const q = search.trim().toLowerCase()

  function elementDragData(kind: string): BuilderDragData {
    return { kind: 'palette-element', elementKind: kind }
  }

  function widgetDragData(blockType: string): BuilderDragData {
    return { kind: 'palette-block', blockType }
  }

  const elements = LANDING_ELEMENTS.filter((el) => {
    if (!q) return true
    return el.labelEn.toLowerCase().includes(q) || el.labelAr.includes(q) || el.kind.includes(q)
  })

  const widgets = QUICK_WIDGETS.filter((w) => {
    if (!q) return true
    return w.labelEn.toLowerCase().includes(q) || w.labelAr.includes(q) || w.blockType.includes(q)
  })

  return (
    <div className="flex min-h-0 flex-1 flex-col">
      <div className="shrink-0 space-y-2.5 border-b border-white/10 p-3">
        <div className="flex items-center justify-between gap-2">
          <div>
            <h3 className="text-sm font-bold text-white/90">{isAr ? 'العناصر' : 'Elements'}</h3>
            <p className="text-[10px] text-white/40">{isAr ? 'اسحب إلى الصفحة' : 'Drag onto the page'}</p>
          </div>
          <button
            type="button"
            onClick={onAddSection}
            className="rounded-lg bg-violet-600 px-2.5 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-violet-500"
          >
            ＋ {isAr ? 'سيكشن' : 'Section'}
          </button>
        </div>
        <input
          type="search"
          value={search}
          onChange={(e) => onSearchChange(e.target.value)}
          placeholder={isAr ? 'ابحث عن عنصر...' : 'Search elements...'}
          className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/35 focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-400/50"
        />
      </div>

      <div className="min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain p-3">
        <section>
          <h4 className="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/40">
            {isAr ? 'محتوى السيكشن' : 'Section content'}
          </h4>
          <div className="grid grid-cols-2 gap-2">
            {elements.map((el) => (
              <PaletteDraggable
                key={el.kind}
                id={`palette-el-${el.kind}`}
                dragData={elementDragData(el.kind)}
                label={isAr ? el.labelAr : el.labelEn}
                hint={isAr ? el.hintAr : el.hintEn}
                icon={el.icon}
                onClick={() => onPickElement(el.kind)}
              />
            ))}
          </div>
        </section>

        {widgets.length > 0 && (
          <section>
            <h4 className="mb-2 text-[10px] font-semibold uppercase tracking-[0.14em] text-white/40">
              {isAr ? 'أقسام جاهزة' : 'Quick widgets'}
            </h4>
            <div className="grid grid-cols-2 gap-2">
              {widgets.map((widget) => (
                <PaletteDraggable
                  key={widget.id}
                  id={`palette-widget-${widget.id}`}
                  dragData={widgetDragData(widget.blockType)}
                  label={isAr ? widget.labelAr : widget.labelEn}
                  hint={isAr ? widget.hintAr : widget.hintEn}
                  icon={widget.icon}
                  onClick={() => onPickWidget(widget.blockType)}
                />
              ))}
            </div>
          </section>
        )}
      </div>
    </div>
  )
}
