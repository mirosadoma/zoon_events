import { useEffect, useState } from 'react'
import OrganizerFaqManager from '@/components/ai/OrganizerFaqManager'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

type AssistantConfig = {
  enabled: boolean
  display_name: { en: string; ar: string }
  greeting: { en: string; ar: string }
  fallback_action: 'registration' | 'contact' | 'none'
  fallback_contact_email: string | null
  daily_question_limit: number
  index: {
    status: string
    version: number
    indexed_at: string | null
    chunk_count: number
    error_code: string | null
  }
  provider: { available: boolean; reason: string | null }
}

type Props = {
  eventId: string
  tenantId: string
}

export default function AssistantConfigPanel({ eventId, tenantId }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [config, setConfig] = useState<AssistantConfig | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [reindexing, setReindexing] = useState(false)

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        const response = await apiFetch<AssistantConfig>(
          `/api/v1/tenant/events/${eventId}/assistant`,
          { headers: { 'X-Tenant-ID': tenantId } },
        )
        if (!cancelled) {
          setConfig(response)
        }
      } catch {
        if (!cancelled) {
          setConfig(null)
        }
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    })()
    return () => {
      cancelled = true
    }
  }, [eventId, tenantId])

  async function save() {
    if (!config) return
    setSaving(true)
    try {
      const response = await apiFetch<AssistantConfig>(
        `/api/v1/tenant/events/${eventId}/assistant`,
        {
          method: 'PUT',
          headers: {
            'X-Tenant-ID': tenantId,
            'Idempotency-Key': `assistant-cfg-${eventId}-${Date.now()}`,
          },
          body: JSON.stringify({
            enabled: config.enabled,
            display_name: config.display_name,
            greeting: config.greeting,
            fallback_action: config.fallback_action,
            fallback_contact_email: config.fallback_contact_email,
            daily_question_limit: config.daily_question_limit,
          }),
        },
      )
      setConfig(response)
      toast(t('saved') || 'Saved', 'success')
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : 'Save failed', 'error')
    } finally {
      setSaving(false)
    }
  }

  async function reindex() {
    setReindexing(true)
    try {
      const response = await apiFetch<AssistantConfig>(
        `/api/v1/tenant/events/${eventId}/assistant/reindex`,
        {
          method: 'POST',
          headers: {
            'X-Tenant-ID': tenantId,
            'Idempotency-Key': `assistant-reindex-${eventId}-${Date.now()}`,
          },
        },
      )
      setConfig((prev) => (prev ? { ...prev, index: response.index ?? prev.index } : prev))
      toast(t('siteAssistantReindexQueued') || 'Index rebuild queued', 'success')
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : 'Reindex failed', 'error')
    } finally {
      setReindexing(false)
    }
  }

  if (loading) {
    return <p className="text-sm text-muted-foreground">{t('loading') || 'Loading…'}</p>
  }

  if (!config) {
    return null
  }

  return (
    <div className="rounded-lg border border-[var(--border)] bg-[var(--surface)] p-4 space-y-4">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="font-medium">{t('siteAssistantTitle') || 'Event assistant'}</h3>
          <p className="text-sm text-muted-foreground">
            {t('siteAssistantDescription') || 'Answer visitor questions from published event knowledge.'}
          </p>
        </div>
        <label className="inline-flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={config.enabled}
            onChange={(e) => setConfig({ ...config, enabled: e.target.checked })}
          />
          {t('enabled') || 'Enabled'}
        </label>
      </div>

      <div className="grid gap-3 md:grid-cols-2">
        <label className="space-y-1 text-sm">
          <span>{locale === 'ar' ? 'الاسم (عربي)' : 'Name (English)'}</span>
          <input
            className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2"
            value={config.display_name[locale] ?? ''}
            onChange={(e) =>
              setConfig({
                ...config,
                display_name: { ...config.display_name, [locale]: e.target.value },
              })
            }
          />
        </label>
        <label className="space-y-1 text-sm">
          <span>{t('siteAssistantGreeting') || 'Greeting'}</span>
          <input
            className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2"
            value={config.greeting[locale] ?? ''}
            onChange={(e) =>
              setConfig({
                ...config,
                greeting: { ...config.greeting, [locale]: e.target.value },
              })
            }
          />
        </label>
      </div>

      <div className="flex flex-wrap items-center gap-3 text-sm">
        <span className="text-muted-foreground">
          {t('siteAssistantIndex') || 'Index'}: {config.index.status}
          {config.index.chunk_count > 0 ? ` · ${config.index.chunk_count}` : ''}
        </span>
        {!config.provider.available && (
          <span className="text-amber-600">
            {t('siteAssistantProviderOffline') || 'AI provider offline'} ({config.provider.reason ?? 'unavailable'})
          </span>
        )}
      </div>

      <div className="flex flex-wrap gap-2">
        <button type="button" className="button-primary" disabled={saving} onClick={save}>
          {saving ? (t('saving') || 'Saving…') : (t('save') || 'Save')}
        </button>
        <button type="button" className="button-secondary" disabled={reindexing || !config.enabled} onClick={reindex}>
          {reindexing ? (t('siteAssistantReindexing') || 'Reindexing…') : (t('siteAssistantReindex') || 'Rebuild index')}
        </button>
      </div>

      <OrganizerFaqManager eventId={eventId} tenantId={tenantId} />
    </div>
  )
}
