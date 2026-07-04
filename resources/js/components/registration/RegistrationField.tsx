export type PublicFormField = {
  key: string
  type: 'text' | 'email' | 'phone' | 'select' | 'checkbox'
  label_en: string
  label_ar: string
  required?: boolean
}

export function RegistrationField({
  field,
  locale,
}: {
  field: PublicFormField
  locale: 'en' | 'ar'
}) {
  const label = locale === 'ar' ? field.label_ar : field.label_en

  return (
    <label className="registration-field">
      <span>{label}</span>
      <input
        name={field.key}
        type={field.type === 'phone' ? 'tel' : field.type}
        required={field.required}
        aria-required={field.required}
      />
    </label>
  )
}
