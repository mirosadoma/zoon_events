import { useEffect, useRef, useState } from 'react'
import OrganizerFaqManager from '@/components/ai/OrganizerFaqManager'
import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import {
  Loader2,
  Sparkles,
  RefreshCw,
  AlertCircle,
  TrendingUp,
  AlertTriangle,
  Lightbulb,
  Send,
  Bot,
  User,
} from 'lucide-react'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
}

type Highlight = {
  kind: 'trend' | 'risk' | 'action'
  text: string
}

type InsightResult = {
  summary: string | null
  highlights: Highlight[] | null
  cached: boolean
  generated_at: string
  expires_at: string
  metrics_used: Record<string, unknown>
  outcome?: string
}

type ChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  kind?: 'insight' | 'reply' | 'system'
}

type Props = {
  event: EventRow
  tenantId: string
  aiAvailable: boolean
  aiStatus?: {
    available: boolean
    adapter: string
    reason?: string | null
    hint?: string | null
  }
}

const metricWindows = [
  { id: 'last_7_days', label: 'Last 7 days', label_ar: 'آخر 7 أيام' },
  { id: 'last_14_days', label: 'Last 14 days', label_ar: 'آخر 14 يوماً' },
  { id: 'last_30_days', label: 'Last 30 days', label_ar: 'آخر 30 يوماً' },
  { id: 'all_time', label: 'All time', label_ar: 'كل الوقت' },
] as const

const suggestedQuestions = {
  en: [
    'Why did registrations change?',
    'How is check-in performing?',
    'What should I improve next?',
  ],
  ar: [
    'لماذا تغيرت التسجيلات؟',
    'كيف أداء تسجيل الحضور؟',
    'ما الذي يجب تحسينه؟',
  ],
} as const

function HighlightIcon({ kind }: { kind: string }) {
  switch (kind) {
    case 'trend':
      return <TrendingUp className="h-4 w-4 text-emerald-500" />
    case 'risk':
      return <AlertTriangle className="h-4 w-4 text-amber-500" />
    case 'action':
      return <Lightbulb className="h-4 w-4 text-blue-500" />
    default:
      return <Sparkles className="h-4 w-4" />
  }
}

function buildInsightMessage(result: InsightResult, locale: 'en' | 'ar'): string {
  const lines: string[] = []
  if (result.summary) {
    lines.push(result.summary)
  }
  if (result.highlights && result.highlights.length > 0) {
    lines.push('')
    for (const highlight of result.highlights) {
      lines.push(`• ${highlight.text}`)
    }
  }
  if (lines.length === 0) {
    return locale === 'ar'
      ? 'تم إنشاء التحليل. اسألني عن أي مقياس.'
      : 'Insights ready. Ask me about any metric.'
  }
  return lines.join('\n')
}

function outcomeToText(outcome: string, locale: 'en' | 'ar'): string {
  if (outcome === 'out_of_scope') {
    return locale === 'ar'
      ? 'لا يمكن الإجابة على هذا السؤال باستخدام المقاييس المتاحة.'
      : 'This question cannot be answered using the available metrics.'
  }
  if (outcome === 'insufficient_data') {
    return locale === 'ar'
      ? 'لا توجد بيانات كافية للإجابة.'
      : 'Not enough data to answer.'
  }
  return locale === 'ar'
    ? 'لم أتمكن من الإجابة الآن.'
    : 'I could not answer that right now.'
}

export default function AiInsights({ event, tenantId, aiAvailable, aiStatus }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [metricWindow, setMetricWindow] = useState<string>('last_14_days')
  const [loading, setLoading] = useState(false)
  const [result, setResult] = useState<InsightResult | null>(null)
  const [error, setError] = useState<string | null>(null)

  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [question, setQuestion] = useState('')
  const [askLoading, setAskLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, askLoading])

  async function generateInsight(refresh = false) {
    setLoading(true)
    setError(null)
    setResult(null)
    setMessages([])

    try {
      const data = await apiFetch<InsightResult>(`/api/v1/tenant/events/${event.id}/ai-insights`, {
        method: 'POST',
        tenantId,
        body: {
          metric_window: metricWindow,
          locale,
          refresh,
        },
      })
      setResult(data)

      if (data.outcome === 'insufficient_data') {
        setMessages([
          {
            id: crypto.randomUUID(),
            role: 'assistant',
            kind: 'system',
            content: locale === 'ar'
              ? 'لا توجد بيانات كافية لإنشاء تحليلات لهذه الفترة. جرّب فترة أوسع أو بعد توفر تسجيلات.'
              : 'Not enough data to generate insights for this period. Try a wider window or wait for more registrations.',
          },
        ])
      } else {
        setMessages([
          {
            id: crypto.randomUUID(),
            role: 'assistant',
            kind: 'insight',
            content: buildInsightMessage(data, locale),
          },
        ])
        queueMicrotask(() => inputRef.current?.focus())
      }
    } catch (e) {
      if (e instanceof ApiFetchError) {
        if (e.status === 503) {
          setError(locale === 'ar' ? 'تحليلات الذكاء الاصطناعي غير متاحة حالياً' : 'AI analytics is currently unavailable')
        } else {
          setError(e.message)
        }
      } else {
        setError(locale === 'ar' ? 'حدث خطأ غير متوقع' : 'An unexpected error occurred')
      }
    } finally {
      setLoading(false)
    }
  }

  async function askQuestion(raw?: string) {
    const text = (raw ?? question).trim()
    if (!text || askLoading || !result || result.outcome === 'insufficient_data') return

    const userMessage: ChatMessage = {
      id: crypto.randomUUID(),
      role: 'user',
      content: text,
    }

    setMessages((current) => [...current, userMessage])
    setQuestion('')
    setAskLoading(true)

    try {
      const data = await apiFetch<{ outcome: string; answer?: string }>(`/api/v1/tenant/events/${event.id}/ai-insights/ask`, {
        method: 'POST',
        tenantId,
        body: {
          metric_window: metricWindow,
          locale,
          question: text,
        },
      })

      const content = data.outcome === 'answered' && data.answer
        ? data.answer
        : outcomeToText(data.outcome, locale)

      setMessages((current) => [
        ...current,
        {
          id: crypto.randomUUID(),
          role: 'assistant',
          kind: 'reply',
          content,
        },
      ])
    } catch (e) {
      if (e instanceof ApiFetchError) {
        toast(e.message, 'error')
      }
      setMessages((current) => [
        ...current,
        {
          id: crypto.randomUUID(),
          role: 'assistant',
          kind: 'system',
          content: locale === 'ar'
            ? 'حدث خطأ أثناء الإجابة. حاول مرة أخرى.'
            : 'Something went wrong while answering. Please try again.',
        },
      ])
    } finally {
      setAskLoading(false)
      queueMicrotask(() => inputRef.current?.focus())
    }
  }

  const chatReady = Boolean(result && result.outcome !== 'insufficient_data')
  const suggestions = suggestedQuestions[locale]

  return (
    <DashboardLayout title={locale === 'ar' ? 'تحليلات الذكاء الاصطناعي' : 'AI Insights'}>
      <PageHeader
        title={locale === 'ar' ? 'تحليلات الذكاء الاصطناعي' : 'AI Insights'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'تحليلات الذكاء الاصطناعي' : 'AI Insights' },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/reports`}>
            {t('reports')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        {!aiAvailable && (
          <div className="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            <div className="flex items-start gap-2">
              <AlertCircle className="h-5 w-5 shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="text-sm font-medium">
                  {locale === 'ar'
                    ? 'تحليلات الذكاء الاصطناعي غير متاحة في هذه البيئة'
                    : 'AI analytics is not available in this environment'}
                </p>
                {aiStatus?.hint ? (
                  <p className="text-xs opacity-90">{aiStatus.hint}</p>
                ) : null}
              </div>
            </div>
          </div>
        )}

        <div className="grid gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
          <aside className="state-panel space-y-4 h-fit">
            <div>
              <label className="block text-sm font-medium mb-2">
                {locale === 'ar' ? 'الفترة الزمنية' : 'Time period'}
              </label>
              <select
                value={metricWindow}
                onChange={(e) => setMetricWindow(e.target.value)}
                className="input w-full"
              >
                {metricWindows.map((w) => (
                  <option key={w.id} value={w.id}>
                    {locale === 'ar' ? w.label_ar : w.label}
                  </option>
                ))}
              </select>
            </div>

            <button
              type="button"
              onClick={() => generateInsight(false)}
              disabled={loading || !aiAvailable}
              className="button-primary w-full inline-flex items-center justify-center gap-2"
            >
              {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Sparkles className="h-4 w-4" />}
              {locale === 'ar' ? 'بدء المحادثة' : 'Start chat'}
            </button>

            {result && (
              <button
                type="button"
                onClick={() => generateInsight(true)}
                disabled={loading || !aiAvailable}
                className="button-secondary w-full inline-flex items-center justify-center gap-2"
              >
                <RefreshCw className="h-4 w-4" />
                {locale === 'ar' ? 'تحديث التحليل' : 'Refresh insights'}
              </button>
            )}

            {result?.highlights && result.highlights.length > 0 ? (
              <div className="space-y-2 pt-2 border-t border-[var(--border)]">
                <p className="text-xs font-semibold uppercase tracking-wide text-[var(--muted)]">
                  {locale === 'ar' ? 'أبرز النقاط' : 'Highlights'}
                </p>
                <ul className="space-y-2">
                  {result.highlights.map((highlight, index) => (
                    <li key={index} className="flex items-start gap-2 text-sm text-[var(--text)]">
                      <HighlightIcon kind={highlight.kind} />
                      <span>{highlight.text}</span>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            {result ? (
              <details className="text-sm pt-2 border-t border-[var(--border)]">
                <summary className="cursor-pointer text-[var(--muted)]">
                  {locale === 'ar' ? 'المقاييس المستخدمة' : 'Metrics used'}
                </summary>
                <pre className="mt-2 p-3 rounded-lg bg-[var(--surface-2,#f8fafc)] overflow-x-auto text-xs">
                  {JSON.stringify(result.metrics_used, null, 2)}
                </pre>
              </details>
            ) : null}

            <div className="pt-2 border-t border-[var(--border)]">
              <OrganizerFaqManager eventId={event.id} tenantId={tenantId} compact />
            </div>
          </aside>

          <section className="state-panel !p-0 overflow-hidden flex flex-col min-h-[560px] max-h-[75vh]">
            <header className="flex items-center gap-3 border-b border-[var(--border)] px-4 py-3 bg-[color-mix(in_srgb,var(--brand)_6%,transparent)]">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand)] text-white">
                <Bot className="h-5 w-5" />
              </div>
              <div className="min-w-0">
                <h3 className="font-semibold truncate">
                  {locale === 'ar' ? 'مساعد التحليلات' : 'Analytics assistant'}
                </h3>
                <p className="text-xs text-[var(--muted)] truncate">
                  {locale === 'ar'
                    ? 'اسأل عن التسجيلات والحضور والمقاييس'
                    : 'Ask about registrations, check-ins, and metrics'}
                  {result?.cached ? (locale === 'ar' ? ' · مخبأ' : ' · cached') : ''}
                </p>
              </div>
            </header>

            {error && (
              <div className="mx-4 mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                <div className="flex items-center gap-2">
                  <AlertCircle className="h-4 w-4" />
                  <p className="text-sm">{error}</p>
                </div>
              </div>
            )}

            <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-[color-mix(in_srgb,var(--surface)_92%,#e2e8f0)]">
              {!result && !loading && !error ? (
                <div className="h-full min-h-[280px] flex items-center justify-center px-6">
                  <div className="text-center max-w-md">
                    <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--brand)] text-white">
                      <Bot className="h-5 w-5" />
                    </div>
                    <h2 className="text-lg font-semibold">
                      {locale === 'ar' ? 'ابدأ محادثة التحليلات' : 'Start an insights chat'}
                    </h2>
                    <p className="mt-2 text-sm text-[var(--muted)]">
                      {locale === 'ar'
                        ? 'اختر الفترة ثم اضغط "بدء المحادثة" لرؤية الملخص واسأل بعده.'
                        : 'Pick a period, then click “Start chat” to see the summary and ask follow-ups.'}
                    </p>
                  </div>
                </div>
              ) : null}

              {loading ? (
                <div className="flex items-center gap-2 text-sm text-[var(--muted)]">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  {locale === 'ar' ? 'جارٍ تجهيز التحليل...' : 'Preparing insights...'}
                </div>
              ) : null}

              {messages.map((message) => (
                <div
                  key={message.id}
                  className={`flex gap-2 ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  {message.role === 'assistant' ? (
                    <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[var(--brand)] text-white">
                      <Bot className="h-4 w-4" />
                    </div>
                  ) : null}

                  <div
                    className={`max-w-[85%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm whitespace-pre-wrap ${
                      message.role === 'user'
                        ? 'rounded-br-md bg-[var(--brand)] text-white'
                        : 'rounded-bl-md bg-white text-[var(--text)] border border-[var(--border)] dark:bg-slate-900'
                    }`}
                  >
                    {message.content}
                  </div>

                  {message.role === 'user' ? (
                    <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-700 text-white">
                      <User className="h-4 w-4" />
                    </div>
                  ) : null}
                </div>
              ))}

              {askLoading ? (
                <div className="flex items-center gap-2 text-sm text-[var(--muted)]">
                  <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand)] text-white">
                    <Bot className="h-4 w-4" />
                  </div>
                  <span className="inline-flex items-center gap-2 rounded-2xl rounded-bl-md bg-white border border-[var(--border)] px-3 py-2 dark:bg-slate-900">
                    <Loader2 className="h-3.5 w-3.5 animate-spin" />
                    {locale === 'ar' ? 'يكتب...' : 'Typing...'}
                  </span>
                </div>
              ) : null}

              <div ref={messagesEndRef} />
            </div>

            <footer className="border-t border-[var(--border)] bg-white dark:bg-slate-950 p-3 space-y-3">
              {chatReady ? (
                <div className="flex flex-wrap gap-2">
                  {suggestions.map((suggestion) => (
                    <button
                      key={suggestion}
                      type="button"
                      disabled={askLoading}
                      onClick={() => void askQuestion(suggestion)}
                      className="rounded-full border border-[var(--border)] px-3 py-1 text-xs text-[var(--muted)] hover:bg-[var(--surface)] hover:text-[var(--text)] disabled:opacity-50"
                    >
                      {suggestion}
                    </button>
                  ))}
                </div>
              ) : null}

              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  void askQuestion()
                }}
                className="flex gap-2"
              >
                <input
                  ref={inputRef}
                  type="text"
                  value={question}
                  onChange={(e) => setQuestion(e.target.value)}
                  placeholder={
                    chatReady
                      ? (locale === 'ar' ? 'اكتب سؤالك...' : 'Type your question...')
                      : (locale === 'ar' ? 'ابدأ المحادثة أولاً' : 'Start the chat first')
                  }
                  className="input flex-1"
                  maxLength={500}
                  disabled={!chatReady || askLoading}
                />
                <button
                  type="submit"
                  disabled={!chatReady || !question.trim() || askLoading}
                  className="button-primary inline-flex h-10 w-10 items-center justify-center p-0"
                  aria-label={locale === 'ar' ? 'إرسال' : 'Send'}
                >
                  {askLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
                </button>
              </form>
            </footer>
          </section>
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
