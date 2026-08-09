import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import {
  cssLengthUnitOptions,
  defaultMinForUnit,
  defaultValueForUnit,
  formatCssLength,
  parseCssLength,
  type CssLength,
  type CssLengthUnit,
} from '@/lib/cssLength'

type Props = {
  label: string
  name: string
  value: unknown
  unit?: unknown
  locale: 'en' | 'ar'
  fallback?: CssLength
  /** When unit changes, optionally remapped default value */
  preferredPx?: number
  onChange: (next: CssLength) => void
  hint?: string
}

export default function LengthUnitField({
  label,
  name,
  value,
  unit,
  locale,
  fallback = { value: 400, unit: 'px' },
  preferredPx = 400,
  onChange,
  hint,
}: Props) {
  const length = parseCssLength(value, unit, fallback)
  const min = defaultMinForUnit(length.unit)

  return (
    <div className="space-y-1">
      <div className="grid grid-cols-[1fr_88px] gap-2">
        <TextInput
          label={label}
          name={name}
          type="number"
          min={min}
          step={length.unit === 'px' ? 1 : 0.1}
          value={String(length.value)}
          onChange={(e) => {
            const next = Number(e.target.value)
            onChange({
              value: Number.isFinite(next) ? next : fallback.value,
              unit: length.unit,
            })
          }}
        />
        <SelectInput
          label={locale === 'ar' ? 'الوحدة' : 'Unit'}
          name={`${name}_unit`}
          value={length.unit}
          onChange={(e) => {
            const nextUnit = e.target.value as CssLengthUnit
            onChange({
              value: length.unit === nextUnit ? length.value : defaultValueForUnit(nextUnit, preferredPx),
              unit: nextUnit,
            })
          }}
          options={cssLengthUnitOptions(locale)}
        />
      </div>
      {hint ? <p className="text-[10px] text-muted-foreground">{hint}</p> : null}
      <p className="text-[10px] text-white/40">
        {locale === 'ar' ? 'القيمة المطبّقة:' : 'Applied:'}{' '}
        <code className="text-violet-300/80">{formatCssLength(length)}</code>
      </p>
    </div>
  )
}
