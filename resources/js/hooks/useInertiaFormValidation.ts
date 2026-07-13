import { usePage } from '@inertiajs/react'
import { useCallback, useEffect, useMemo, useRef } from 'react'
import { useFormValidation, type UseFormValidationOptions } from '@/hooks/useFormValidation'
import { normalizeInertiaErrors } from '@/lib/formatValidationErrors'

export function useInertiaFormValidation(
  formErrors: Record<string, string | string[]>,
  options?: UseFormValidationOptions,
) {
  const pageErrors = usePage().props.errors as Record<string, string | string[]> | undefined
  const errors = useMemo(
    () => ({
      ...normalizeInertiaErrors(pageErrors),
      ...normalizeInertiaErrors(formErrors),
    }),
    [formErrors, pageErrors],
  )
  const validation = useFormValidation(options)
  const { applyErrors, clearValidation: clearValidationState } = validation
  const previousErrors = useRef('')

  const clearValidation = useCallback(() => {
    previousErrors.current = ''
    clearValidationState()
  }, [clearValidationState])

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

  return {
    ...validation,
    clearValidation,
  }
}
