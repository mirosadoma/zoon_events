import { useState, useRef, useEffect } from 'react'
import { MessageCircle, X, Send, Loader2, AlertCircle, ExternalLink } from 'lucide-react'
import { clsx as cn } from 'clsx'

type Citation = {
  source_type: string
  source_id: string
  title: string | null
}

type Message = {
  id: string
  role: 'user' | 'assistant'
  content: string
  citations?: Citation[]
  outcome?: string
}

type Fallback = {
  action: 'registration' | 'contact' | 'none'
  email?: string
  message?: string
}

type AssistantConfig = {
  enabled: boolean
  display_name: { en: string; ar: string }
  greeting: { en: string; ar: string }
}

type Props = {
  eventSlug: string
  locale: 'en' | 'ar'
  config: AssistantConfig
  registerUrl?: string
}

export default function AssistantPanel({ eventSlug, locale, config, registerUrl }: Props) {
  const [isOpen, setIsOpen] = useState(false)
  const [messages, setMessages] = useState<Message[]>([])
  const [input, setInput] = useState('')
  const [loading, setLoading] = useState(false)
  const [conversationId, setConversationId] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const messagesEndRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)

  const displayName = locale === 'ar' ? config.display_name.ar : config.display_name.en
  const greeting = locale === 'ar' ? config.greeting.ar : config.greeting.en

  useEffect(() => {
    if (isOpen) {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' })
      inputRef.current?.focus()
    }
  }, [isOpen, messages])

  const handleSend = async () => {
    if (!input.trim() || loading) return

    const userMessage: Message = {
      id: crypto.randomUUID(),
      role: 'user',
      content: input.trim(),
    }

    setMessages((prev) => [...prev, userMessage])
    setInput('')
    setLoading(true)
    setError(null)

    try {
      const response = await fetch(`/api/v1/public/events/${eventSlug}/assistant/ask`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({
          conversation_id: conversationId,
          message: userMessage.content,
          locale,
        }),
      })

      const data = await response.json()

      if (!response.ok) {
        if (response.status === 429) {
          setError(locale === 'ar' ? 'يرجى الانتظار قبل إرسال سؤال آخر' : 'Please wait before sending another question')
          return
        }
        if (response.status === 503) {
          setError(locale === 'ar' ? 'المساعد غير متاح حالياً' : 'Assistant is currently unavailable')
          return
        }
        throw new Error(data.title || 'Unknown error')
      }

      if (data.data.conversation_id) {
        setConversationId(data.data.conversation_id)
      }

      const assistantMessage: Message = {
        id: crypto.randomUUID(),
        role: 'assistant',
        content: data.data.answer || getOutcomeMessage(data.data.outcome, data.data.fallback),
        citations: data.data.citations,
        outcome: data.data.outcome,
      }

      setMessages((prev) => [...prev, assistantMessage])
    } catch (err) {
      setError(locale === 'ar' ? 'حدث خطأ. يرجى المحاولة مرة أخرى.' : 'An error occurred. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  const getOutcomeMessage = (outcome: string, fallback?: Fallback): string => {
    switch (outcome) {
      case 'unanswered':
        return locale === 'ar'
          ? 'عذراً، لا أملك معلومات كافية للإجابة على هذا السؤال.'
          : "I don't have enough information to answer this question."
      case 'refused':
        return locale === 'ar'
          ? 'عذراً، لا يمكنني المساعدة في هذا الطلب.'
          : "I'm sorry, I can't help with that request."
      case 'throttled':
        return locale === 'ar'
          ? 'لقد وصلت إلى الحد الأقصى للأسئلة. يرجى المحاولة لاحقاً.'
          : "You've reached the question limit. Please try again later."
      case 'unavailable':
        return locale === 'ar'
          ? 'المساعد غير متاح مؤقتاً.'
          : 'The assistant is temporarily unavailable.'
      default:
        return ''
    }
  }

  if (!config.enabled) {
    return null
  }

  return (
    <>
      {/* Floating button */}
      <button
        onClick={() => setIsOpen(true)}
        className={cn(
          'fixed bottom-6 end-6 z-50 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105',
          isOpen && 'hidden',
        )}
        aria-label={locale === 'ar' ? 'فتح المساعد' : 'Open assistant'}
      >
        <MessageCircle className="h-6 w-6" />
      </button>

      {/* Chat panel */}
      {isOpen && (
        <div className="fixed bottom-6 end-6 z-50 flex h-[500px] w-[380px] max-w-[calc(100vw-2rem)] flex-col rounded-xl border bg-background shadow-2xl">
          {/* Header */}
          <div className="flex items-center justify-between border-b px-4 py-3">
            <div>
              <h3 className="font-semibold">{displayName || (locale === 'ar' ? 'مساعد الحدث' : 'Event Assistant')}</h3>
            </div>
            <button
              onClick={() => setIsOpen(false)}
              className="rounded-full p-1 hover:bg-muted"
              aria-label={locale === 'ar' ? 'إغلاق' : 'Close'}
            >
              <X className="h-5 w-5" />
            </button>
          </div>

          {/* Messages */}
          <div className="flex-1 overflow-y-auto p-4 space-y-4">
            {/* Greeting */}
            {messages.length === 0 && greeting && (
              <div className="bg-muted rounded-lg p-3 text-sm">
                {greeting}
              </div>
            )}

            {messages.map((msg) => (
              <div
                key={msg.id}
                className={cn(
                  'max-w-[85%] rounded-lg p-3 text-sm',
                  msg.role === 'user'
                    ? 'ms-auto bg-primary text-primary-foreground'
                    : 'bg-muted',
                )}
              >
                <p className="whitespace-pre-wrap">{msg.content}</p>

                {/* Citations */}
                {msg.citations && msg.citations.length > 0 && (
                  <div className="mt-2 pt-2 border-t border-border/50 space-y-1">
                    <p className="text-xs opacity-70">
                      {locale === 'ar' ? 'المصادر:' : 'Sources:'}
                    </p>
                    {msg.citations.map((citation, i) => (
                      <span
                        key={i}
                        className="inline-block text-xs bg-background/50 px-2 py-0.5 rounded me-1"
                      >
                        [{i + 1}] {citation.title || citation.source_type}
                      </span>
                    ))}
                  </div>
                )}

                {/* Fallback for unanswered */}
                {msg.outcome === 'unanswered' && registerUrl && (
                  <a
                    href={registerUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-2 flex items-center gap-1 text-xs text-primary underline"
                  >
                    {locale === 'ar' ? 'صفحة التسجيل' : 'Registration page'}
                    <ExternalLink className="h-3 w-3" />
                  </a>
                )}
              </div>
            ))}

            {loading && (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="h-4 w-4 animate-spin" />
                {locale === 'ar' ? 'جارٍ التفكير...' : 'Thinking...'}
              </div>
            )}

            <div ref={messagesEndRef} />
          </div>

          {/* Error message */}
          {error && (
            <div className="mx-4 mb-2 flex items-center gap-2 rounded-lg bg-destructive/10 p-2 text-xs text-destructive">
              <AlertCircle className="h-4 w-4" />
              {error}
            </div>
          )}

          {/* Input */}
          <div className="border-t p-4">
            <form
              onSubmit={(e) => {
                e.preventDefault()
                handleSend()
              }}
              className="flex gap-2"
            >
              <input
                ref={inputRef}
                type="text"
                value={input}
                onChange={(e) => setInput(e.target.value)}
                placeholder={locale === 'ar' ? 'اكتب سؤالك...' : 'Type your question...'}
                className="flex-1 rounded-lg border bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary"
                disabled={loading}
                maxLength={1000}
              />
              <button
                type="submit"
                disabled={!input.trim() || loading}
                className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary text-primary-foreground disabled:opacity-50"
              >
                <Send className="h-4 w-4" />
              </button>
            </form>
          </div>
        </div>
      )}
    </>
  )
}
