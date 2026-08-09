import { useState, useCallback } from 'react'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SelectInput from '@/components/forms/SelectInput'
import BackgroundEditor from '../BackgroundEditor'
import { apiFetch } from '@/lib/apiFetch'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
  eventId: string
  tenantId: string
  onContentChange: (updates: Record<string, unknown>) => void
  onOptionsChange: (updates: Record<string, unknown>) => void
  onRefsChange: (updates: Record<string, unknown>) => void
}

export default function MediaTextEditor({
  content,
  options,
  refs,
  locale,
  eventId,
  tenantId,
  onContentChange,
  onOptionsChange,
  onRefsChange,
}: Props) {
  const [uploading, setUploading] = useState(false)

  const title = typeof content.title === 'string' ? content.title : ''
  const body = typeof content.body === 'string' ? content.body : ''
  const buttonLabel = typeof content.button_label === 'string' ? content.button_label : ''
  const buttonHref = typeof content.button_href === 'string' ? content.button_href : ''
  const layout = typeof options.layout === 'string' ? options.layout : 'image_left'
  const image = typeof refs.image === 'string' ? refs.image : ''
  const background = (options.background as SiteBackground) || { type: 'none' }

  const handleImageUpload = useCallback(
    async (e: React.ChangeEvent<HTMLInputElement>) => {
      const file = e.target.files?.[0]
      if (!file) return

      setUploading(true)
      try {
        const formData = new FormData()
        formData.append('file', file)

        const response = await apiFetch<{ path: string; url: string }>(
          `/api/v1/tenant/events/${eventId}/site/media`,
          {
            method: 'POST',
            tenantId,
            body: formData,
          },
        )
        onRefsChange({ image: response.url || response.path })
      } catch (err) {
        console.error('Failed to upload image:', err)
      } finally {
        setUploading(false)
        e.target.value = ''
      }
    },
    [eventId, tenantId, onRefsChange],
  )

  const clearImage = useCallback(() => {
    onRefsChange({ image: undefined })
  }, [onRefsChange])

  return (
    <div className="space-y-4 p-4 bg-muted/30 rounded-lg">
      <h3 className="text-lg font-semibold">
        {locale === 'ar' ? 'قسم وسائط ونص' : 'Media + Text Section'}
      </h3>

      <TextInput
        label={locale === 'ar' ? 'العنوان' : 'Title'}
        name="title"
        value={title}
        onChange={(e) => onContentChange({ title: e.target.value })}
        placeholder={locale === 'ar' ? 'أدخل العنوان' : 'Enter title'}
      />

      <TextareaInput
        label={locale === 'ar' ? 'الوصف' : 'Description'}
        name="body"
        value={body}
        onChange={(e) => onContentChange({ body: e.target.value })}
        placeholder={locale === 'ar' ? 'أدخل الوصف' : 'Enter description'}
        rows={4}
      />

      <div className="grid grid-cols-2 gap-3">
        <TextInput
          label={locale === 'ar' ? 'نص الزر' : 'Button Label'}
          name="button_label"
          value={buttonLabel}
          onChange={(e) => onContentChange({ button_label: e.target.value })}
          placeholder={locale === 'ar' ? 'المزيد' : 'Learn More'}
        />
        <TextInput
          label={locale === 'ar' ? 'رابط الزر' : 'Button Link'}
          name="button_href"
          value={buttonHref}
          onChange={(e) => onContentChange({ button_href: e.target.value })}
          placeholder="https://..."
        />
      </div>

      <SelectInput
        label={locale === 'ar' ? 'التخطيط' : 'Layout'}
        name="layout"
        value={layout}
        onChange={(e) => onOptionsChange({ layout: e.target.value })}
        options={[
          { value: 'image_left', label: locale === 'ar' ? 'صورة في البداية' : 'Image at start' },
          { value: 'image_right', label: locale === 'ar' ? 'صورة في النهاية' : 'Image at end' },
          { value: 'image_top', label: locale === 'ar' ? 'صورة أعلى' : 'Image Top' },
        ]}
      />

      <div className="space-y-2">
        <p className="text-sm font-medium">{locale === 'ar' ? 'الصورة' : 'Image'}</p>
        {image && (
          <div className="relative">
            <img src={image} alt="" className="h-32 w-full rounded object-cover" />
            <button
              type="button"
              className="absolute top-1 end-1 bg-black/50 text-white rounded-full p-1 text-xs hover:bg-black/70"
              onClick={clearImage}
            >
              ✕
            </button>
          </div>
        )}
        <label className="block">
          <span className="button-secondary inline-block cursor-pointer text-sm">
            {uploading
              ? (locale === 'ar' ? 'جاري الرفع...' : 'Uploading...')
              : (locale === 'ar' ? 'رفع صورة' : 'Upload Image')}
          </span>
          <input
            type="file"
            accept="image/*"
            onChange={handleImageUpload}
            disabled={uploading}
            className="hidden"
          />
        </label>
      </div>

      <BackgroundEditor
        value={background}
        onChange={(bg) => onOptionsChange({ background: bg })}
        locale={locale}
        tenantId={tenantId}
        eventId={eventId}
      />
    </div>
  )
}
