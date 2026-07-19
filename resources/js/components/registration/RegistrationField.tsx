import { ValidationError } from '@/components/forms/TextInput'
import { FORM_FIELD_INVALID_CLASS } from '@/lib/formFieldStyles'
import en from '@/locales/en'
import ar from '@/locales/ar'

export type FieldOption = {
  value: string
  label_en: string
  label_ar: string
}

export type PublicFormField = {
  key: string
  type: string
  label_en: string
  label_ar: string
  required?: boolean
  options?: FieldOption[]
}

const FIELD_CLASS = 'registration-field'
const SELECT_PANEL_CLASS = 'registration-field registration-field-select-panel'

function optionLabel(option: FieldOption, locale: 'en' | 'ar'): string {
  return locale === 'ar' ? option.label_ar : option.label_en
}

function isSelectType(type: string): boolean {
  return type === 'select'
}

export function RegistrationField({
  field,
  locale,
  disabled = false,
  readOnly = false,
  defaultValue,
  value,
  error,
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
  'data-form-field'?: string
}) {
  const label = locale === 'ar' ? field.label_ar : field.label_en
  const messages = locale === 'ar' ? ar : en
  const options = field.options ?? []
  const locked = disabled || readOnly
  const required = Boolean(field.required && !locked)
  const fieldError = error ? <ValidationError message={error} /> : null
  const invalidClass = error ? FORM_FIELD_INVALID_CLASS : ''

  if (field.type === 'radio') {
    if (options.length === 0) {
      return null
    }

    return (
      <fieldset className={`${FIELD_CLASS} registration-field-choice ${invalidClass}`} data-form-field={dataFormField}>
        <legend>
          {label}
          {required ? <span className="registration-field-required">*</span> : null}
        </legend>
        <div className="registration-choice-options">
          {options.map((option) => (
            <label key={option.value} className="registration-choice-option">
              <input
                type="radio"
                name={field.key}
                value={option.value}
                required={required}
                aria-required={required}
                disabled={disabled}
              />
              <span>{optionLabel(option, locale)}</span>
            </label>
          ))}
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
          {required ? <span className="registration-field-required">*</span> : null}
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
          {required ? <span className="registration-field-required">*</span> : null}
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

    return (
      <fieldset className={`${FIELD_CLASS} registration-field-choice ${invalidClass}`} data-form-field={dataFormField}>
        <legend>
          {label}
          {required ? <span className="registration-field-required">*</span> : null}
        </legend>
        <div className="registration-choice-options">
          {options.map((option) => (
            <label key={option.value} className="registration-choice-option">
              <input type="checkbox" name={field.key} value={option.value} disabled={disabled} />
              <span>{optionLabel(option, locale)}</span>
            </label>
          ))}
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
          {required ? <span className="registration-field-required">*</span> : null}
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

  return (
    <label className={`${FIELD_CLASS} ${invalidClass}`}>
      <span>
        {label}
        {required ? <span className="registration-field-required">*</span> : null}
      </span>
      <input
        name={field.key}
        type={inputType}
        className={invalidClass}
        required={required}
        aria-required={required}
        disabled={disabled}
        readOnly={locked}
        {...(value !== undefined
          ? { value, onChange: () => undefined }
          : { defaultValue })}
        data-form-field={dataFormField}
        aria-invalid={error ? 'true' : undefined}
      />
      {fieldError}
    </label>
  )
}
