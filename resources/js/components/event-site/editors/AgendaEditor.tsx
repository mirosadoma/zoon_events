import TextInput from '@/components/forms/TextInput'
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

export default function AgendaEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const showSpeakers = options.show_speakers === true
  const groupByDate = options.group_by_date === true

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'قسم جدول الأعمال' : 'Agenda Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'جدول الأعمال' : 'Agenda'}
      />

      <p className="text-sm text-muted-foreground">
        {locale === 'ar'
          ? 'يُحمَّل جدول الأعمال تلقائياً من بيانات الحدث. استخدم تبويب التنسيق لتغيير الشكل.'
          : 'Agenda loads automatically from event data. Use the Style tab to change its look.'}
      </p>

      <div className="space-y-2">
        <CheckboxInput
          label={locale === 'ar' ? 'إظهار المتحدثين' : 'Show speakers'}
          id="show_speakers"
          checked={showSpeakers}
          onChange={(e) => onOptionsChange({ show_speakers: e.target.checked })}
        />

        <CheckboxInput
          label={locale === 'ar' ? 'تجميع حسب التاريخ' : 'Group by date'}
          id="group_by_date"
          checked={groupByDate}
          onChange={(e) => onOptionsChange({ group_by_date: e.target.checked })}
        />
      </div>
    </div>
  )
}
