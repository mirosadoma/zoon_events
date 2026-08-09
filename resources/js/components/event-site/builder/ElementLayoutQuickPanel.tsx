import { AlignCenter, AlignLeft, AlignRight } from 'lucide-react'
import { WIDTH_PRESETS, alignElementPatch, widthElementPatch } from '@/lib/elementLayoutStyle'

type ElementLayout = {
  col_span?: number
  col_start?: number
  align?: 'start' | 'center' | 'end'
}

type Props = {
  element: ElementLayout
  locale: 'en' | 'ar'
  freeform?: boolean
  onChange: (patch: Partial<ElementLayout>) => void
}

export default function ElementLayoutQuickPanel({ element, locale, freeform = false, onChange }: Props) {
  const isAr = locale === 'ar'
  const currentAlign = element.align ?? (element.col_start && element.col_start > 1 ? 'start' : 'start')
  const currentSpan = element.col_span ?? 6

  if (freeform) {
    return (
      <div className="mx-4 mb-4 rounded-lg border border-violet-400/30 bg-violet-500/10 p-3">
        <p className="mb-2 text-xs font-semibold text-violet-200">
          {isAr ? 'موضع العنصر (Freeform)' : 'Element position (Freeform)'}
        </p>
        <p className="text-[11px] leading-relaxed text-violet-200/70">
          {isAr
            ? 'اسحب العنصر بالـ Grip أو عدّل X/Y من لوحة المحتوى.'
            : 'Drag the element with the grip handle, or edit X/Y in the content panel.'}
        </p>
      </div>
    )
  }

  return (
    <div className="mx-4 mb-4 space-y-3 rounded-lg border border-violet-400/30 bg-violet-500/10 p-3">
      <div>
        <p className="mb-2 text-xs font-semibold text-violet-200">
          {isAr ? 'محاذاة أفقية داخل السيكشن' : 'Horizontal position in section'}
        </p>
        <div className="flex gap-1">
          {(
            [
              { id: 'start' as const, icon: AlignLeft, labelEn: 'Left', labelAr: 'يسار' },
              { id: 'center' as const, icon: AlignCenter, labelEn: 'Center', labelAr: 'وسط' },
              { id: 'end' as const, icon: AlignRight, labelEn: 'Right', labelAr: 'يمين' },
            ] as const
          ).map(({ id, icon: Icon, labelEn, labelAr }) => (
            <button
              key={id}
              type="button"
              title={isAr ? labelAr : labelEn}
              onClick={() => onChange(alignElementPatch(id))}
              className={`flex flex-1 items-center justify-center gap-1 rounded-md border py-2 text-xs font-semibold transition ${
                currentAlign === id
                  ? 'border-violet-400 bg-violet-600 text-white'
                  : 'border-white/15 bg-white/5 text-white/70 hover:border-violet-400/50 hover:text-white'
              }`}
            >
              <Icon className="h-4 w-4" />
              <span>{isAr ? labelAr : labelEn}</span>
            </button>
          ))}
        </div>
      </div>

      <div>
        <p className="mb-2 text-xs font-semibold text-violet-200">
          {isAr ? 'عرض العنصر' : 'Element width'}
        </p>
        <div className="flex flex-wrap gap-1">
          {WIDTH_PRESETS.map(({ span, labelEn, labelAr }) => (
            <button
              key={span}
              type="button"
              onClick={() => onChange(widthElementPatch(span))}
              className={`min-w-[3rem] rounded-md border px-2.5 py-1.5 text-xs font-semibold transition ${
                currentSpan === span
                  ? 'border-violet-400 bg-violet-600 text-white'
                  : 'border-white/15 bg-white/5 text-white/70 hover:border-violet-400/50 hover:text-white'
              }`}
            >
              {isAr ? labelAr : labelEn}
            </button>
          ))}
        </div>
      </div>

      <p className="text-[11px] leading-relaxed text-violet-200/70">
        {isAr
          ? 'نصيحة: اضغط «وسط» لتوسيط الصورة أو النص. غيّر العرض إلى 50% أو 100% حسب الحاجة.'
          : 'Tip: click Center to center an image or text block. Adjust width to 50% or 100% as needed.'}
      </p>
    </div>
  )
}
