type Preset = '1' | '2' | '2-left' | '2-right' | '2-narrow-left' | '2-narrow-right' | '3' | '3-center' | '4' | '2x2' | '1-2'

type PresetDef = {
  id: Preset
  spans: number[]
  starts?: number[]
  label: { en: string; ar: string }
}

type Props = {
  value: string
  onChange: (preset: Preset) => void
  locale: 'en' | 'ar'
  inspector?: boolean
}

const PRESETS: PresetDef[] = [
  { id: '2', spans: [6, 6], starts: [1, 7], label: { en: '50 / 50', ar: '50 / 50' } },
  { id: '2-left', spans: [4, 8], starts: [1, 5], label: { en: '33 / 66', ar: '33 / 66' } },
  { id: '2-right', spans: [8, 4], starts: [1, 9], label: { en: '66 / 33', ar: '66 / 33' } },
  { id: '2-narrow-left', spans: [5, 7], starts: [1, 6], label: { en: '40 / 60', ar: '40 / 60' } },
  { id: '2-narrow-right', spans: [7, 5], starts: [1, 8], label: { en: '60 / 40', ar: '60 / 40' } },
  { id: '2x2', spans: [6, 6, 6, 6], starts: [1, 7, 1, 7], label: { en: '2 × 2', ar: '2 × 2' } },
  { id: '3', spans: [4, 4, 4], starts: [1, 5, 9], label: { en: 'Thirds', ar: 'ثلاثة' } },
  { id: '3-center', spans: [3, 6, 3], starts: [1, 4, 10], label: { en: 'Wide center', ar: 'وسط عريض' } },
  { id: '4', spans: [3, 3, 3, 3], starts: [1, 4, 7, 10], label: { en: 'Quarters', ar: 'أرباع' } },
  { id: '1', spans: [12], starts: [1], label: { en: 'Full', ar: 'كامل' } },
  { id: '1-2', spans: [4, 8], starts: [1, 5], label: { en: 'Sidebar', ar: 'جانبي' } },
]

export function presetToSpans(preset: string): number[] {
  const found = PRESETS.find((p) => p.id === preset)
  return found ? [...found.spans] : [12]
}

export function presetLayout(preset: string): { spans: number[]; starts: number[] } {
  const found = PRESETS.find((p) => p.id === preset)
  if (!found) return { spans: [12], starts: [1] }
  const spans = [...found.spans]
  const starts = found.starts ? [...found.starts] : spans.map((_, i) => {
    if (i === 0) return 1
    const prev = spans.slice(0, i).reduce((a, b) => a + b, 0) + 1
    return Math.min(prev, 12)
  })
  return { spans, starts }
}

function Cell({ className = '' }: { className?: string }) {
  return <div className={`rounded-[2px] bg-white/30 ${className}`} />
}

function PresetVisual({ id, selected }: { id: Preset; selected: boolean }) {
  const frame = `grid h-11 w-full gap-0.5 rounded-md p-1 ${selected ? 'bg-violet-500/25' : 'bg-white/10'}`

  switch (id) {
    case '2':
      return (
        <div className={`${frame} grid-cols-2`}>
          <Cell /><Cell />
        </div>
      )
    case '2-left':
      return (
        <div className={`${frame} grid-cols-3`}>
          <Cell /><Cell className="col-span-2" />
        </div>
      )
    case '2-right':
      return (
        <div className={`${frame} grid-cols-3`}>
          <Cell className="col-span-2" /><Cell />
        </div>
      )
    case '2-narrow-left':
      return (
        <div className={`${frame} grid-cols-12`}>
          <Cell className="col-span-5" /><Cell className="col-span-7" />
        </div>
      )
    case '2-narrow-right':
      return (
        <div className={`${frame} grid-cols-12`}>
          <Cell className="col-span-7" /><Cell className="col-span-5" />
        </div>
      )
    case '2x2':
      return (
        <div className={`${frame} grid-cols-2 grid-rows-2`}>
          <Cell /><Cell /><Cell /><Cell />
        </div>
      )
    case '3':
      return (
        <div className={`${frame} grid-cols-3`}>
          <Cell /><Cell /><Cell />
        </div>
      )
    case '3-center':
      return (
        <div className={`${frame} grid-cols-12`}>
          <Cell className="col-span-3" /><Cell className="col-span-6" /><Cell className="col-span-3" />
        </div>
      )
    case '4':
      return (
        <div className={`${frame} grid-cols-4`}>
          <Cell /><Cell /><Cell /><Cell />
        </div>
      )
    case '1':
      return (
        <div className={frame}>
          <Cell className="h-full min-h-[2.5rem]" />
        </div>
      )
    case '1-2':
      return (
        <div className={`${frame} grid-cols-3`}>
          <Cell /><Cell className="col-span-2" />
        </div>
      )
    default:
      return <div className={frame}><Cell className="h-full min-h-[2.5rem]" /></div>
  }
}

export default function LayoutPresetPicker({ value, onChange, locale, inspector = false }: Props) {
  const isAr = locale === 'ar'

  if (inspector) {
    return (
      <div className="space-y-2">
        <p className="text-xs font-medium text-white/70">
          {isAr ? 'تخطيط الأعمدة' : 'Column layout'}
        </p>
        <div className="builder-layout-presets -mx-1 flex gap-2 overflow-x-auto overscroll-contain px-1 pb-1">
          {PRESETS.map((preset) => (
            <button
              key={preset.id}
              type="button"
              onClick={() => onChange(preset.id)}
              className={`flex w-[4.5rem] shrink-0 flex-col gap-1 rounded-lg border p-1.5 transition-all hover:border-violet-400/60 ${
                value === preset.id
                  ? 'border-violet-400/80 bg-violet-500/15 ring-1 ring-violet-400/40'
                  : 'border-white/10 bg-[#12121f]'
              }`}
              title={preset.label[locale]}
            >
              <PresetVisual id={preset.id} selected={value === preset.id} />
            </button>
          ))}
        </div>
      </div>
    )
  }

  return (
    <div className="space-y-2">
      <p className="text-sm font-medium">{isAr ? 'تخطيط الأعمدة' : 'Column layout'}</p>
      <div className="flex flex-wrap gap-2">
        {PRESETS.map((preset) => (
          <button
            key={preset.id}
            type="button"
            onClick={() => onChange(preset.id)}
            className={`w-[4.5rem] rounded-lg border p-1.5 transition-all hover:border-[var(--brand)] ${
              value === preset.id ? 'border-[var(--brand)] bg-[var(--brand)]/5' : 'border-[var(--border)] bg-background'
            }`}
            title={preset.label[locale]}
          >
            <PresetVisual id={preset.id} selected={value === preset.id} />
            <span className="mt-1 block text-center text-[9px] text-muted-foreground">{preset.label[locale]}</span>
          </button>
        ))}
      </div>
    </div>
  )
}
