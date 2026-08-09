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

export default function GalleryEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'grid'
  const lightbox = options.lightbox === true
  const columns = typeof options.columns === 'number' ? options.columns : 3

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم المعرض' : 'Gallery Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'المعرض' : 'Gallery'}
      />

      <p className="text-sm text-muted-foreground">
        {locale === 'ar'
          ? 'سيتم استخدام صور الحدث تلقائياً.'
          : 'Event images will be used automatically.'}
      </p>

      <div className="grid grid-cols-2 gap-4">
        <SelectInput
          label="Layout"
          name="layout"
          value={layout}
          onChange={(e) => onOptionsChange({ layout: e.target.value })}
          options={[
            { value: 'grid', label: 'Grid' },
            { value: 'masonry', label: 'Masonry' },
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
          ]}
        />
      </div>

      <CheckboxInput
        label={locale === 'ar' ? 'عرض بالحجم الكامل عند النقر' : 'Lightbox on click'}
        id="lightbox"
        checked={lightbox}
        onChange={(e) => onOptionsChange({ lightbox: e.target.checked })}
      />
    </div>
  )
}
