import { useEffect, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { Loader2, Plus, Trash2 } from 'lucide-react'

export type OrganizerFaq = {
  id: string
  question_en: string
  question_ar: string
  answer_en: string
  answer_ar: string
  sort_order: number
  is_active: boolean
}

type DraftFaq = {
  question_en: string
  question_ar: string
  answer_en: string
  answer_ar: string
  is_active: boolean
}

const emptyDraft = (): DraftFaq => ({
  question_en: '',
  question_ar: '',
  answer_en: '',
  answer_ar: '',
  is_active: true,
})

type Props = {
  eventId: string
  tenantId: string
  compact?: boolean
}

export default function OrganizerFaqManager({ eventId, tenantId, compact = false }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [faqs, setFaqs] = useState<OrganizerFaq[]>([])
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [editingId, setEditingId] = useState<string | null>(null)
  const [draft, setDraft] = useState<DraftFaq>(emptyDraft)
  const [showForm, setShowForm] = useState(false)

  useEffect(() => {
    let cancelled = false
    ;(async () => {
      try {
        const response = await apiFetch<{ faqs: OrganizerFaq[] }>(
          `/api/v1/tenant/events/${eventId}/assistant/faqs`,
          { headers: { 'X-Tenant-ID': tenantId } },
        )
        if (!cancelled) {
          setFaqs(response.faqs)
        }
      } catch {
        if (!cancelled) {
          setFaqs([])
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

  function startCreate() {
    setEditingId(null)
    setDraft(emptyDraft())
    setShowForm(true)
  }

  function startEdit(faq: OrganizerFaq) {
    setEditingId(faq.id)
    setDraft({
      question_en: faq.question_en,
      question_ar: faq.question_ar,
      answer_en: faq.answer_en,
      answer_ar: faq.answer_ar,
      is_active: faq.is_active,
    })
    setShowForm(true)
  }

  function cancelForm() {
    setShowForm(false)
    setEditingId(null)
    setDraft(emptyDraft())
  }

  async function saveFaq() {
    if (!draft.question_en.trim() || !draft.question_ar.trim() || !draft.answer_en.trim() || !draft.answer_ar.trim()) {
      toast(t('assistantFaqRequired') || 'Question and answer are required in both languages.', 'error')
      return
    }

    setSaving(true)
    try {
      if (editingId) {
        const response = await apiFetch<OrganizerFaq>(
          `/api/v1/tenant/events/${eventId}/assistant/faqs/${editingId}`,
          {
            method: 'PUT',
            headers: {
              'X-Tenant-ID': tenantId,
              'Idempotency-Key': `faq-update-${editingId}-${Date.now()}`,
            },
            body: JSON.stringify(draft),
          },
        )
        setFaqs((current) => current.map((faq) => (faq.id === editingId ? response : faq)))
      } else {
        const response = await apiFetch<OrganizerFaq>(
          `/api/v1/tenant/events/${eventId}/assistant/faqs`,
          {
            method: 'POST',
            headers: {
              'X-Tenant-ID': tenantId,
              'Idempotency-Key': `faq-create-${eventId}-${Date.now()}`,
            },
            body: JSON.stringify(draft),
          },
        )
        setFaqs((current) => [...current, response])
      }
      toast(t('assistantFaqSaved') || 'FAQ saved. Knowledge reindex queued.', 'success')
      cancelForm()
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : (t('assistantFaqSaveFailed') || 'Save failed'), 'error')
    } finally {
      setSaving(false)
    }
  }

  async function deleteFaq(faqId: string) {
    if (!window.confirm(t('assistantFaqDeleteConfirm') || 'Delete this FAQ?')) {
      return
    }

    setSaving(true)
    try {
      await apiFetch(`/api/v1/tenant/events/${eventId}/assistant/faqs/${faqId}`, {
        method: 'DELETE',
        headers: {
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': `faq-delete-${faqId}-${Date.now()}`,
        },
      })
      setFaqs((current) => current.filter((faq) => faq.id !== faqId))
      if (editingId === faqId) {
        cancelForm()
      }
      toast(t('assistantFaqDeleted') || 'FAQ deleted. Knowledge reindex queued.', 'success')
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : (t('assistantFaqDeleteFailed') || 'Delete failed'), 'error')
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return (
      <div className="flex items-center gap-2 text-sm text-muted-foreground">
        <Loader2 className="h-4 w-4 animate-spin" />
        {t('loading') || 'Loading…'}
      </div>
    )
  }

  return (
    <div className={compact ? 'space-y-3' : 'space-y-4 border-t border-[var(--border)] pt-4'}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h4 className="font-medium text-sm">{t('assistantFaqTitle') || 'Organizer FAQs'}</h4>
          <p className="text-xs text-muted-foreground mt-0.5">
            {t('assistantFaqDescription') || 'Curated Q&A used by the visitor assistant and Insights chat.'}
          </p>
        </div>
        {!showForm && (
          <button type="button" className="button-secondary inline-flex items-center gap-1 text-xs" onClick={startCreate}>
            <Plus className="h-3.5 w-3.5" />
            {t('assistantFaqAdd') || 'Add FAQ'}
          </button>
        )}
      </div>

      {faqs.length === 0 && !showForm ? (
        <p className="text-sm text-muted-foreground">{t('assistantFaqEmpty') || 'No FAQs yet.'}</p>
      ) : (
        <ul className="space-y-2">
          {faqs.map((faq) => (
            <li
              key={faq.id}
              className="rounded-md border border-[var(--border)] px-3 py-2 text-sm space-y-1"
            >
              <div className="flex items-start justify-between gap-2">
                <button
                  type="button"
                  className="text-left flex-1 min-w-0"
                  onClick={() => startEdit(faq)}
                >
                  <p className="font-medium truncate">
                    {locale === 'ar' ? faq.question_ar : faq.question_en}
                  </p>
                  <p className="text-xs text-muted-foreground line-clamp-2">
                    {locale === 'ar' ? faq.answer_ar : faq.answer_en}
                  </p>
                </button>
                <div className="flex items-center gap-2 shrink-0">
                  <span className={`text-[10px] uppercase tracking-wide ${faq.is_active ? 'text-emerald-600' : 'text-muted-foreground'}`}>
                    {faq.is_active ? (t('assistantFaqActive') || 'Active') : (t('assistantFaqInactive') || 'Inactive')}
                  </span>
                  <button
                    type="button"
                    className="text-red-600 hover:text-red-700 p-1"
                    onClick={() => void deleteFaq(faq.id)}
                    disabled={saving}
                    aria-label={t('delete') || 'Delete'}
                  >
                    <Trash2 className="h-3.5 w-3.5" />
                  </button>
                </div>
              </div>
            </li>
          ))}
        </ul>
      )}

      {showForm && (
        <div className="rounded-md border border-[var(--border)] bg-[var(--surface)] p-3 space-y-3">
          <p className="text-sm font-medium">
            {editingId ? (t('assistantFaqEdit') || 'Edit FAQ') : (t('assistantFaqAdd') || 'Add FAQ')}
          </p>
          <div className="grid gap-3 md:grid-cols-2">
            <label className="space-y-1 text-xs">
              <span>{t('assistantFaqQuestionEn') || 'Question (EN)'}</span>
              <input
                className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2 text-sm"
                value={draft.question_en}
                onChange={(e) => setDraft({ ...draft, question_en: e.target.value })}
                maxLength={500}
              />
            </label>
            <label className="space-y-1 text-xs">
              <span>{t('assistantFaqQuestionAr') || 'Question (AR)'}</span>
              <input
                className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2 text-sm"
                value={draft.question_ar}
                onChange={(e) => setDraft({ ...draft, question_ar: e.target.value })}
                maxLength={500}
                dir="rtl"
              />
            </label>
            <label className="space-y-1 text-xs md:col-span-1">
              <span>{t('assistantFaqAnswerEn') || 'Answer (EN)'}</span>
              <textarea
                className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2 text-sm min-h-[80px]"
                value={draft.answer_en}
                onChange={(e) => setDraft({ ...draft, answer_en: e.target.value })}
                maxLength={5000}
              />
            </label>
            <label className="space-y-1 text-xs">
              <span>{t('assistantFaqAnswerAr') || 'Answer (AR)'}</span>
              <textarea
                className="w-full rounded-md border border-[var(--border)] bg-transparent px-3 py-2 text-sm min-h-[80px]"
                value={draft.answer_ar}
                onChange={(e) => setDraft({ ...draft, answer_ar: e.target.value })}
                maxLength={5000}
                dir="rtl"
              />
            </label>
          </div>
          <label className="inline-flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={draft.is_active}
              onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })}
            />
            {t('assistantFaqActive') || 'Active'}
          </label>
          <div className="flex flex-wrap gap-2">
            <button type="button" className="button-primary" disabled={saving} onClick={() => void saveFaq()}>
              {saving ? (t('saving') || 'Saving…') : (t('save') || 'Save')}
            </button>
            <button type="button" className="button-secondary" disabled={saving} onClick={cancelForm}>
              {t('cancel') || 'Cancel'}
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
