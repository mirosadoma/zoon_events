import { useEffect, useRef, useState } from 'react'
import { Bot, Loader2, Send, User, BarChart3 } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

export type ChatStructuredItem = {
  type: string
  label: string
  value?: number
  city?: string
  event_name?: string
  registrations?: number
  date_range?: string
}

export type ChatMessage = {
  id: string
  role: 'user' | 'assistant'
  content: string
  handler?: string
  structured?: ChatStructuredItem[]
}

type Props = {
  tenantId: string
  disabled?: boolean
  className?: string
}

const suggestions = {
  en: [
    'How many attendees in Cairo events?',
    'What is the most popular event?',
    'How many tickets sold this week?',
    'List upcoming events',
  ],
  ar: [
    'كم عدد الحضور في فعاليات القاهرة؟',
    'ما هو الحدث الأكثر شعبية؟',
    'كم تذكرة بيعت هذا الأسبوع؟',
    'ما الفعاليات القادمة؟',
  ],
} as const

export default function PlatformChatBot({ tenantId, disabled = false, className = '' }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [messages, setMessages] = useState<ChatMessage[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
  }, [messages, loading])

  async function sendMessage(raw?: string) {
    const text = (raw ?? input).trim()
    if (!text || loading || disabled) return

    const userMessage: ChatMessage = {
      id: crypto.randomUUID(),
      role: 'user',
      content: text,
    }

    const history = messages.slice(-10).map((message) => ({
      role: message.role,
      content: message.content,
    }))

    setMessages((current) => [...current, userMessage])
    setInput('')
    setLoading(true)

    try {
      const data = await apiFetch<{
        answer: string
        handler: string
        structured?: ChatStructuredItem[]
      }>('/api/v1/chat', {
        method: 'POST',
        tenantId,
        body: {
          message: text,
          locale,
          history,
        },
      })

      setMessages((current) => [
        ...current,
        {
          id: crypto.randomUUID(),
          role: 'assistant',
          content: data.answer,
          handler: data.handler,
          structured: data.structured,
        },
      ])
    } catch (error) {
      toast(error instanceof ApiFetchError ? error.message : 'Chat failed', 'error')
      setMessages((current) => [
        ...current,
        {
          id: crypto.randomUUID(),
          role: 'assistant',
          content: locale === 'ar'
            ? 'حدث خطأ. حاول مرة أخرى.'
            : 'Something went wrong. Please try again.',
        },
      ])
    } finally {
      setLoading(false)
      queueMicrotask(() => inputRef.current?.focus())
    }
  }

  const suggestionList = suggestions[locale]

  return (
    <div className={`flex flex-col min-h-[480px] rounded-lg border border-[var(--border)] bg-[var(--surface)] overflow-hidden ${className}`}>
      <header className="flex items-center gap-3 border-b border-[var(--border)] px-4 py-3 bg-[color-mix(in_srgb,var(--brand)_6%,transparent)]">
        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand)] text-white">
          <Bot className="h-5 w-5" />
        </div>
        <div>
          <h3 className="font-semibold">{t('platformChatTitle') || 'AI Assistant'}</h3>
          <p className="text-xs text-muted-foreground">
            {t('platformChatSubtitle') || 'Ask about events, analytics, and tickets'}
          </p>
        </div>
      </header>

      <div className="flex-1 overflow-y-auto px-4 py-4 space-y-4 bg-[color-mix(in_srgb,var(--surface)_92%,#e2e8f0)]">
        {messages.length === 0 ? (
          <div className="text-center py-8 text-sm text-muted-foreground">
            <p>{t('platformChatEmpty') || 'Ask a question to get started.'}</p>
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
              {message.structured && message.structured.length > 0 ? (
                <div className="mt-2 space-y-1.5">
                  {message.structured.map((item, index) => (
                    <div
                      key={index}
                      className="flex items-center gap-2 rounded-md bg-[color-mix(in_srgb,var(--brand)_8%,transparent)] px-2.5 py-1.5 text-xs"
                    >
                      <BarChart3 className="h-3.5 w-3.5 shrink-0 text-[var(--brand)]" />
                      <span className="font-medium">{item.label}</span>
                      {item.value !== undefined ? (
                        <span className="ml-auto font-semibold tabular-nums">{item.value}</span>
                      ) : null}
                      {item.event_name ? (
                        <span className="ml-auto font-semibold">{item.event_name}</span>
                      ) : null}
                    </div>
                  ))}
                </div>
              ) : null}
            </div>

            {message.role === 'user' ? (
              <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-700 text-white">
                <User className="h-4 w-4" />
              </div>
            ) : null}
          </div>
        ))}

        {loading ? (
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--brand)] text-white">
              <Bot className="h-4 w-4" />
            </div>
            <span className="inline-flex items-center gap-2 rounded-2xl rounded-bl-md bg-white border border-[var(--border)] px-3 py-2 dark:bg-slate-900">
              <Loader2 className="h-3.5 w-3.5 animate-spin" />
              {t('platformChatTyping') || 'Typing...'}
            </span>
          </div>
        ) : null}

        <div ref={messagesEndRef} />
      </div>

      <footer className="border-t border-[var(--border)] bg-white dark:bg-slate-950 p-3 space-y-3">
        <div className="flex flex-wrap gap-2">
          {suggestionList.map((suggestion) => (
            <button
              key={suggestion}
              type="button"
              disabled={loading || disabled}
              onClick={() => void sendMessage(suggestion)}
              className="rounded-full border border-[var(--border)] px-3 py-1 text-xs text-muted-foreground hover:bg-[var(--surface)] hover:text-[var(--text)] disabled:opacity-50"
            >
              {suggestion}
            </button>
          ))}
        </div>

        <form
          onSubmit={(e) => {
            e.preventDefault()
            void sendMessage()
          }}
          className="flex gap-2"
        >
          <input
            ref={inputRef}
            type="text"
            value={input}
            onChange={(e) => setInput(e.target.value)}
            placeholder={t('platformChatPlaceholder') || 'Type your question...'}
            className="input flex-1"
            maxLength={1000}
            disabled={loading || disabled}
          />
          <button
            type="submit"
            disabled={!input.trim() || loading || disabled}
            className="button-primary inline-flex h-10 w-10 items-center justify-center p-0"
            aria-label={t('send') || 'Send'}
          >
            {loading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />}
          </button>
        </form>
      </footer>
    </div>
  )
}
