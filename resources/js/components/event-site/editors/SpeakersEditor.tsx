import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'

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

export default function SpeakersEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'grid'
  const showBio = options.show_bio === true

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم المتحدثين' : 'Speakers Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'المتحدثون' : 'Speakers'}
      />

      <p className="text-sm text-muted-foreground">
        {locale === 'ar'
          ? 'سيتم استخراج المتحدثين من جدول الأعمال تلقائياً.'
          : 'Speakers will be automatically extracted from the agenda.'}
      </p>

      <SelectInput
        label="Layout"
        name="layout"
        value={layout}
        onChange={(e) => onOptionsChange({ layout: e.target.value })}
        options={[
          { value: 'grid', label: 'Grid' },
          { value: 'list', label: 'List' },
          { value: 'carousel', label: 'Carousel' },
        ]}
      />

      <CheckboxInput
        label={locale === 'ar' ? 'إظهار السيرة الذاتية' : 'Show bio'}
        id="show_bio"
        checked={showBio}
        onChange={(e) => onOptionsChange({ show_bio: e.target.checked })}
      />
    </div>
  )
}
