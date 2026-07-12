import { useEffect, useRef } from 'react'
import { useFormValidation, type UseFormValidationOptions } from '@/hooks/useFormValidation'

export function useInertiaFormValidation(
  errors: Record<string, string>,
  options?: UseFormValidationOptions,
) {
  const validation = useFormValidation(options)
  const { applyErrors } = validation
  const previousErrors = useRef('')

  useEffect(() => {
    const serialized = JSON.stringify(errors)
    if (serialized === previousErrors.current) {
      return
    }

    previousErrors.current = serialized

    if (Object.keys(errors).length > 0) {
      applyErrors(errors)
    }
  }, [errors, applyErrors])

  return validation
}
