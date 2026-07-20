import { useEffect, useRef, useState } from 'react'
import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

export type BadgeFieldOverrides = {
  job_title?: string
  custom_text?: string
  company?: string
}

type PreviewResponse = {
  print_html: string
  fields: Record<string, string | null>
  editable_fields: string[]
}

type Props = {
  open: boolean
  eventId?: string
  tenantId?: string
  attendeeId: string
  credentialId: string
  attendeeName?: string | null
  mode?: 'print' | 'reprint'
  loading?: boolean
  loadPreview?: (overrides: BadgeFieldOverrides) => Promise<PreviewResponse>
  onCancel: () => void
  onConfirm: (result: { overrides: BadgeFieldOverrides; reason?: string }) => void
}

const FIELD_LABEL_KEYS: Record<string, string> = {
  job_title: 'badgePrintPreviewJobTitle',
  custom_text: 'badgePrintPreviewCustomText',
  company: 'badgePrintPreviewCompany',
}

const FIELD_GUIDE_COLORS: Record<string, string> = {
  job_title: '#f59e0b',
  custom_text: '#10b981',
  company: '#8b5cf6',
}

export default function BadgePrintPreviewModal({
  open,
  eventId,
  tenantId,
  attendeeId,
  credentialId,
  attendeeName,
  mode = 'print',
  loading = false,
  loadPreview,
  onCancel,
  onConfirm,
}: Props) {
  const { t } = useLocale()
  const [previewHtml, setPreviewHtml] = useState<string | null>(null)
  const [editableFields, setEditableFields] = useState<string[]>([])
  const [overrides, setOverrides] = useState<BadgeFieldOverrides>({})
  const [reason, setReason] = useState('')
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const overridesRef = useRef(overrides)
  const requestIdRef = useRef(0)
  const loadPreviewRef = useRef(loadPreview)
  loadPreviewRef.current = loadPreview

  overridesRef.current = overrides
  const isReprint = mode === 'reprint'

  async function fetchPreview(fieldOverrides: BadgeFieldOverrides): Promise<PreviewResponse> {
    if (loadPreviewRef.current) {
      return loadPreviewRef.current(fieldOverrides)
    }

    if (!eventId || !tenantId) {
      throw new Error('preview_unavailable')
    }

    return apiFetch<PreviewResponse>(
      `/api/v1/tenant/events/${eventId}/badge-print-jobs/preview`,
      {
        method: 'POST',
        tenantId,
        body: {
          attendee_id: attendeeId,
          credential_id: credentialId,
          field_overrides: fieldOverrides,
        },
      },
    )
  }

  useEffect(() => {
    if (!open) {
      setPreviewHtml(null)
      setEditableFields([])
      setOverrides({})
      setReason('')
      setError(null)
      setLoadingPreview(false)
      return
    }

    let cancelled = false
    const requestId = ++requestIdRef.current

    async function loadInitialPreview() {
      setLoadingPreview(true)
      setError(null)
      try {
        const data = await fetchPreview({})
        if (cancelled || requestId !== requestIdRef.current) return
        setPreviewHtml(data.print_html)
        setEditableFields(data.editable_fields ?? [])
        setOverrides((current) => {
          const seeded: BadgeFieldOverrides = { ...current }
          for (const key of data.editable_fields ?? []) {
            if (seeded[key as keyof BadgeFieldOverrides] === undefined) {
              seeded[key as keyof BadgeFieldOverrides] = data.fields?.[key] ?? ''
            }
          }
          return seeded
        })
      } catch (caught) {
        if (cancelled || requestId !== requestIdRef.current) return
        setError(caught instanceof ApiFetchError ? caught.message : t('attendeeDetailBadgeFailed'))
        setPreviewHtml(null)
      } finally {
        if (!cancelled && requestId === requestIdRef.current) {
          setLoadingPreview(false)
        }
      }
    }

    void loadInitialPreview()

    return () => {
      cancelled = true
    }
  }, [open, eventId, tenantId, attendeeId, credentialId])

  if (!open) return null

  async function refreshPreview() {
    const requestId = ++requestIdRef.current
    setLoadingPreview(true)
    setError(null)
    try {
      const data = await fetchPreview(overridesRef.current)
      if (requestId !== requestIdRef.current) return
      setPreviewHtml(data.print_html)
      setEditableFields(data.editable_fields ?? [])
    } catch (caught) {
      if (requestId !== requestIdRef.current) return
      setError(caught instanceof ApiFetchError ? caught.message : t('attendeeDetailBadgeFailed'))
    } finally {
      if (requestId === requestIdRef.current) {
        setLoadingPreview(false)
      }
    }
  }

  const canConfirm = Boolean(previewHtml)
    && !loading
    && !loadingPreview
    && (!isReprint || reason.trim() !== '')

  return (
    <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="badge-print-preview-title">
      <div className="ta-card flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden shadow-xl">
        <div className="border-b border-slate-200 px-5 py-4 dark:border-slate-700">
          <h2 id="badge-print-preview-title" className="text-lg font-semibold">
            {isReprint ? t('badgePrintReprintTitle') : t('badgePrintPreviewTitle')}
          </h2>
          <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
            {attendeeName
              ? t('badgePrintPreviewFor').replace(':name', attendeeName)
              : (isReprint ? t('badgePrintReprintMessage') : t('badgePrintPreviewMessage'))}
          </p>
          <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
            {t('badgePrintPreviewFieldGuideHint')}
          </p>
        </div>

        <div className="grid min-h-0 flex-1 gap-4 overflow-auto p-5 lg:grid-cols-[260px_minmax(0,1fr)]">
          <div className="space-y-3">
            {editableFields.length === 0 ? (
              <p className="text-sm text-slate-500">{t('badgePrintPreviewNoEditable')}</p>
            ) : (
              editableFields.map((field) => (
                <div key={field} className="space-y-1">
                  <div className="flex items-center gap-2">
                    <span
                      className="inline-block h-2.5 w-2.5 shrink-0 rounded-full"
                      style={{ backgroundColor: FIELD_GUIDE_COLORS[field] ?? '#64748b' }}
                      aria-hidden
                    />
                    <span className="text-xs font-medium text-slate-500">
                      {t('badgePrintPreviewShowsOnBadge')}
                    </span>
                  </div>
                  <TextInput
                    label={t((FIELD_LABEL_KEYS[field] ?? field) as 'badgePrintPreviewJobTitle')}
                    name={field}
                    value={overrides[field as keyof BadgeFieldOverrides] ?? ''}
                    onChange={(event) => {
                      const value = event.target.value
                      setOverrides((current) => ({ ...current, [field]: value }))
                    }}
                  />
                </div>
              ))
            )}
            {editableFields.length > 0 ? (
              <button
                type="button"
                className="button-secondary w-full"
                onClick={() => void refreshPreview()}
                disabled={loadingPreview || loading}
              >
                {loadingPreview ? t('badgePrintPreviewLoading') : t('badgePrintPreviewRefresh')}
              </button>
            ) : null}
            {isReprint ? (
              <TextInput
                label={t('badgePrintReason')}
                name="reprint_reason"
                value={reason}
                required
                onChange={(event) => setReason(event.target.value)}
                placeholder={t('badgePrintReprintMessage')}
              />
            ) : null}
            {error ? <p className="text-sm text-red-600">{error}</p> : null}
          </div>

          <div className="relative min-h-[320px] overflow-auto rounded-lg border border-slate-200 bg-slate-100 dark:border-slate-700 dark:bg-slate-900">
            {previewHtml ? (
              <iframe
                title={t('badgePrintPreviewTitle')}
                srcDoc={previewHtml}
                className="h-[60vh] w-full bg-white"
              />
            ) : loadingPreview ? (
              <div className="grid h-full min-h-[320px] place-items-center text-sm text-slate-500">
                {t('badgePrintPreviewLoading')}
              </div>
            ) : (
              <div className="grid h-full min-h-[320px] place-items-center text-sm text-slate-500">
                {t('badgePrintPreviewUnavailable')}
              </div>
            )}
            {loadingPreview && previewHtml ? (
              <div className="absolute inset-0 grid place-items-center bg-white/70 text-sm text-slate-600 dark:bg-slate-950/60 dark:text-slate-300">
                {t('badgePrintPreviewLoading')}
              </div>
            ) : null}
          </div>
        </div>

        <div className="flex justify-end gap-3 border-t border-slate-200 px-5 py-4 dark:border-slate-700">
          <button type="button" className="button-secondary" onClick={onCancel} disabled={loading}>
            {t('cancel')}
          </button>
          <button
            type="button"
            className="button-primary"
            disabled={!canConfirm}
            onClick={() => onConfirm({
              overrides,
              reason: isReprint ? reason.trim() : undefined,
            })}
          >
            {loading
              ? (isReprint ? t('badgePrintPreviewPrinting') : t('badgePrintPreviewPrinting'))
              : (isReprint ? t('badgePrintReprintAction') : t('badgePrintPreviewConfirm'))}
          </button>
        </div>
      </div>
    </div>
  )
}
