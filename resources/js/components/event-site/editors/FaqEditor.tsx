import { useCallback } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import CheckboxInput from '@/components/forms/CheckboxInput'

type FaqItem = {
  question: string
  answer: string
}

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

export default function FaqEditor({
  content,
  options,
  locale,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const items: FaqItem[] = Array.isArray(content.items) ? content.items : []
  const collapsible = options.collapsible === true

  const addItem = useCallback(() => {
    onContentChange({
      items: [...items, { question: '', answer: '' }],
    })
  }, [items, onContentChange])

  const updateItem = useCallback(
    (index: number, updates: Partial<FaqItem>) => {
      const newItems = items.map((item, i) =>
        i === index ? { ...item, ...updates } : item,
      )
      onContentChange({ items: newItems })
    },
    [items, onContentChange],
  )

  const removeItem = useCallback(
    (index: number) => {
      onContentChange({ items: items.filter((_, i) => i !== index) })
    },
    [items, onContentChange],
  )

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">{locale === 'ar' ? 'الأسئلة الشائعة' : 'FAQ Section'}</h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'الأسئلة الشائعة' : 'Frequently Asked Questions'}
      />

      <CheckboxInput
        label={locale === 'ar' ? 'قابل للطي' : 'Collapsible'}
        id="collapsible"
        checked={collapsible}
        onChange={(e) => onOptionsChange({ collapsible: e.target.checked })}
      />

      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <span className="text-sm font-medium">
            {locale === 'ar' ? 'الأسئلة' : 'Questions'}
          </span>
          <button
            type="button"
            onClick={addItem}
            className="flex items-center gap-1 text-sm text-primary hover:underline"
          >
            <Plus className="w-4 h-4" />
            {locale === 'ar' ? 'إضافة سؤال' : 'Add question'}
          </button>
        </div>

        {items.map((item, index) => (
          <div key={index} className="p-3 bg-background rounded-lg border space-y-2">
            <div className="flex items-start gap-2">
              <div className="flex-1 space-y-2">
                <TextInput
                  label={locale === 'ar' ? 'السؤال' : 'Question'}
                  name={`question-${index}`}
                  value={item.question}
                  onChange={(e) => updateItem(index, { question: e.target.value })}
                />
                <TextareaInput
                  label={locale === 'ar' ? 'الإجابة' : 'Answer'}
                  name={`answer-${index}`}
                  value={item.answer}
                  onChange={(e) => updateItem(index, { answer: e.target.value })}
                  rows={3}
                />
              </div>
              <button
                type="button"
                onClick={() => removeItem(index)}
                className="p-1 rounded hover:bg-red-100 dark:hover:bg-red-900/30"
              >
                <Trash2 className="w-4 h-4 text-red-500" />
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}
