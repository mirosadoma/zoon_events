import { randomUuid } from '@/lib/uuid'
import type { AppLocale } from '@/lib/localePath'

let redirectingToLogin = false

function redirectToLogin(): void {
  if (typeof window === 'undefined' || redirectingToLogin) {
    return
  }

  const { pathname, search, hash } = window.location
  const localeMatch = pathname.match(/^\/(en|ar)(?=\/|$)/)
  const locale: AppLocale = localeMatch ? (localeMatch[1] as AppLocale) : 'en'
  const loginPath = `/${locale}/login`

  if (pathname === loginPath) {
    return
  }

  redirectingToLogin = true
  const intended = encodeURIComponent(`${pathname}${search}${hash}`)
  window.location.assign(`${loginPath}?redirect=${intended}`)
}

type ApiFetchOptions = Omit<RequestInit, 'body'> & {
  tenantId?: string
  idempotency?: boolean
  skipAuthRedirect?: boolean
  body?: Record<string, unknown> | BodyInit | null
}

function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : null
}

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE'])

async function ensureCsrfToken(method: string): Promise<string | null> {
  const existing = readCsrfToken()

  if (existing || !MUTATING_METHODS.has(method.toUpperCase())) {
    return existing
  }

  await fetch('/sanctum/csrf-cookie', { credentials: 'include' })

  return readCsrfToken()
}

export class ApiFetchError extends Error {
  constructor(
    message: string,
    public readonly status: number,
    public readonly code?: string,
    public readonly errors: Record<string, string> = {},
    public readonly missing: string[] = [],
  ) {
    super(message)
    this.name = 'ApiFetchError'
  }
}

function mapValidationErrors(body: Record<string, unknown>): Record<string, string> {
  const errors = body.errors

  if (typeof errors !== 'object' || errors === null) {
    return {}
  }

  return Object.fromEntries(
    Object.entries(errors as Record<string, string[] | string>).map(([key, value]) => [
      key,
      Array.isArray(value) ? String(value[0] ?? '') : String(value),
    ]),
  )
}

export async function apiFetch<T = unknown>(
  url: string,
  options: ApiFetchOptions = {},
): Promise<T> {
  const {
    tenantId,
    idempotency = false,
    skipAuthRedirect = false,
    headers: initHeaders,
    body,
    ...rest
  } = options
  const headers = new Headers(initHeaders)

  headers.set('Accept', 'application/json')
  const isFormData = body instanceof FormData
  const resolvedBody = body && typeof body === 'object' && !isFormData && !(body instanceof URLSearchParams) && !(body instanceof Blob)
    ? JSON.stringify(body)
    : body

  if (!headers.has('Content-Type') && resolvedBody && !isFormData) {
    headers.set('Content-Type', 'application/json')
  }
  if (tenantId) {
    headers.set('X-Tenant-ID', tenantId)
  }
  if (idempotency) {
    headers.set('Idempotency-Key', randomUuid())
  }

  let method = (rest.method ?? 'GET').toUpperCase()
  let requestBody: BodyInit | undefined = resolvedBody ?? undefined

  // PHP only parses multipart fields for POST; spoof PATCH/PUT via _method.
  if (isFormData && (method === 'PATCH' || method === 'PUT')) {
    body.append('_method', method)
    method = 'POST'
    requestBody = body
  }

  const csrfToken = await ensureCsrfToken(method)

  if (csrfToken) {
    headers.set('X-XSRF-TOKEN', csrfToken)
  }

  const response = await fetch(url, {
    credentials: 'include',
    ...rest,
    method,
    body: requestBody,
    headers,
  })

  const payload = await response.json().catch(() => ({})) as Record<string, unknown>

  if (!response.ok) {
    if (response.status === 401 && !skipAuthRedirect) {
      redirectToLogin()
    }
    const detail = String(payload.detail ?? payload.message ?? payload.title ?? payload.code ?? 'Request failed')
    const missing = Array.isArray(payload.missing)
      ? payload.missing.map((item) => String(item)).filter(Boolean)
      : []
    throw new ApiFetchError(
      detail,
      response.status,
      typeof payload.code === 'string' ? payload.code : undefined,
      mapValidationErrors(payload),
      missing,
    )
  }

  return (payload.data ?? payload) as T
}
