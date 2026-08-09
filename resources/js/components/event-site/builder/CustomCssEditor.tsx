import TextareaInput from '@/components/forms/TextareaInput'
import TextInput from '@/components/forms/TextInput'

type Props = {
  locale: 'en' | 'ar'
  customClass: string
  customCss: string
  onClassChange: (value: string) => void
  onCssChange: (value: string) => void
}

export default function CustomCssEditor({ locale, customClass, customCss, onClassChange, onCssChange }: Props) {
  const isAr = locale === 'ar'

  return (
    <div className="space-y-3">
      <TextInput
        label={isAr ? 'CSS class إضافي' : 'Extra CSS class'}
        name="custom_class"
        value={customClass}
        onChange={(e) => onClassChange(e.target.value)}
        placeholder="my-section highlight-box"
      />
      <div className="space-y-1.5">
        <label className="grid gap-1.5 text-sm">
          <span className="text-xs font-medium text-white/60">
            {isAr ? 'CSS مخصص' : 'Custom CSS'}
          </span>
          <textarea
            value={customCss}
            onChange={(e) => onCssChange(e.target.value)}
            rows={6}
            dir="ltr"
            spellCheck={false}
            placeholder={`padding: 2rem;\nbackground: linear-gradient(...);\nborder-radius: 16px;`}
            className="w-full rounded-md border border-white/10 bg-[#0d0d18] px-3 py-2 font-mono text-xs leading-relaxed text-violet-100 placeholder:text-white/25 focus:border-violet-400 focus:outline-none focus:ring-1 focus:ring-violet-400/40"
          />
        </label>
        <p className="text-[10px] leading-relaxed text-white/40">
          {isAr
            ? 'أضف خصائص CSS لهذا العنصر فقط (بدون selector).'
            : 'Add CSS properties for this element only (no selector needed).'}
        </p>
      </div>
    </div>
  )
}
