import { useState, useEffect, useCallback } from 'react'
import { apiFetch } from '@/lib/apiFetch'
import { RefreshCw, Inbox, X } from 'lucide-react'

type FormSubmission = {
  id: string
  block_id: string
  form_name: string
  page_title: string
  locale: string
  fields: Record<string, string>
  created_at: string
}

type Props = {
  eventId: string
  tenantId: string
  locale: 'en' | 'ar'
  onClose?: () => void
}

function normalizeFields(raw: unknown): Record<string, string> {
  if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return {}
  return Object.fromEntries(
    Object.entries(raw as Record<string, unknown>).map(([key, value]) => [
      key,
      value == null ? '' : String(value),
    ]),
  )
}

function normalizeSubmissions(payload: unknown): FormSubmission[] {
  const rows = Array.isArray(payload)
    ? payload
    : payload && typeof payload === 'object' && Array.isArray((payload as { submissions?: unknown }).submissions)
      ? (payload as { submissions: unknown[] }).submissions
      : []

  return rows
    .filter((item): item is Record<string, unknown> => typeof item === 'object' && item !== null)
    .map((row) => ({
      id: String(row.id ?? ''),
      block_id: String(row.block_id ?? ''),
      form_name: String(row.form_name ?? ''),
      page_title: String(row.page_title ?? ''),
      locale: String(row.locale ?? ''),
      fields: normalizeFields(row.fields ?? row.payload),
      created_at: String(row.created_at ?? ''),
    }))
    .filter((row) => row.id !== '')
}

export default function FormSubmissionsPanel({ eventId, tenantId, locale, onClose }: Props) {
  const [submissions, setSubmissions] = useState<FormSubmission[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const fetchSubmissions = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await apiFetch<unknown>(
        `/api/v1/tenant/events/${eventId}/site/form-submissions`,
        { tenantId },
      )
      setSubmissions(normalizeSubmissions(data))
    } catch (err) {
      setError(locale === 'ar' ? 'فشل تحميل الطلبات' : 'Failed to load submissions')
      console.error(err)
    } finally {
      setLoading(false)
    }
  }, [eventId, tenantId, locale])

  useEffect(() => {
    fetchSubmissions()
  }, [fetchSubmissions])

  const formatDate = (dateString: string) => {
    if (!dateString) return '—'
    const date = new Date(dateString)
    if (Number.isNaN(date.getTime())) return dateString
    return new Intl.DateTimeFormat(locale === 'ar' ? 'ar-SA' : 'en-US', {
      dateStyle: 'medium',
      timeStyle: 'short',
    }).format(date)
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-2">
        <h4 className="text-xs font-semibold uppercase tracking-wider text-white/70">
          {locale === 'ar' ? 'طلبات النماذج' : 'Form submissions'}
        </h4>
        <div className="flex items-center gap-1">
          <button
            type="button"
            onClick={fetchSubmissions}
            className="rounded p-1 text-white/50 hover:bg-white/10 hover:text-white"
            title={locale === 'ar' ? 'تحديث' : 'Refresh'}
          >
            <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
          </button>
          {onClose && (
            <button
              type="button"
              onClick={onClose}
              className="rounded p-1 text-white/50 hover:bg-white/10 hover:text-white"
              title={locale === 'ar' ? 'إغلاق' : 'Close'}
            >
              <X className="h-3.5 w-3.5" />
            </button>
          )}
        </div>
      </div>

      {loading && (
        <div className="flex items-center justify-center py-8">
          <RefreshCw className="h-5 w-5 animate-spin text-white/40" />
        </div>
      )}

      {!loading && error && (
        <div className="space-y-2 py-4 text-center">
          <p className="text-xs text-red-400">{error}</p>
          <button
            type="button"
            onClick={fetchSubmissions}
            className="rounded-md border border-white/15 px-2 py-1 text-[11px] text-white/70 hover:bg-white/5"
          >
            {locale === 'ar' ? 'إعادة المحاولة' : 'Retry'}
          </button>
        </div>
      )}

      {!loading && !error && submissions.length === 0 && (
        <div className="flex flex-col items-center justify-center py-8 text-center">
          <Inbox className="mb-2 h-8 w-8 text-white/25" />
          <p className="text-xs font-medium text-white/70">
            {locale === 'ar' ? 'لا توجد طلبات' : 'No submissions'}
          </p>
          <p className="mt-1 text-[11px] leading-relaxed text-white/40">
            {locale === 'ar'
              ? 'ستظهر هنا الطلبات من نماذج الموقع.'
              : 'Site form submissions will appear here.'}
          </p>
        </div>
      )}

      {!loading && !error && submissions.length > 0 && (
        <div className="space-y-2">
          <p className="text-[11px] text-white/45">
            {submissions.length} {locale === 'ar' ? 'طلب' : 'submission(s)'}
          </p>
          <div className="space-y-2">
            {submissions.map((submission) => {
              const fieldEntries = Object.entries(submission.fields)
              return (
                <div
                  key={submission.id}
                  className="rounded-lg border border-white/10 bg-white/[0.03] p-2.5"
                >
                  <div className="mb-1.5 flex items-start justify-between gap-2">
                    <div className="min-w-0">
                      <p className="truncate text-xs font-semibold text-white/85">
                        {submission.form_name || (locale === 'ar' ? 'نموذج' : 'Form')}
                      </p>
                      {submission.page_title && (
                        <p className="truncate text-[10px] text-white/40">{submission.page_title}</p>
                      )}
                    </div>
                    <span className="shrink-0 text-[10px] text-white/35">
                      {formatDate(submission.created_at)}
                    </span>
                  </div>
                  {fieldEntries.length > 0 ? (
                    <dl className="space-y-1">
                      {fieldEntries.map(([key, value]) => (
                        <div key={key} className="grid grid-cols-[72px_1fr] gap-1 text-[11px]">
                          <dt className="truncate text-white/40 capitalize">{key.replace(/_/g, ' ')}</dt>
                          <dd className="truncate text-white/75">{value || '—'}</dd>
                        </div>
                      ))}
                    </dl>
                  ) : (
                    <p className="text-[11px] text-white/35">—</p>
                  )}
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
