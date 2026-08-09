import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  onContentChange: (updates: Record<string, unknown>) => void
  onOptionsChange: (updates: Record<string, unknown>) => void
  onRefsChange: (updates: Record<string, unknown>) => void
}

export default function HeroEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const subtitle = typeof content.subtitle === 'string' ? content.subtitle : ''
  const backgroundStyle = typeof options.background_style === 'string' ? options.background_style : 'gradient'
  const textAlignment = typeof options.text_alignment === 'string' ? options.text_alignment : 'center'

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">Hero Section</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'أدخل عنوان الحدث' : 'Enter event title'}
      />

      <TextareaInput
        label={locale === 'ar' ? 'العنوان الفرعي' : 'Subtitle'}
        name="subtitle"
        value={subtitle}
        onChange={(e) => onContentChange({ subtitle: e.target.value })}
        placeholder={locale === 'ar' ? 'التاريخ والموقع' : 'Date and location'}
        rows={2}
      />

      <div className="grid grid-cols-2 gap-4">
        <SelectInput
          label="Background Style"
          name="background_style"
          value={backgroundStyle}
          onChange={(e) => onOptionsChange({ background_style: e.target.value })}
          options={[
            { value: 'gradient', label: 'Gradient' },
            { value: 'image', label: 'Image' },
            { value: 'solid', label: 'Solid Color' },
          ]}
        />

        <SelectInput
          label={locale === 'ar' ? 'محاذاة النص' : 'Text Alignment'}
          name="text_alignment"
          value={
            textAlignment === 'left' ? 'start' : textAlignment === 'right' ? 'end' : textAlignment
          }
          onChange={(e) => onOptionsChange({ text_alignment: e.target.value })}
          options={[
            { value: 'start', label: locale === 'ar' ? 'البداية' : 'Start' },
            { value: 'center', label: locale === 'ar' ? 'وسط' : 'Center' },
            { value: 'end', label: locale === 'ar' ? 'النهاية' : 'End' },
          ]}
        />
      </div>
    </div>
  )
}
