import { useState, useCallback } from 'react'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'

type FormField = {
  id: string
  name: string
  label: string
  type: 'text' | 'email' | 'phone' | 'textarea' | 'select'
  required: boolean
  options?: string[]
}

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventSlug?: string
  pageId?: string
  blockId?: string
  registerUrl?: string
}

export default function FormRenderer({
  content,
  options,
  locale,
  eventSlug,
  pageId,
  blockId,
}: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const description = typeof content.description === 'string' ? content.description : ''
  const submitLabel = typeof content.submit_label === 'string' ? content.submit_label : (locale === 'ar' ? 'إرسال' : 'Submit')
  const successMessage = typeof content.success_message === 'string' ? content.success_message : (locale === 'ar' ? 'تم الإرسال بنجاح!' : 'Submitted successfully!')
  const fields: FormField[] = Array.isArray(content.fields) ? content.fields : []
  const background = options.background as SiteBackground | undefined

  const bgStyle = backgroundStyle(background)
  const overlayStyle = backgroundOverlayStyle(background)
  const hasBackground = background?.type && background.type !== 'none'

  const [formData, setFormData] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [submitted, setSubmitted] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleChange = useCallback((name: string, value: string) => {
    setFormData((prev) => ({ ...prev, [name]: value }))
  }, [])

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      if (!eventSlug || !blockId) return

      setSubmitting(true)
      setError(null)

      try {
        await apiFetch(`/api/v1/public/events/${eventSlug}/site/forms/${blockId}`, {
          method: 'POST',
          skipAuthRedirect: true,
          body: {
            locale,
            page_id: pageId || 'home',
            fields: formData,
          },
        })
        setSubmitted(true)
      } catch (err) {
        setError(err instanceof ApiFetchError ? err.message : (locale === 'ar' ? 'حدث خطأ. حاول مرة أخرى.' : 'An error occurred. Please try again.'))
      } finally {
        setSubmitting(false)
      }
    },
    [eventSlug, blockId, locale, pageId, formData],
  )

  if (fields.length === 0) {
    return (
      <section className="relative py-16 px-6" style={bgStyle}>
        {overlayStyle && <div style={overlayStyle} />}
        <div className="relative max-w-2xl mx-auto text-center">
          {title && <h2 className="text-3xl font-bold mb-4">{title}</h2>}
          <p className="text-muted-foreground">
            {locale === 'ar' ? 'لم يتم تكوين حقول النموذج.' : 'Form fields not configured.'}
          </p>
        </div>
      </section>
    )
  }

  if (submitted) {
    return (
      <section className="relative py-16 px-6" style={bgStyle}>
        {overlayStyle && <div style={overlayStyle} />}
        <div className={`relative max-w-2xl mx-auto text-center ${hasBackground && background?.type === 'image' ? 'text-white' : ''}`}>
          <div className="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-8">
            <div className="text-4xl mb-4">✓</div>
            <p className="text-lg font-medium text-green-800 dark:text-green-200">{successMessage}</p>
          </div>
        </div>
      </section>
    )
  }

  return (
    <section className="relative py-16 px-6" style={bgStyle}>
      {overlayStyle && <div style={overlayStyle} />}
      <div className={`relative max-w-2xl mx-auto ${hasBackground && background?.type === 'image' ? 'text-white' : ''}`}>
        {(title || description) && (
          <div className="text-center mb-8">
            {title && <h2 className="text-3xl md:text-4xl font-bold mb-3">{title}</h2>}
            {description && <p className="text-lg text-muted-foreground">{description}</p>}
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-5 bg-background/80 backdrop-blur-sm rounded-xl p-6 shadow-sm">
          {fields.map((field) => (
            <div key={field.id} className="space-y-2">
              <label className="block text-sm font-medium">
                {field.label}
                {field.required && <span className="text-red-500 ms-1">*</span>}
              </label>

              {field.type === 'textarea' ? (
                <textarea
                  name={field.name}
                  value={formData[field.name] || ''}
                  onChange={(e) => handleChange(field.name, e.target.value)}
                  required={field.required}
                  rows={4}
                  className="control w-full focus:border-[var(--brand)] focus:ring-2 focus:ring-[var(--focus-ring)]/20"
                />
              ) : field.type === 'select' ? (
                <select
                  name={field.name}
                  value={formData[field.name] || ''}
                  onChange={(e) => handleChange(field.name, e.target.value)}
                  required={field.required}
                  className="control w-full focus:border-[var(--brand)] focus:ring-2 focus:ring-[var(--focus-ring)]/20"
                >
                  <option value="">{locale === 'ar' ? 'اختر...' : 'Select...'}</option>
                  {(field.options || []).map((opt) => (
                    <option key={opt} value={opt}>{opt}</option>
                  ))}
                </select>
              ) : (
                <input
                  type={field.type === 'email' ? 'email' : field.type === 'phone' ? 'tel' : 'text'}
                  name={field.name}
                  value={formData[field.name] || ''}
                  onChange={(e) => handleChange(field.name, e.target.value)}
                  required={field.required}
                  className="control w-full focus:border-[var(--brand)] focus:ring-2 focus:ring-[var(--focus-ring)]/20"
                />
              )}
            </div>
          ))}

          {error && (
            <div className="p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-700 dark:text-red-300">
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={submitting}
            className="button-primary w-full py-3"
          >
            {submitting ? (locale === 'ar' ? 'جاري الإرسال...' : 'Submitting...') : submitLabel}
          </button>
        </form>
      </div>
    </section>
  )
}
