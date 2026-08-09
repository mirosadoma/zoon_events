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

export default function AboutEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const body = typeof content.body === 'string' ? content.body : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'centered'

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم حول' : 'About Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'عن الحدث' : 'About the Event'}
      />

      <TextareaInput
        label={locale === 'ar' ? 'المحتوى' : 'Content'}
        name="body"
        value={body}
        onChange={(e) => onContentChange({ body: e.target.value })}
        placeholder={locale === 'ar' ? 'وصف الحدث...' : 'Describe the event...'}
        rows={6}
      />

      <SelectInput
        label="Layout"
        name="layout"
        value={layout}
        onChange={(e) => onOptionsChange({ layout: e.target.value })}
        options={[
          { value: 'centered', label: locale === 'ar' ? 'وسط' : 'Centered' },
          { value: 'left', label: locale === 'ar' ? 'محاذاة البداية' : 'Start aligned' },
          { value: 'two-column', label: locale === 'ar' ? 'عمودين' : 'Two Column' },
        ]}
      />
    </div>
  )
}
