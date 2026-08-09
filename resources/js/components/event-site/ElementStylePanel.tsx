import BuilderColorField from './builder/BuilderColorField'
import CustomCssEditor from './builder/CustomCssEditor'
import SelectInput from '@/components/forms/SelectInput'

type Props = {
  style: Record<string, unknown>
  locale: 'en' | 'ar'
  kind: string
  onChange: (style: Record<string, unknown>) => void
}

export default function ElementStylePanel({ style, locale, kind, onChange }: Props) {
  const isAr = locale === 'ar'
  const update = (patch: Record<string, unknown>) => onChange({ ...style, ...patch })

  const textColor = typeof style.text_color === 'string' ? style.text_color : ''
  const headingColor = typeof style.heading_color === 'string' ? style.heading_color : ''
  const accentColor = typeof style.accent_color === 'string' ? style.accent_color : ''
  const bodySize = typeof style.body_size === 'string' ? style.body_size : ''
  const borderRadius = typeof style.border_radius === 'string' ? style.border_radius : ''
  const shadow = typeof style.shadow === 'string' ? style.shadow : ''
  const borderWidth = typeof style.border_width === 'string' ? style.border_width : ''
  const marginTop = typeof style.margin_top === 'string' ? style.margin_top : ''
  const marginBottom = typeof style.margin_bottom === 'string' ? style.margin_bottom : ''
  const marginLeft = typeof style.margin_left === 'string' ? style.margin_left : ''
  const marginRight = typeof style.margin_right === 'string' ? style.margin_right : ''
  const customClass = typeof style.custom_class === 'string' ? style.custom_class : ''
  const customCss = typeof style.custom_css === 'string' ? style.custom_css : ''

  const marginOptions = [
    { value: '', label: isAr ? 'افتراضي' : 'Default' },
    { value: 'none', label: isAr ? 'بدون' : 'None' },
    { value: 'xs', label: 'XS' },
    { value: 'sm', label: 'S' },
    { value: 'md', label: 'M' },
    { value: 'lg', label: 'L' },
    { value: 'xl', label: 'XL' },
    { value: '2xl', label: '2XL' },
  ]

  return (
    <details className="rounded-md border border-violet-500/20 bg-violet-500/5" open>
      <summary className="cursor-pointer px-3 py-2 text-xs font-semibold text-violet-300/90 hover:text-violet-200">
        {isAr ? '▸ تنسيق العنصر' : '▸ Element style & CSS'}
      </summary>
      <div className="space-y-3 border-t border-white/10 p-3">
        <div className="space-y-3">
          {(kind === 'heading' || kind === 'card') && (
            <BuilderColorField
              label={isAr ? 'لون العنوان' : 'Heading color'}
              value={headingColor}
              onChange={(v) => update({ heading_color: v })}
            />
          )}
          {(kind === 'text' || kind === 'card' || kind === 'heading' || kind === 'quote' || kind === 'list') && (
            <BuilderColorField
              label={isAr ? 'لون النص' : 'Text color'}
              value={textColor}
              onChange={(v) => update({ text_color: v })}
            />
          )}
          {(kind === 'button' || kind === 'card' || kind === 'divider') && (
            <BuilderColorField
              label={kind === 'divider' ? (isAr ? 'لون الخط' : 'Line color') : (isAr ? 'لون الخلفية' : 'Background')}
              value={accentColor}
              onChange={(v) => update({ accent_color: v })}
            />
          )}
        </div>
        <div className="grid grid-cols-2 gap-2">
          <SelectInput
            label={isAr ? 'هامش أعلى' : 'Margin top'}
            name="el_margin_top"
            value={marginTop}
            onChange={(e) => update({ margin_top: e.target.value })}
            options={marginOptions}
          />
          <SelectInput
            label={isAr ? 'هامش أسفل' : 'Margin bottom'}
            name="el_margin_bottom"
            value={marginBottom}
            onChange={(e) => update({ margin_bottom: e.target.value })}
            options={marginOptions}
          />
          <SelectInput
            label={isAr ? 'هامش يسار' : 'Margin left'}
            name="el_margin_left"
            value={marginLeft}
            onChange={(e) => update({ margin_left: e.target.value })}
            options={marginOptions}
          />
          <SelectInput
            label={isAr ? 'هامش يمين' : 'Margin right'}
            name="el_margin_right"
            value={marginRight}
            onChange={(e) => update({ margin_right: e.target.value })}
            options={marginOptions}
          />
        </div>
        <div className="grid gap-2">
          <SelectInput
            label={isAr ? 'حجم النص' : 'Text size'}
            name="el_body_size"
            value={bodySize}
            onChange={(e) => update({ body_size: e.target.value })}
            options={[
              { value: '', label: isAr ? 'افتراضي' : 'Default' },
              { value: 'xs', label: 'XS' },
              { value: 'sm', label: 'S' },
              { value: 'base', label: 'M' },
              { value: 'lg', label: 'L' },
              { value: 'xl', label: 'XL' },
            ]}
          />
          {(kind === 'card' || kind === 'button' || kind === 'image') && (
            <SelectInput
              label={isAr ? 'انحناء' : 'Radius'}
              name="el_radius"
              value={borderRadius}
              onChange={(e) => update({ border_radius: e.target.value })}
              options={[
                { value: '', label: isAr ? 'افتراضي' : 'Default' },
                { value: 'none', label: isAr ? 'بدون' : 'None' },
                { value: 'sm', label: 'S' },
                { value: 'md', label: 'M' },
                { value: 'lg', label: 'L' },
                { value: 'xl', label: 'XL' },
                { value: 'full', label: isAr ? 'كامل' : 'Full' },
              ]}
            />
          )}
          {kind === 'card' && (
            <>
              <SelectInput
                label={isAr ? 'الظل' : 'Shadow'}
                name="el_shadow"
                value={shadow}
                onChange={(e) => update({ shadow: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: 'none', label: isAr ? 'بدون' : 'None' },
                  { value: 'sm', label: 'S' },
                  { value: 'md', label: 'M' },
                  { value: 'lg', label: 'L' },
                ]}
              />
              <SelectInput
                label={isAr ? 'سمك الحد' : 'Border'}
                name="el_border"
                value={borderWidth}
                onChange={(e) => update({ border_width: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: '0', label: isAr ? 'بدون' : 'None' },
                  { value: '1', label: '1px' },
                  { value: '2', label: '2px' },
                ]}
              />
            </>
          )}
        </div>
        <CustomCssEditor
          locale={locale}
          customClass={customClass}
          customCss={customCss}
          onClassChange={(v) => update({ custom_class: v })}
          onCssChange={(v) => update({ custom_css: v })}
        />
      </div>
    </details>
  )
}
