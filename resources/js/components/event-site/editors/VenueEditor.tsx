import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
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

export default function VenueEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const description = typeof content.description === 'string' ? content.description : ''
  const showMap = options.show_map === true

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم الموقع' : 'Venue Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'الموقع' : 'Venue'}
      />

      <TextareaInput
        label={locale === 'ar' ? 'الوصف' : 'Description'}
        name="description"
        value={description}
        onChange={(e) => onContentChange({ description: e.target.value })}
        placeholder={locale === 'ar' ? 'اسم وعنوان المكان' : 'Venue name and address'}
        rows={4}
      />

      <CheckboxInput
        label={locale === 'ar' ? 'إظهار الخريطة' : 'Show map'}
        id="show_map"
        checked={showMap}
        onChange={(e) => onOptionsChange({ show_map: e.target.checked })}
      />
    </div>
  )
}
