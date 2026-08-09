import { useCallback } from 'react'
import {
  DndContext,
  closestCenter,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
  type DragEndEvent,
} from '@dnd-kit/core'
import {
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
  arrayMove,
} from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { GripVertical, Plus, Trash2 } from 'lucide-react'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import BackgroundEditor from '../BackgroundEditor'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'

type FormField = {
  id: string
  name: string
  label: string
  type: 'text' | 'email' | 'phone' | 'textarea' | 'select'
  required: boolean
  options?: string[]
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

function SortableField({
  field,
  locale,
  onUpdate,
  onRemove,
}: {
  field: FormField
  locale: 'en' | 'ar'
  onUpdate: (updates: Partial<FormField>) => void
  onRemove: () => void
}) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: field.id,
  })

  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.5 : 1,
  }

  const typeOptions = [
    { value: 'text', label: locale === 'ar' ? 'نص' : 'Text' },
    { value: 'email', label: locale === 'ar' ? 'بريد إلكتروني' : 'Email' },
    { value: 'phone', label: locale === 'ar' ? 'هاتف' : 'Phone' },
    { value: 'textarea', label: locale === 'ar' ? 'نص طويل' : 'Textarea' },
    { value: 'select', label: locale === 'ar' ? 'قائمة اختيار' : 'Select' },
  ]

  return (
    <div ref={setNodeRef} style={style} className="border border-[var(--border)] rounded-lg p-3 bg-background space-y-3">
      <div className="flex items-center gap-2">
        <button type="button" {...attributes} {...listeners} className="cursor-grab text-muted-foreground">
          <GripVertical className="w-4 h-4" />
        </button>
        <span className="flex-1 text-sm font-medium truncate">
          {field.label || field.name || (locale === 'ar' ? 'حقل جديد' : 'New Field')}
        </span>
        <button type="button" onClick={onRemove} className="p-1 hover:bg-red-100 dark:hover:bg-red-900/30 rounded">
          <Trash2 className="w-4 h-4 text-red-500" />
        </button>
      </div>

      <div className="grid grid-cols-2 gap-2">
        <TextInput
          label={locale === 'ar' ? 'الاسم البرمجي' : 'Field Name'}
          name="field_name"
          value={field.name}
          onChange={(e) => onUpdate({ name: e.target.value.replace(/\s/g, '_').toLowerCase() })}
          placeholder="field_name"
        />
        <TextInput
          label={locale === 'ar' ? 'العنوان' : 'Label'}
          name="field_label"
          value={field.label}
          onChange={(e) => onUpdate({ label: e.target.value })}
          placeholder={locale === 'ar' ? 'عنوان الحقل' : 'Field Label'}
        />
      </div>

      <div className="grid grid-cols-2 gap-2 items-end">
        <SelectInput
          label={locale === 'ar' ? 'النوع' : 'Type'}
          name="field_type"
          value={field.type}
          onChange={(e) => onUpdate({ type: e.target.value as FormField['type'] })}
          options={typeOptions}
        />
        <CheckboxInput
          label={locale === 'ar' ? 'مطلوب' : 'Required'}
          id={`${field.id}_required`}
          checked={field.required}
          onChange={(e) => onUpdate({ required: e.target.checked })}
        />
      </div>

      {field.type === 'select' && (
        <TextareaInput
          label={locale === 'ar' ? 'الخيارات (سطر لكل خيار)' : 'Options (one per line)'}
          name="field_options"
          value={(field.options || []).join('\n')}
          onChange={(e) => onUpdate({ options: e.target.value.split('\n').filter(Boolean) })}
          rows={3}
          placeholder={locale === 'ar' ? 'خيار 1\nخيار 2\nخيار 3' : 'Option 1\nOption 2\nOption 3'}
        />
      )}
    </div>
  )
}

export default function FormEditor({
  content,
  options,
  locale,
  eventId,
  tenantId,
  onContentChange,
  onOptionsChange,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const description = typeof content.description === 'string' ? content.description : ''
  const submitLabel = typeof content.submit_label === 'string' ? content.submit_label : ''
  const successMessage = typeof content.success_message === 'string' ? content.success_message : ''
  const fields: FormField[] = Array.isArray(content.fields) ? content.fields : []
  const background = (options.background as SiteBackground) || { type: 'none' }

  const sensors = useSensors(
    useSensor(PointerSensor),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  )

  const handleDragEnd = useCallback(
    (event: DragEndEvent) => {
      const { active, over } = event
      if (over && active.id !== over.id) {
        const oldIndex = fields.findIndex((f) => f.id === active.id)
        const newIndex = fields.findIndex((f) => f.id === over.id)
        onContentChange({ fields: arrayMove(fields, oldIndex, newIndex) })
      }
    },
    [fields, onContentChange],
  )

  const addField = useCallback(() => {
    const newField: FormField = {
      id: `fld_${Date.now()}`,
      name: '',
      label: '',
      type: 'text',
      required: false,
    }
    onContentChange({ fields: [...fields, newField] })
  }, [fields, onContentChange])

  const updateField = useCallback(
    (id: string, updates: Partial<FormField>) => {
      onContentChange({
        fields: fields.map((f) => (f.id === id ? { ...f, ...updates } : f)),
      })
    },
    [fields, onContentChange],
  )

  const removeField = useCallback(
    (id: string) => {
      onContentChange({ fields: fields.filter((f) => f.id !== id) })
    },
    [fields, onContentChange],
  )

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">
        {locale === 'ar' ? 'قسم النموذج' : 'Form Section'}
      </h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'اتصل بنا' : 'Contact Us'}
      />

      <TextareaInput
        label={locale === 'ar' ? 'الوصف' : 'Description'}
        name="description"
        value={description}
        onChange={(e) => onContentChange({ description: e.target.value })}
        rows={2}
      />

      <div className="grid grid-cols-2 gap-3">
        <TextInput
          label={locale === 'ar' ? 'نص زر الإرسال' : 'Submit Button'}
          name="submit_label"
          value={submitLabel}
          onChange={(e) => onContentChange({ submit_label: e.target.value })}
          placeholder={locale === 'ar' ? 'إرسال' : 'Submit'}
        />
        <TextInput
          label={locale === 'ar' ? 'رسالة النجاح' : 'Success Message'}
          name="success_message"
          value={successMessage}
          onChange={(e) => onContentChange({ success_message: e.target.value })}
          placeholder={locale === 'ar' ? 'شكراً لتواصلك!' : 'Thank you!'}
        />
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <p className="text-sm font-medium">
            {locale === 'ar' ? 'الحقول' : 'Fields'} ({fields.length})
          </p>
          <button type="button" onClick={addField} className="button-secondary text-xs py-1.5">
            <Plus className="w-4 h-4 me-1 inline" />
            {locale === 'ar' ? 'إضافة حقل' : 'Add Field'}
          </button>
        </div>

        <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
          <SortableContext items={fields.map((f) => f.id)} strategy={verticalListSortingStrategy}>
            <div className="space-y-2">
              {fields.map((field) => (
                <SortableField
                  key={field.id}
                  field={field}
                  locale={locale}
                  onUpdate={(updates) => updateField(field.id, updates)}
                  onRemove={() => removeField(field.id)}
                />
              ))}
            </div>
          </SortableContext>
        </DndContext>

        {fields.length === 0 && (
          <p className="text-sm text-muted-foreground text-center py-4">
            {locale === 'ar' ? 'لا توجد حقول. أضف حقلاً للبدء.' : 'No fields. Add a field to get started.'}
          </p>
        )}
      </div>

      <BackgroundEditor
        value={background}
        onChange={(bg) => onOptionsChange({ background: bg })}
        locale={locale}
        tenantId={tenantId}
        eventId={eventId}
      />
    </div>
  )
}
