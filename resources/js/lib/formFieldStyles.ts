import { clsx } from 'clsx'

export const FORM_FIELD_INVALID_CLASS = 'form-field-invalid'

export function controlClassName(error?: string, className?: string): string {
  return clsx(className, error && FORM_FIELD_INVALID_CLASS)
}

export function wrapperClassName(error?: string, className?: string): string {
  return clsx(className, error && FORM_FIELD_INVALID_CLASS)
}
