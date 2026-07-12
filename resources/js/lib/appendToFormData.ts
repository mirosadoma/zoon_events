export function appendToFormData(formData: FormData, key: string, value: unknown): void {
  if (value === null || value === undefined) {
    return
  }

  if (value instanceof File) {
    formData.append(key, value)

    return
  }

  if (Array.isArray(value)) {
    value.forEach((item, index) => {
      appendToFormData(formData, `${key}[${index}]`, item)
    })

    return
  }

  if (typeof value === 'object') {
    Object.entries(value as Record<string, unknown>).forEach(([childKey, childValue]) => {
      appendToFormData(formData, `${key}[${childKey}]`, childValue)
    })

    return
  }

  formData.append(key, String(value))
}
