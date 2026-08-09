import BackgroundEditor from './BackgroundEditor'
import SelectInput from '@/components/forms/SelectInput'
import BuilderColorField from './builder/BuilderColorField'
import CustomCssEditor from './builder/CustomCssEditor'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'

type Props = {
  options: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  blockType: string
  onChange: (updates: Record<string, unknown>) => void
}

function Section({ title, children, defaultOpen = true }: { title: string; children: React.ReactNode; defaultOpen?: boolean }) {
  return (
    <details className="group border-b border-white/10" open={defaultOpen}>
      <summary className="cursor-pointer list-none px-4 py-3 text-[11px] font-semibold uppercase tracking-wider text-white/50 hover:text-white/70 [&::-webkit-details-marker]:hidden">
        <span className="flex items-center justify-between">
          {title}
          <span className="text-white/30 transition-transform group-open:rotate-180">▾</span>
        </span>
      </summary>
      <div className="space-y-3 px-4 pb-4">{children}</div>
    </details>
  )
}

function FieldGrid({ children, cols = 1 }: { children: React.ReactNode; cols?: number }) {
  return (
    <div className={cols === 2 ? 'builder-field-grid-2 grid grid-cols-1 gap-3' : 'space-y-3'}>
      {children}
    </div>
  )
}

export default function StylePanel({ options, locale, eventId, tenantId, blockType, onChange }: Props) {
  const isAr = locale === 'ar'
  const background = (options.background as SiteBackground) || { type: 'none' }

  const update = (patch: Record<string, unknown>) => onChange(patch)

  const headingSize = typeof options.heading_size === 'string' ? options.heading_size : ''
  const bodySize = typeof options.body_size === 'string' ? options.body_size : ''
  const headingWeight = typeof options.heading_weight === 'string' ? options.heading_weight : ''
  const paddingY = typeof options.padding_y === 'string' ? options.padding_y : ''
  const paddingX = typeof options.padding_x === 'string' ? options.padding_x : ''
  const marginTop = typeof options.margin_top === 'string' ? options.margin_top : ''
  const marginBottom = typeof options.margin_bottom === 'string' ? options.margin_bottom : ''
  const borderRadius = typeof options.border_radius === 'string' ? options.border_radius : ''
  const borderWidth = typeof options.border_width === 'string' ? options.border_width : ''
  const borderStyle = typeof options.border_style === 'string' ? options.border_style : 'solid'
  const shadow = typeof options.shadow === 'string' ? options.shadow : ''
  const maxWidth = typeof options.max_width === 'string' ? options.max_width : ''
  const width = typeof options.width === 'string' ? options.width : 'boxed'
  const contentAlign = typeof options.content_align === 'string' ? options.content_align : 'start'
  const opacity = typeof options.opacity === 'number' ? options.opacity : 100
  const customClass = typeof options.custom_class === 'string' ? options.custom_class : ''
  const customCss = typeof options.custom_css === 'string' ? options.custom_css : ''

  const sizeOpts = [
    { value: '', label: isAr ? 'افتراضي' : 'Default' },
    { value: 'sm', label: 'S' },
    { value: 'md', label: 'M' },
    { value: 'lg', label: 'L' },
    { value: 'xl', label: 'XL' },
    { value: '2xl', label: '2XL' },
  ]

  const spacingOpts = [
    { value: '', label: isAr ? 'افتراضي' : 'Default' },
    { value: 'none', label: isAr ? 'بدون' : 'None' },
    { value: 'sm', label: 'S' },
    { value: 'md', label: 'M' },
    { value: 'lg', label: 'L' },
    { value: 'xl', label: 'XL' },
  ]

  return (
    <div className="builder-inspector divide-y divide-white/10">
      <Section title={isAr ? 'الألوان' : 'Colors'}>
        <FieldGrid>
          {blockType === 'footer' ? (
            <>
              <BuilderColorField
                label={isAr ? 'لون الشعار النصي' : 'Tagline color'}
                value={typeof options.tagline_color === 'string' ? options.tagline_color : ''}
                onChange={(v) => update({ tagline_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون حقوق النشر' : 'Copyright color'}
                value={typeof options.copyright_color === 'string' ? options.copyright_color : ''}
                onChange={(v) => update({ copyright_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون عناوين الأعمدة' : 'Column title color'}
                value={typeof options.column_title_color === 'string' ? options.column_title_color : ''}
                onChange={(v) => update({ column_title_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون الروابط' : 'Link color'}
                value={typeof options.link_color === 'string' ? options.link_color : ''}
                onChange={(v) => update({ link_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون الأيقونات الاجتماعية' : 'Social icons color'}
                value={typeof options.social_color === 'string' ? options.social_color : ''}
                onChange={(v) => update({ social_color: v })}
              />
            </>
          ) : (
            <>
              <BuilderColorField
                label={isAr ? 'لون العناوين' : 'Heading color'}
                value={typeof options.heading_color === 'string' ? options.heading_color : ''}
                onChange={(v) => update({ heading_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون النص' : 'Text color'}
                value={typeof options.text_color === 'string' ? options.text_color : ''}
                onChange={(v) => update({ text_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون التمييز / الأزرار' : 'Accent / buttons'}
                value={typeof options.accent_color === 'string' ? options.accent_color : ''}
                onChange={(v) => update({ accent_color: v })}
              />
              <BuilderColorField
                label={isAr ? 'لون الروابط' : 'Link color'}
                value={typeof options.link_color === 'string' ? options.link_color : ''}
                onChange={(v) => update({ link_color: v })}
              />
            </>
          )}
        </FieldGrid>
      </Section>

      <Section title={isAr ? 'الخطوط' : 'Typography'}>
        <FieldGrid>
          {blockType === 'footer' ? (
            <>
              <SelectInput
                label={isAr ? 'حجم الشعار النصي' : 'Tagline size'}
                name="tagline_size"
                value={typeof options.tagline_size === 'string' ? options.tagline_size : ''}
                onChange={(e) => update({ tagline_size: e.target.value })}
                options={sizeOpts}
              />
              <SelectInput
                label={isAr ? 'حجم حقوق النشر' : 'Copyright size'}
                name="copyright_size"
                value={typeof options.copyright_size === 'string' ? options.copyright_size : ''}
                onChange={(e) => update({ copyright_size: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: 'xs', label: 'XS' },
                  { value: 'sm', label: 'S' },
                  { value: 'base', label: 'M' },
                  { value: 'lg', label: 'L' },
                  { value: 'xl', label: 'XL' },
                ]}
              />
              <SelectInput
                label={isAr ? 'حجم عناوين الأعمدة' : 'Column title size'}
                name="column_title_size"
                value={typeof options.column_title_size === 'string' ? options.column_title_size : ''}
                onChange={(e) => update({ column_title_size: e.target.value })}
                options={sizeOpts}
              />
              <SelectInput
                label={isAr ? 'حجم الروابط' : 'Link size'}
                name="footer_link_size"
                value={typeof options.footer_link_size === 'string' ? options.footer_link_size : ''}
                onChange={(e) => update({ footer_link_size: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: 'xs', label: 'XS' },
                  { value: 'sm', label: 'S' },
                  { value: 'base', label: 'M' },
                  { value: 'lg', label: 'L' },
                  { value: 'xl', label: 'XL' },
                ]}
              />
              <SelectInput
                label={isAr ? 'حجم الأيقونات الاجتماعية' : 'Social icon size'}
                name="social_size"
                value={typeof options.social_size === 'string' ? options.social_size : ''}
                onChange={(e) => update({ social_size: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: 'sm', label: 'S' },
                  { value: 'md', label: 'M' },
                  { value: 'lg', label: 'L' },
                  { value: 'xl', label: 'XL' },
                ]}
              />
            </>
          ) : (
            <>
              <SelectInput
                label={isAr ? 'حجم العنوان' : 'Heading size'}
                name="heading_size"
                value={headingSize}
                onChange={(e) => update({ heading_size: e.target.value })}
                options={sizeOpts}
              />
              <SelectInput
                label={isAr ? 'حجم النص' : 'Body size'}
                name="body_size"
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
              <SelectInput
                label={isAr ? 'سمك العنوان' : 'Heading weight'}
                name="heading_weight"
                value={headingWeight}
                onChange={(e) => update({ heading_weight: e.target.value })}
                options={[
                  { value: '', label: isAr ? 'افتراضي' : 'Default' },
                  { value: 'normal', label: isAr ? 'عادي' : 'Normal' },
                  { value: 'medium', label: isAr ? 'متوسط' : 'Medium' },
                  { value: 'semibold', label: isAr ? 'شبه عريض' : 'Semibold' },
                  { value: 'bold', label: isAr ? 'عريض' : 'Bold' },
                  { value: 'extrabold', label: isAr ? 'أعرض' : 'Extra bold' },
                ]}
              />
            </>
          )}
        </FieldGrid>
      </Section>

      <Section title={isAr ? 'المسافات' : 'Spacing'}>
        <FieldGrid>
          <SelectInput
            label={isAr ? 'حشو عمودي' : 'Padding Y'}
            name="padding_y"
            value={paddingY}
            onChange={(e) => update({ padding_y: e.target.value })}
            options={spacingOpts}
          />
          <SelectInput
            label={isAr ? 'حشو جانبي' : 'Padding X'}
            name="padding_x"
            value={paddingX}
            onChange={(e) => update({ padding_x: e.target.value })}
            options={spacingOpts}
          />
          <SelectInput
            label={isAr ? 'هامش أعلى' : 'Margin top'}
            name="margin_top"
            value={marginTop}
            onChange={(e) => update({ margin_top: e.target.value })}
            options={[
              { value: '', label: isAr ? 'افتراضي' : 'Default' },
              { value: 'sm', label: 'S' },
              { value: 'md', label: 'M' },
              { value: 'lg', label: 'L' },
            ]}
          />
          <SelectInput
            label={isAr ? 'هامش أسفل' : 'Margin bottom'}
            name="margin_bottom"
            value={marginBottom}
            onChange={(e) => update({ margin_bottom: e.target.value })}
            options={[
              { value: '', label: isAr ? 'افتراضي' : 'Default' },
              { value: 'sm', label: 'S' },
              { value: 'md', label: 'M' },
              { value: 'lg', label: 'L' },
            ]}
          />
        </FieldGrid>
        <label className="grid gap-1.5 pt-1">
          <span className="text-xs font-medium text-white/60">
            {isAr ? 'الشفافية' : 'Opacity'} ({opacity}%)
          </span>
          <input
            type="range"
            min={20}
            max={100}
            value={opacity}
            onChange={(e) => update({ opacity: Number(e.target.value) })}
            className="w-full accent-violet-500"
          />
        </label>
      </Section>

      <Section title={isAr ? 'الحدود والظل' : 'Border & shadow'} defaultOpen={false}>
        <FieldGrid>
          <SelectInput
            label={isAr ? 'انحناء الحواف' : 'Border radius'}
            name="border_radius"
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
          <SelectInput
            label={isAr ? 'سمك الحد' : 'Border width'}
            name="border_width"
            value={borderWidth}
            onChange={(e) => update({ border_width: e.target.value })}
            options={[
              { value: '', label: isAr ? 'افتراضي' : 'Default' },
              { value: '0', label: isAr ? 'بدون' : 'None' },
              { value: '1', label: '1px' },
              { value: '2', label: '2px' },
              { value: '4', label: '4px' },
            ]}
          />
          <SelectInput
            label={isAr ? 'نوع الحد' : 'Border style'}
            name="border_style"
            value={borderStyle}
            onChange={(e) => update({ border_style: e.target.value })}
            options={[
              { value: 'solid', label: isAr ? 'صلب' : 'Solid' },
              { value: 'dashed', label: isAr ? 'متقطع' : 'Dashed' },
              { value: 'dotted', label: isAr ? 'نقطي' : 'Dotted' },
            ]}
          />
          <SelectInput
            label={isAr ? 'الظل' : 'Shadow'}
            name="shadow"
            value={shadow}
            onChange={(e) => update({ shadow: e.target.value })}
            options={[
              { value: '', label: isAr ? 'افتراضي' : 'Default' },
              { value: 'none', label: isAr ? 'بدون' : 'None' },
              { value: 'sm', label: 'S' },
              { value: 'md', label: 'M' },
              { value: 'lg', label: 'L' },
              { value: 'xl', label: 'XL' },
            ]}
          />
        </FieldGrid>
        <BuilderColorField
          label={isAr ? 'لون الحد' : 'Border color'}
          value={typeof options.border_color === 'string' ? options.border_color : ''}
          onChange={(v) => update({ border_color: v })}
        />
      </Section>

      <Section title={isAr ? 'التخطيط' : 'Layout'}>
        <FieldGrid>
          <SelectInput
            label={isAr ? 'عرض القسم' : 'Section width'}
            name="width"
            value={width}
            onChange={(e) => update({ width: e.target.value })}
            options={[
              { value: 'full', label: isAr ? 'كامل' : 'Full' },
              { value: 'boxed', label: isAr ? 'محاط' : 'Boxed' },
              { value: 'narrow', label: isAr ? 'ضيق' : 'Narrow' },
            ]}
          />
          <SelectInput
            label={isAr ? 'محاذاة المحتوى' : 'Content align'}
            name="content_align"
            value={contentAlign}
            onChange={(e) => update({ content_align: e.target.value })}
            options={[
              { value: 'start', label: isAr ? 'بداية' : 'Start' },
              { value: 'center', label: isAr ? 'وسط' : 'Center' },
              { value: 'end', label: isAr ? 'نهاية' : 'End' },
            ]}
          />
          {blockType !== 'header' && blockType !== 'footer' && (
            <SelectInput
              label={isAr ? 'أقصى عرض للمحتوى' : 'Max content width'}
              name="max_width"
              value={maxWidth}
              onChange={(e) => update({ max_width: e.target.value })}
              options={[
                { value: '', label: isAr ? 'افتراضي (6xl)' : 'Default (6xl)' },
                { value: 'sm', label: 'SM' },
                { value: 'md', label: 'MD' },
                { value: 'lg', label: 'LG' },
                { value: 'xl', label: 'XL' },
                { value: '2xl', label: '2XL' },
                { value: '3xl', label: '3XL' },
                { value: '4xl', label: '4XL' },
                { value: '5xl', label: '5XL' },
                { value: '6xl', label: '6XL' },
                { value: '7xl', label: '7XL' },
                { value: 'full', label: isAr ? 'كامل' : 'Full' },
              ]}
            />
          )}
        </FieldGrid>
      </Section>

      <Section title={isAr ? 'الخلفية' : 'Background'} defaultOpen={false}>
        <BackgroundEditor
          value={background}
          onChange={(bg) => update({ background: bg })}
          locale={locale}
          tenantId={tenantId}
          eventId={eventId}
          label={isAr ? 'خلفية القسم' : 'Section background'}
        />
      </Section>

      <Section title={isAr ? 'متقدم · CSS' : 'Advanced · CSS'} defaultOpen={false}>
        <CustomCssEditor
          locale={locale}
          customClass={customClass}
          customCss={customCss}
          onClassChange={(v) => update({ custom_class: v })}
          onCssChange={(v) => update({ custom_css: v })}
        />
      </Section>
    </div>
  )
}
