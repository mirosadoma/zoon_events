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

export default function RegisterCtaEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const buttonText = typeof content.button_text === 'string' ? content.button_text : ''
  const style = typeof options.style === 'string' ? options.style : 'prominent'
  const showCountdown = options.show_countdown === true

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">
        {locale === 'ar' ? 'دعوة للتسجيل' : 'Register Call to Action'}
      </h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'سجّل الآن' : 'Register Now'}
      />

      <TextInput
        label={locale === 'ar' ? 'نص الزر' : 'Button Text'}
        name="button_text"
        value={buttonText}
        onChange={(e) => onContentChange({ button_text: e.target.value })}
        placeholder={locale === 'ar' ? 'تسجيل' : 'Register'}
      />

      <SelectInput
        label="Style"
        name="style"
        value={style}
        onChange={(e) => onOptionsChange({ style: e.target.value })}
        options={[
          { value: 'prominent', label: 'Prominent (large)' },
          { value: 'inline', label: 'Inline (subtle)' },
          { value: 'floating', label: 'Floating button' },
        ]}
      />

      <CheckboxInput
        label={locale === 'ar' ? 'إظهار العد التنازلي' : 'Show countdown'}
        id="show_countdown"
        checked={showCountdown}
        onChange={(e) => onOptionsChange({ show_countdown: e.target.checked })}
      />
    </div>
  )
}
