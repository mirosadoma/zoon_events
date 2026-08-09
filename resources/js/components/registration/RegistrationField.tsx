import { useState, type CSSProperties } from 'react'
import { ValidationError } from '@/components/forms/TextInput'
import { FORM_FIELD_INVALID_CLASS } from '@/lib/formFieldStyles'
import { linkedTextAnswerKey } from '@/lib/linkedTextAnswerKey'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type FieldOption = {
  value: string
  label_en: string
  label_ar: string
  linked_text?: boolean
}

export type PublicFormField = {
  key: string
  type: string
  label_en: string
  label_ar: string
  required?: boolean
  options?: FieldOption[]
  content?: string
  width?: 'full' | 'half' | 'third' | string
  choice_style?: string | null
  choice_color?: string | null
}

const FIELD_CLASS = 'registration-field'
const SELECT_PANEL_CLASS = 'registration-field registration-field-select-panel'

function optionLabel(option: FieldOption, locale: 'en' | 'ar'): string {
  return locale === 'ar' ? option.label_ar : option.label_en
}

function isSelectType(type: string): boolean {
  return type === 'select'
}

function resolveChoiceStyle(type: string, style?: string | null): string {
  if (type === 'checkbox') {
    if (style === 'toggle' || style === 'pill' || style === 'card') return style
    return 'square'
  }

  if (type === 'radio') {
    if (style === 'toggle' || style === 'pill' || style === 'card' || style === 'button') return style
    return 'circle'
  }

  return 'square'
}

function choiceFieldStyle(color?: string | null): CSSProperties | undefined {
  if (!color || !/^#[0-9A-Fa-f]{6}$/.test(color)) return undefined
  return { ['--choice-accent' as string]: color }
}

function fieldPlaceholder(field: PublicFormField, messages: typeof en): string | undefined {
  if (field.type === 'phone' || field.key === 'phone') {
    return messages.publicRegistrationPlaceholderPhone
  }
  if (field.key === 'full_name' || field.key === 'name') {
    return messages.publicRegistrationPlaceholderFullName
  }
  if (field.type === 'email' || field.key === 'email') {
    return messages.publicRegistrationPlaceholderEmail
  }
  return undefined
}

function LinkedTextInput({
  fieldKey,
  option,
  locale,
  messages,
  disabled,
  error,
}: {
  fieldKey: string
  option: FieldOption
  locale: 'en' | 'ar'
  messages: typeof en
  disabled?: boolean
  error?: string
}) {
  const name = linkedTextAnswerKey(fieldKey, option.value)
  const inputId = `registration-field-${name}`

  return (
    <div className={`registration-choice-linked-text${error ? ` ${FORM_FIELD_INVALID_CLASS}` : ''}`}>
      <label htmlFor={inputId} className="sr-only">
        {messages.publicRegistrationLinkedTextLabel.replace(':option', optionLabel(option, locale))}
      </label>
      <input
        id={inputId}
        type="text"
        name={name}
        required
        disabled={disabled}
        placeholder={messages.publicRegistrationLinkedTextPlaceholder}
        data-form-field={name}
        aria-invalid={error ? 'true' : undefined}
        className={error ? FORM_FIELD_INVALID_CLASS : undefined}
      />
      {error ? <ValidationError message={error} /> : null}
    </div>
  )
}

export function RegistrationField({
  field,
  locale,
  disabled = false,
  readOnly = false,
  defaultValue,
  value,
  error,
  linkedTextErrors,
  'data-form-field': dataFormField,
}: {
  field: PublicFormField
  locale: 'en' | 'ar'
  disabled?: boolean
  readOnly?: boolean
  defaultValue?: string
  /** When set, the input is controlled (used to lock invite emails). */
  value?: string
  error?: string
  linkedTextErrors?: Record<string, string>
  'data-form-field'?: string
}) {
  const label = locale === 'ar' ? field.label_ar : field.label_en
  const messages = locale === 'ar' ? ar : en
  const options = field.options ?? []
  const locked = disabled || readOnly
  const showRequiredMark = Boolean(field.required)
  const required = Boolean(field.required && !readOnly)
  const fieldError = error ? <ValidationError message={error} /> : null
  const invalidClass = error ? FORM_FIELD_INVALID_CLASS : ''
  const [radioValue, setRadioValue] = useState('')
  const [checkboxValues, setCheckboxValues] = useState<string[]>([])

  if (field.type === 'radio') {
    if (options.length === 0) {
      return null
    }

    const style = resolveChoiceStyle('radio', field.choice_style)

    return (
      <fieldset
        className={`${FIELD_CLASS} registration-field-choice registration-choice--${style} ${invalidClass}`}
        style={choiceFieldStyle(field.choice_color)}
        data-form-field={dataFormField}
      >
        <legend>
          {label}
          {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
        </legend>
        <div className={`registration-choice-options registration-choice-options--${style}`}>
          {options.map((option) => {
            const selected = radioValue === option.value
            const linkedKey = linkedTextAnswerKey(field.key, option.value)

            return (
              <div key={option.value} className="registration-choice-option-wrap">
                <label className={`registration-choice-option registration-choice-option--${style}`}>
                  <input
                    type="radio"
                    name={field.key}
                    value={option.value}
                    required={required}
                    aria-required={required}
                    disabled={disabled}
                    checked={selected}
                    onChange={() => setRadioValue(option.value)}
                  />
                  <span className="registration-choice-control" aria-hidden="true" />
                  <span className="registration-choice-label">{optionLabel(option, locale)}</span>
                </label>
                {option.linked_text && selected ? (
                  <LinkedTextInput
                    fieldKey={field.key}
                    option={option}
                    locale={locale}
                    messages={messages}
                    disabled={disabled}
                    error={linkedTextErrors?.[linkedKey]}
                  />
                ) : null}
              </div>
            )
          })}
        </div>
        {fieldError}
      </fieldset>
    )
  }

  if (isSelectType(field.type)) {
    if (options.length === 0) {
      return null
    }

    return (
      <label className={`${SELECT_PANEL_CLASS} ${invalidClass}`}>
        <span>
          {label}
          {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
        </span>
        <select
          name={field.key}
          className={`registration-select-control ${invalidClass}`}
          required={required}
          aria-required={required}
          disabled={disabled}
          data-form-field={dataFormField}
          aria-invalid={error ? 'true' : undefined}
        >
          <option value="">{messages.selectPlaceholder}</option>
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {optionLabel(option, locale)}
            </option>
          ))}
        </select>
        {fieldError}
      </label>
    )
  }

  if (field.type === 'multi_select') {
    if (options.length === 0) {
      return null
    }

    return (
      <label className={`${SELECT_PANEL_CLASS} ${invalidClass}`}>
        <span>
          {label}
          {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
        </span>
        <select
          name={field.key}
          className={`registration-select-control ${invalidClass}`}
          multiple
          required={required}
          aria-required={required}
          disabled={disabled}
          data-form-field={dataFormField}
          aria-invalid={error ? 'true' : undefined}
        >
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {optionLabel(option, locale)}
            </option>
          ))}
        </select>
        {fieldError}
      </label>
    )
  }

  if (field.type === 'checkbox') {
    if (options.length === 0) {
      return null
    }

    const style = resolveChoiceStyle('checkbox', field.choice_style)

    return (
      <fieldset
        className={`${FIELD_CLASS} registration-field-choice registration-choice--${style} ${invalidClass}`}
        style={choiceFieldStyle(field.choice_color)}
        data-form-field={dataFormField}
      >
        <legend>
          {label}
          {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
        </legend>
        <div className={`registration-choice-options registration-choice-options--${style}`}>
          {options.map((option) => {
            const selected = checkboxValues.includes(option.value)
            const linkedKey = linkedTextAnswerKey(field.key, option.value)

            return (
              <div key={option.value} className="registration-choice-option-wrap">
                <label className={`registration-choice-option registration-choice-option--${style}`}>
                  <input
                    type="checkbox"
                    name={field.key}
                    value={option.value}
                    disabled={disabled}
                    checked={selected}
                    onChange={(event) => {
                      setCheckboxValues((prev) => (
                        event.target.checked
                          ? [...prev, option.value]
                          : prev.filter((item) => item !== option.value)
                      ))
                    }}
                  />
                  <span className="registration-choice-control" aria-hidden="true" />
                  <span className="registration-choice-label">{optionLabel(option, locale)}</span>
                </label>
                {option.linked_text && selected ? (
                  <LinkedTextInput
                    fieldKey={field.key}
                    option={option}
                    locale={locale}
                    messages={messages}
                    disabled={disabled}
                    error={linkedTextErrors?.[linkedKey]}
                  />
                ) : null}
              </div>
            )
          })}
        </div>
        {fieldError}
      </fieldset>
    )
  }

  if (field.type === 'consent') {
    return (
      <label className={`${FIELD_CLASS} registration-field-checkbox ${invalidClass}`}>
        <input
          type="checkbox"
          name={field.key}
          value="true"
          required={required}
          aria-required={required}
          disabled={disabled}
          data-form-field={dataFormField}
          aria-invalid={error ? 'true' : undefined}
        />
        <span>
          {label}
          {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
        </span>
        {fieldError}
      </label>
    )
  }

  const inputType = field.type === 'phone'
    ? 'tel'
    : field.type === 'number'
      ? 'number'
      : field.type === 'date'
        ? 'date'
        : field.type === 'email'
          ? 'email'
          : 'text'

  const inputId = `registration-field-${field.key}`

  return (
    <div className={`${FIELD_CLASS} ${invalidClass}`}>
      <div className="registration-field-label">
        <label htmlFor={inputId}>{label}</label>
        {showRequiredMark ? <span className="registration-field-required" aria-hidden="true">*</span> : null}
      </div>
      <input
        id={inputId}
        name={field.key}
        type={inputType}
        inputMode={field.type === 'phone' ? 'numeric' : undefined}
        maxLength={field.type === 'phone' ? 10 : undefined}
        pattern={field.type === 'phone' ? '05[0-9]{8}' : undefined}
        placeholder={fieldPlaceholder(field, messages)}
        className={invalidClass}
        required={required}
        aria-required={required || showRequiredMark}
        disabled={disabled}
        readOnly={locked}
        {...(value !== undefined
          ? { value, onChange: () => undefined }
          : { defaultValue })}
        data-form-field={dataFormField}
        aria-invalid={error ? 'true' : undefined}
      />
      {fieldError}
    </div>
  )
}
