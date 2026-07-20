import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { formFieldProps } from '@/lib/formatValidationErrors'

export type FieldOptionRow = {
  id: string
  label_en: string
  label_ar: string
}

type Props = {
  options: FieldOptionRow[]
  onChange: (options: FieldOptionRow[]) => void
  fieldKeyPrefix?: string
  fieldError?: (suffix: string) => string | undefined
}

function newOptionId(): string {
  return crypto.randomUUID()
}

export function defaultFieldOptions(): FieldOptionRow[] {
  return [{ id: newOptionId(), label_en: 'Option 1', label_ar: 'خيار 1' }]
}

export default function FieldOptionsRepeater({ options, onChange, fieldKeyPrefix, fieldError }: Props) {
  const { locale, t } = useLocale()

  function updateOption(index: number, patch: Partial<FieldOptionRow>) {
    onChange(
      options.map((option, i) => (i === index ? { ...option, ...patch } : option)),
    )
  }

  function addOption() {
    const index = options.length
    onChange([
      ...options,
      {
        id: newOptionId(),
        label_en: `Option ${index + 1}`,
        label_ar: `خيار ${index + 1}`,
      },
    ])
  }

  function removeOption(index: number) {
    if (options.length <= 1) {
      return
    }
    onChange(options.filter((_, i) => i !== index))
  }

  return (
    <div className="space-y-3 rounded-lg border border-slate-200 p-3 dark:border-slate-700">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-semibold">
          {t('fieldOptionsTitle')}
        </h3>
        <button type="button" className="button-secondary" onClick={addOption}>
          {t('fieldOptionsAdd')}
        </button>
      </div>
      <ul className="space-y-3">
        {options.map((option, index) => (
          <li key={option.id} className="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
            <TextInput
              label={t('fieldOptionsLabelEn')}
              name={`option_label_en_${index}`}
              value={option.label_en}
              onChange={(event) => updateOption(index, { label_en: event.target.value })}
              error={fieldKeyPrefix ? fieldError?.(`options.${index}.label_en`) : undefined}
              {...(fieldKeyPrefix ? formFieldProps(`${fieldKeyPrefix}.options.${index}.label_en`) : {})}
            />
            <TextInput
              label={t('fieldOptionsLabelAr')}
              name={`option_label_ar_${index}`}
              value={option.label_ar}
              onChange={(event) => updateOption(index, { label_ar: event.target.value })}
              error={fieldKeyPrefix ? fieldError?.(`options.${index}.label_ar`) : undefined}
              {...(fieldKeyPrefix ? formFieldProps(`${fieldKeyPrefix}.options.${index}.label_ar`) : {})}
            />
            <div className="flex items-end">
              <button
                type="button"
                className="button-secondary"
                onClick={() => removeOption(index)}
                disabled={options.length <= 1}
              >
                {t('fieldOptionsRemove')}
              </button>
            </div>
          </li>
        ))}
      </ul>
    </div>
  )
}
