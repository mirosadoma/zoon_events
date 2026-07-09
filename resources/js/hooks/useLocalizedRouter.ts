import { router } from '@inertiajs/react'
import type { RequestPayload, VisitOptions } from '@inertiajs/core'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'

export function useLocalizedRouter() {
  const { locale } = useLocale()

  return {
    visit: (path: string, options?: VisitOptions) => router.visit(localizedPath(locale, path), options),
    get: (path: string, data?: RequestPayload, options?: VisitOptions) =>
      router.get(localizedPath(locale, path), data, options),
    post: (path: string, data?: RequestPayload, options?: VisitOptions) =>
      router.post(localizedPath(locale, path), data, options),
    patch: (path: string, data?: RequestPayload, options?: VisitOptions) =>
      router.patch(localizedPath(locale, path), data, options),
    delete: (path: string, options?: VisitOptions) => router.delete(localizedPath(locale, path), options),
  }
}
