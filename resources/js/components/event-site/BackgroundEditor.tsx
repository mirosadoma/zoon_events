import { useState, useCallback } from 'react'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import { apiFetch } from '@/lib/apiFetch'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'

type Props = {
  value: SiteBackground
  onChange: (value: SiteBackground) => void
  locale: 'en' | 'ar'
  tenantId: string
  eventId: string
  label?: string
  compact?: boolean
  /** Unique prefix so multiple editors on one page don't share form control ids. */
  idPrefix?: string
}

export default function BackgroundEditor({
  value,
  onChange,
  locale,
  tenantId,
  eventId,
  label,
  compact = false,
  idPrefix = 'bg',
}: Props) {
  const [uploading, setUploading] = useState(false)

  const bgType = value.type ?? 'none'
  const color = value.color ?? '#ffffff'
  const colorEnd = value.color_end ?? '#000000'
  const image = value.image ?? ''
  const overlay = value.overlay ?? 30

  const handleTypeChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      onChange({ ...value, type: e.target.value as SiteBackground['type'] })
    },
    [value, onChange],
  )

  const handleColorChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onChange({ ...value, color: e.target.value })
    },
    [value, onChange],
  )

  const handleColorEndChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onChange({ ...value, color_end: e.target.value })
    },
    [value, onChange],
  )

  const handleOverlayChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onChange({ ...value, overlay: parseInt(e.target.value, 10) })
    },
    [value, onChange],
  )

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
        onChange({ ...value, image: response.url || response.path })
      } catch (err) {
        console.error('Failed to upload image:', err)
      } finally {
        setUploading(false)
        e.target.value = ''
      }
    },
    [eventId, tenantId, value, onChange],
  )

  const clearImage = useCallback(() => {
    onChange({ ...value, image: undefined })
  }, [value, onChange])

  const typeOptions = [
    { value: 'none', label: locale === 'ar' ? 'بدون' : 'None' },
    { value: 'solid', label: locale === 'ar' ? 'لون واحد' : 'Solid Color' },
    { value: 'gradient', label: locale === 'ar' ? 'تدرج' : 'Gradient' },
    { value: 'image', label: locale === 'ar' ? 'صورة' : 'Image' },
  ]

  return (
    <div className={compact ? 'space-y-2' : 'space-y-3 border-t border-[var(--border)] pt-4 mt-4'}>
      <p className={`font-medium ${compact ? 'text-xs text-white/70' : 'text-sm text-muted-foreground'}`}>
        {label || (locale === 'ar' ? 'الخلفية' : 'Background')}
      </p>

      <SelectInput
        label={locale === 'ar' ? 'النوع' : 'Type'}
        name={`${idPrefix}_type`}
        value={bgType}
        onChange={handleTypeChange}
        options={typeOptions}
      />

      {bgType === 'solid' && (
        <div className="flex items-center gap-3">
          <label className="text-sm text-muted-foreground">
            {locale === 'ar' ? 'اللون' : 'Color'}
          </label>
          <input
            type="color"
            value={color}
            onChange={handleColorChange}
            className="h-9 w-14 cursor-pointer rounded border border-[var(--border)]"
          />
          <TextInput
            label=""
            name={`${idPrefix}_color`}
            value={color}
            onChange={handleColorChange}
            className="w-24"
          />
        </div>
      )}

      {bgType === 'gradient' && (
        <div className="space-y-3">
          <div className="flex items-center gap-3">
            <label className="text-sm text-muted-foreground w-20">
              {locale === 'ar' ? 'البداية' : 'Start'}
            </label>
            <input
              type="color"
              value={color}
              onChange={handleColorChange}
              className="h-9 w-14 cursor-pointer rounded border border-[var(--border)]"
            />
            <TextInput
              label=""
              name={`${idPrefix}_color`}
              value={color}
              onChange={handleColorChange}
              className="w-24"
            />
          </div>
          <div className="flex items-center gap-3">
            <label className="text-sm text-muted-foreground w-20">
              {locale === 'ar' ? 'النهاية' : 'End'}
            </label>
            <input
              type="color"
              value={colorEnd}
              onChange={handleColorEndChange}
              className="h-9 w-14 cursor-pointer rounded border border-[var(--border)]"
            />
            <TextInput
              label=""
              name={`${idPrefix}_color_end`}
              value={colorEnd}
              onChange={handleColorEndChange}
              className="w-24"
            />
          </div>
          <div
            className="h-8 rounded border border-[var(--border)]"
            style={{ background: `linear-gradient(90deg, ${color} 0%, ${colorEnd} 100%)` }}
          />
        </div>
      )}

      {bgType === 'image' && (
        <div className="space-y-3">
          {image && (
            <div className="relative">
              <img
                src={image}
                alt="Background"
                className="h-24 w-full rounded object-cover"
              />
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
          <div className="flex items-center gap-3">
            <label className="text-sm text-muted-foreground shrink-0">
              {locale === 'ar' ? 'التعتيم' : 'Overlay'} ({overlay}%)
            </label>
            <input
              type="range"
              min="0"
              max="80"
              value={overlay}
              onChange={handleOverlayChange}
              className="flex-1"
            />
          </div>
        </div>
      )}
    </div>
  )
}
