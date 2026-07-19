import { FormEvent, useState } from 'react'
import { Download, Mail, Upload } from 'lucide-react'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'

type Props = {
  open: boolean
  eventId: string
  tenantId: string
  onClose: () => void
}

export default function SendPrivateInviteModal({ open, eventId, tenantId, onClose }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [mode, setMode] = useState<'email' | 'file'>('email')
  const [email, setEmail] = useState('')
  const [file, setFile] = useState<File | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!open) {
    return null
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      if (mode === 'email') {
        const result = await apiFetch<{ sent?: number; renewed?: number }>(`/api/v1/tenant/events/${eventId}/invites`, {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: { email: email.trim(), locale },
        })
        toast(
          (result.renewed ?? 0) > 0 ? t('inviteRenewed') : t('inviteSent'),
          'success',
        )
      } else {
        if (!file) {
          setError(t('inviteFileRequired'))
          setSubmitting(false)
          return
        }

        const body = new FormData()
        body.append('file', file)
        body.append('locale', locale)

        const result = await apiFetch<{ parsed?: number; sent?: number; renewed?: number }>(`/api/v1/tenant/events/${eventId}/invites/bulk`, {
          method: 'POST',
          tenantId,
          idempotency: true,
          body,
        })
        toast(
          t('inviteBulkSent')
            .replace(':count', String(result.parsed ?? ((result.sent ?? 0) + (result.renewed ?? 0)))),
          'success',
        )
      }

      setEmail('')
      setFile(null)
      onClose()
    } catch (caught) {
      setError(caught instanceof ApiFetchError ? caught.message : t('inviteCouldNotSend'))
    } finally {
      setSubmitting(false)
    }
  }

  async function downloadTemplate() {
    try {
      const csrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)
      const response = await fetch(`/api/v1/tenant/events/${eventId}/invites/template`, {
        headers: {
          Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          'X-Tenant-ID': tenantId,
          ...(csrf ? { 'X-XSRF-TOKEN': decodeURIComponent(csrf[1]) } : {}),
        },
        credentials: 'include',
      })
      if (!response.ok) {
        throw new Error('download failed')
      }
      const blob = await response.blob()
      const url = URL.createObjectURL(blob)
      const anchor = document.createElement('a')
      anchor.href = url
      anchor.download = 'private-event-invite-emails.xlsx'
      anchor.click()
      URL.revokeObjectURL(url)
    } catch {
      toast(t('inviteTemplateDownloadFailed'), 'error')
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" role="dialog" aria-modal="true">
      <div className="w-full max-w-lg rounded-[var(--radius-panel)] border border-[var(--border)] bg-[var(--surface-elevated)] p-5 shadow-xl">
        <div className="mb-4">
          <h2 className="text-lg font-semibold text-[var(--ink)]">{t('inviteSendTitle')}</h2>
          <p className="mt-1 text-sm text-[var(--muted)]">{t('inviteSendDescription')}</p>
        </div>

        <div className="mb-4 grid grid-cols-2 gap-2">
          <button
            type="button"
            className={mode === 'email' ? 'button-primary' : 'button-secondary'}
            onClick={() => setMode('email')}
          >
            <span className="inline-flex items-center justify-center gap-2">
              <Mail className="h-4 w-4" />
              {t('inviteModeEmail')}
            </span>
          </button>
          <button
            type="button"
            className={mode === 'file' ? 'button-primary' : 'button-secondary'}
            onClick={() => setMode('file')}
          >
            <span className="inline-flex items-center justify-center gap-2">
              <Upload className="h-4 w-4" />
              {t('inviteModeExcel')}
            </span>
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {error ? (
            <div className="rounded-[var(--radius-control)] border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
              {error}
            </div>
          ) : null}

          {mode === 'email' ? (
            <TextInput
              label={t('inviteEmailLabel')}
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          ) : (
            <div className="space-y-3">
              <p className="text-sm text-[var(--muted)]">{t('inviteExcelHelp')}</p>
              <button
                type="button"
                className="button-secondary inline-flex items-center gap-2"
                onClick={() => void downloadTemplate()}
              >
                <Download className="h-4 w-4" />
                {t('inviteDownloadTemplate')}
              </button>
              <label className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">{t('inviteUploadLabel')}</span>
                <input
                  type="file"
                  accept=".xlsx,.csv,.txt"
                  className="control"
                  onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                  required
                />
              </label>
            </div>
          )}

          <div className="flex justify-end gap-2">
            <button type="button" className="button-secondary" onClick={onClose} disabled={submitting}>
              {t('close')}
            </button>
            <SubmitButtonWithLoader
              label={t('inviteSendAction')}
              loading={submitting}
            />
          </div>
        </form>
      </div>
    </div>
  )
}
