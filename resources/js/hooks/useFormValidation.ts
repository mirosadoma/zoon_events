import { useCallback, useEffect, useMemo, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError } from '@/lib/apiFetch'
import { FORM_FIELD_INVALID_CLASS } from '@/lib/formFieldStyles'
import {
  firstValidationTarget,
  formFieldSelector,
  formatValidationFieldMessage,
  validationMessagesFromErrors,
  type FieldLabelMap,
} from '@/lib/formatValidationErrors'
import { translateValidationMessage } from '@/lib/translateValidationMessage'

type ValidationHintState = {
  messages: string[]
  targetSelector: string | null
}

export type UseFormValidationOptions = {
  title?: string
  titleKey?: string
  fieldLabels?: FieldLabelMap
  remapErrors?: (errors: Record<string, string>) => Record<string, string>
  selectorForKey?: (key: string) => string | null
  formatMessage?: (key: string, message: string, locale: 'en' | 'ar') => string
}

export function useFormValidation(options: UseFormValidationOptions = {}) {
  const { locale, t } = useLocale()
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({})
  const [validationHint, setValidationHint] = useState<ValidationHintState | null>(null)

  const clearValidation = useCallback(() => {
    setFieldErrors({})
    setValidationHint(null)
  }, [])

  const clearField = useCallback((key: string) => {
    setFieldErrors((current) => {
      const next = { ...current }

      for (const errorKey of Object.keys(next)) {
        if (errorKey === key || errorKey.startsWith(`${key}.`)) {
          delete next[errorKey]
        }
      }

      return next
    })
  }, [])

  const fieldError = useCallback((key: string) => fieldErrors[key], [fieldErrors])

  const applyErrors = useCallback((
    rawErrors: Record<string, string>,
    applyOptions?: Partial<UseFormValidationOptions>,
  ): boolean => {
    if (Object.keys(rawErrors).length === 0) {
      return false
    }

    const merged = { ...options, ...applyOptions }
    const remapped = merged.remapErrors ? merged.remapErrors(rawErrors) : rawErrors
    const selectorForKey = merged.selectorForKey ?? formFieldSelector
    const formatMessage = merged.formatMessage
      ?? ((key, message, currentLocale) => formatValidationFieldMessage(key, message, currentLocale, merged.fieldLabels))

    const translated = Object.fromEntries(
      Object.entries(remapped).map(([key, message]) => [
        key,
        translateValidationMessage(message, locale),
      ]),
    )

    setFieldErrors(translated)
    setValidationHint({
      messages: validationMessagesFromErrors(translated, locale, formatMessage),
      targetSelector: firstValidationTarget(translated, selectorForKey),
    })

    return true
  }, [locale, options])

  const applyApiError = useCallback((
    error: unknown,
    applyOptions?: Partial<UseFormValidationOptions>,
  ): boolean => {
    if (!(error instanceof ApiFetchError) || Object.keys(error.errors).length === 0) {
      return false
    }

    return applyErrors(error.errors, applyOptions)
  }, [applyErrors])

  const closeHint = useCallback(() => {
    setValidationHint(null)
  }, [])

  useEffect(() => {
    const marked = new Set<Element>()

    for (const key of Object.keys(fieldErrors)) {
      const selector = (options.selectorForKey ?? formFieldSelector)(key)
      if (!selector) {
        continue
      }

      document.querySelectorAll(selector).forEach((element) => {
        element.classList.add(FORM_FIELD_INVALID_CLASS)
        marked.add(element)
      })
    }

    return () => {
      marked.forEach((element) => {
        element.classList.remove(FORM_FIELD_INVALID_CLASS)
      })
    }
  }, [fieldErrors, options.selectorForKey])

  const hintProps = useMemo(() => ({
    open: validationHint !== null,
    onClose: closeHint,
    title: options.title ?? (options.titleKey ? t(options.titleKey) : t('checkTheFields')),
    messages: validationHint?.messages ?? [],
    targetSelector: validationHint?.targetSelector,
  }), [validationHint, closeHint, options.title, options.titleKey, t])

  return {
    fieldErrors,
    fieldError,
    validationHint,
    hintProps,
    clearValidation,
    clearField,
    applyErrors,
    applyApiError,
    closeHint,
  }
}
