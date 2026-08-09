import { asElementArray } from '@/lib/sectionElementFactory'

const ELEMENT_ICONS: Record<string, string> = {
  hero: '◩',
  heading: 'H',
  text: 'T',
  image: '▧',
  button: '▣',
  box: '□',
  card: '▤',
  details: '◫',
  list: '☷',
  video: '▶',
  divider: '━',
  shape: '◆',
  quote: '❝',
  icon: '★',
  html: '<>',
  spacer: '▭',
}

const ELEMENT_LABELS: Record<string, { en: string; ar: string }> = {
  hero: { en: 'Hero', ar: 'بطل' },
  heading: { en: 'Heading', ar: 'عنوان' },
  text: { en: 'Text', ar: 'نص' },
  image: { en: 'Image', ar: 'صورة' },
  button: { en: 'Button', ar: 'زر' },
  box: { en: 'Box', ar: 'صندوق' },
  card: { en: 'Card', ar: 'بطاقة' },
  details: { en: 'Event details', ar: 'تفاصيل الحدث' },
  list: { en: 'List', ar: 'قائمة' },
  video: { en: 'Video', ar: 'فيديو' },
  divider: { en: 'Line', ar: 'فاصل' },
  shape: { en: 'Shape', ar: 'شكل' },
  quote: { en: 'Quote', ar: 'اقتباس' },
  icon: { en: 'Icon', ar: 'أيقونة' },
  html: { en: 'HTML', ar: 'HTML' },
  spacer: { en: 'Spacer', ar: 'فراغ' },
}

type Block = {
  id: string
  type: string
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
}

type BlockTypeLabels = Record<string, { en: string; ar: string }>

type Props = {
  blocks: Block[]
  selectedBlockId: string | null
  selectedElementId: string | null
  locale: 'en' | 'ar'
  blockTypeLabels: BlockTypeLabels
  onSelectBlock: (id: string) => void
  onSelectElement: (blockId: string, elementId: string) => void
}

function elementLabel(element: Record<string, unknown>, locale: 'en' | 'ar'): string {
  const kind = typeof element.kind === 'string' ? element.kind : 'text'
  const title = typeof element.title === 'string' ? element.title : ''
  const body = typeof element.body === 'string' ? element.body : ''
  const label = typeof element.label === 'string' ? element.label : ''
  const text = title || body || label
  if (text) return text.length > 24 ? `${text.slice(0, 24)}…` : text
  return ELEMENT_LABELS[kind]?.[locale] ?? kind
}

export default function PageStructurePanel({
  blocks,
  selectedBlockId,
  selectedElementId,
  locale,
  blockTypeLabels,
  onSelectBlock,
  onSelectElement,
}: Props) {
  const isAr = locale === 'ar'
  const contentBlocks = blocks.filter((b) => b.type !== 'header' && b.type !== 'footer')

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-bold text-white/90">{isAr ? 'هيكل الصفحة' : 'Page structure'}</h3>
        <span className="text-xs text-white/40">{contentBlocks.length}</span>
      </div>
      {contentBlocks.length === 0 ? (
        <p className="text-xs text-white/40">{isAr ? 'لا توجد أقسام بعد.' : 'No sections yet.'}</p>
      ) : (
        contentBlocks.map((block, index) => {
          const label = blockTypeLabels[block.type]?.[locale] ?? block.type
          const content = locale === 'ar' ? block.content_ar : block.content_en
          const title = typeof content.title === 'string' ? content.title : ''
          const elements = block.type === 'section' ? asElementArray(content.elements) : []

          return (
            <div
              key={block.id}
              className={`rounded-xl border p-3 transition ${
                selectedBlockId === block.id && !selectedElementId
                  ? 'border-violet-400/60 bg-violet-500/10'
                  : 'border-white/10 bg-white/[0.03]'
              }`}
            >
              <button
                type="button"
                onClick={() => onSelectBlock(block.id)}
                className="flex w-full items-center justify-between gap-2 text-start"
              >
                <b className="text-xs text-white/85">
                  {isAr ? 'قسم' : 'Section'} {index + 1} · {label}
                </b>
                <span className="text-xs text-white/40">
                  {block.type === 'section' ? `${elements.length} ${isAr ? 'عناصر' : 'elements'}` : ''}
                </span>
              </button>
              {title && <p className="mt-1 truncate text-[10px] text-white/45">{title}</p>}
              {elements.length > 0 && (
                <div className="mt-2 flex flex-wrap gap-1">
                  {elements.map((el) => {
                    const kind = el.kind ?? 'text'
                    const icon = ELEMENT_ICONS[kind] ?? '•'
                    return (
                      <button
                        key={el.id}
                        type="button"
                        onClick={() => onSelectElement(block.id, el.id)}
                        className={`rounded px-2 py-1 text-xs transition ${
                          selectedElementId === el.id
                            ? 'bg-violet-600/80 text-white ring-1 ring-violet-400/50'
                            : 'bg-white/10 text-white/65 hover:bg-white/15 hover:text-white'
                        }`}
                      >
                        {icon} {elementLabel(el as Record<string, unknown>, locale)}
                      </button>
                    )
                  })}
                </div>
              )}
            </div>
          )
        })
      )}
    </div>
  )
}
