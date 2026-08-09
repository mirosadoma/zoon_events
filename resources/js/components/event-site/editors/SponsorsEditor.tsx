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

export default function SponsorsEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'grid'
  const showNames = options.show_names === true
  const columns = typeof options.columns === 'number' ? options.columns : 4

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم الرعاة' : 'Sponsors Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'الرعاة' : 'Sponsors'}
      />

      <p className="text-sm text-muted-foreground">
        {locale === 'ar'
          ? 'قم بتحميل شعارات الرعاة في المعرض.'
          : 'Upload sponsor logos in the gallery.'}
      </p>

      <div className="grid grid-cols-2 gap-4">
        <SelectInput
          label="Layout"
          name="layout"
          value={layout}
          onChange={(e) => onOptionsChange({ layout: e.target.value })}
          options={[
            { value: 'grid', label: 'Grid' },
            { value: 'row', label: 'Row' },
            { value: 'carousel', label: 'Carousel' },
          ]}
        />

        <SelectInput
          label="Columns"
          name="columns"
          value={String(columns)}
          onChange={(e) => onOptionsChange({ columns: parseInt(e.target.value, 10) })}
          options={[
            { value: '2', label: '2 columns' },
            { value: '3', label: '3 columns' },
            { value: '4', label: '4 columns' },
            { value: '5', label: '5 columns' },
            { value: '6', label: '6 columns' },
          ]}
        />
      </div>

      <CheckboxInput
        label={locale === 'ar' ? 'إظهار الأسماء' : 'Show names'}
        id="show_names"
        checked={showNames}
        onChange={(e) => onOptionsChange({ show_names: e.target.checked })}
      />
    </div>
  )
}
