import { useState, useCallback } from 'react'
import { Upload, Trash2 } from 'lucide-react'
import { apiFetch } from '@/lib/apiFetch'
import SelectInput from '@/components/forms/SelectInput'
import LengthUnitField from './builder/LengthUnitField'
import { formatCssLength, parseCssLength, type CssLengthUnit } from '@/lib/cssLength'

type LogoValue = {
  url?: string
  path?: string
  position?: 'left' | 'center' | 'right'
  size?: 'sm' | 'md' | 'lg' | 'custom'
  max_height?: number
  max_height_unit?: CssLengthUnit
}

type Props = {
  value: LogoValue
  onChange: (value: LogoValue) => void
  locale: 'en' | 'ar'
  tenantId: string
  eventId: string
}

const SIZE_HEIGHTS: Record<string, number> = {
  sm: 32,
  md: 48,
  lg: 72,
}

export default function LogoEditor({ value, onChange, locale, tenantId, eventId }: Props) {
  const [uploading, setUploading] = useState(false)

  const url = value.url || ''
  const position = value.position || 'left'
  const size = value.size || 'md'
  const maxHeight = parseCssLength(value.max_height, value.max_height_unit, {
    value: SIZE_HEIGHTS[size] || 48,
    unit: 'px',
  })

  const handleUpload = useCallback(
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
        onChange({
          ...value,
          url: response.url,
          path: response.path,
        })
      } catch (err) {
        console.error('Failed to upload logo:', err)
      } finally {
        setUploading(false)
        e.target.value = ''
      }
    },
    [eventId, tenantId, value, onChange],
  )

  const handleRemove = useCallback(() => {
    onChange({
      ...value,
      url: '',
      path: '',
    })
  }, [value, onChange])

  const handlePositionChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      onChange({ ...value, position: e.target.value as 'left' | 'center' | 'right' })
    },
    [value, onChange],
  )

  const handleSizeChange = useCallback(
    (e: React.ChangeEvent<HTMLSelectElement>) => {
      const newSize = e.target.value as 'sm' | 'md' | 'lg' | 'custom'
      onChange({
        ...value,
        size: newSize,
        max_height: newSize === 'custom' ? maxHeight.value : SIZE_HEIGHTS[newSize],
        max_height_unit: newSize === 'custom' ? maxHeight.unit : 'px',
      })
    },
    [value, onChange, maxHeight],
  )

  const positionOptions = [
    { value: 'left', label: locale === 'ar' ? 'البداية' : 'Start' },
    { value: 'center', label: locale === 'ar' ? 'وسط' : 'Center' },
    { value: 'right', label: locale === 'ar' ? 'النهاية' : 'End' },
  ]

  const sizeOptions = [
    { value: 'sm', label: locale === 'ar' ? 'صغير (32px)' : 'Small (32px)' },
    { value: 'md', label: locale === 'ar' ? 'متوسط (48px)' : 'Medium (48px)' },
    { value: 'lg', label: locale === 'ar' ? 'كبير (72px)' : 'Large (72px)' },
    { value: 'custom', label: locale === 'ar' ? 'مخصص' : 'Custom' },
  ]

  const previewHeightCss =
    size === 'custom'
      ? formatCssLength(maxHeight)
      : `${SIZE_HEIGHTS[size] || 48}px`

  return (
    <div className="space-y-3 border-t border-[var(--border)] pt-4 mt-4">
      <p className="text-sm font-medium text-muted-foreground">
        {locale === 'ar' ? 'الشعار' : 'Logo'}
      </p>

      {url ? (
        <div className="space-y-3">
          <div
            className="relative flex items-center justify-center rounded-lg border border-[var(--border)] bg-muted/30 p-4"
            style={{ minHeight: `calc(${previewHeightCss} + 2rem)` }}
          >
            <img
              src={url}
              alt="Logo"
              className="max-w-full object-contain"
              style={{ maxHeight: previewHeightCss }}
            />
            <button
              type="button"
              onClick={handleRemove}
              className="absolute top-2 end-2 rounded-full bg-red-100 p-1.5 text-red-600 hover:bg-red-200 transition-colors"
              title={locale === 'ar' ? 'إزالة' : 'Remove'}
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>

          <div className="grid gap-3 sm:grid-cols-2">
            <SelectInput
              label={locale === 'ar' ? 'الموضع' : 'Position'}
              name="logo_position"
              value={position}
              onChange={handlePositionChange}
              options={positionOptions}
            />

            <SelectInput
              label={locale === 'ar' ? 'الحجم' : 'Size'}
              name="logo_size"
              value={size}
              onChange={handleSizeChange}
              options={sizeOptions}
            />
          </div>

          {size === 'custom' && (
            <LengthUnitField
              label={locale === 'ar' ? 'الارتفاع الأقصى' : 'Max height'}
              name="logo_max_height"
              value={maxHeight.value}
              unit={maxHeight.unit}
              locale={locale}
              preferredPx={48}
              fallback={{ value: 48, unit: 'px' }}
              onChange={(next) =>
                onChange({
                  ...value,
                  max_height: next.value,
                  max_height_unit: next.unit,
                })
              }
            />
          )}
        </div>
      ) : (
        <label className="flex flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-[var(--border)] p-6 cursor-pointer hover:border-[var(--brand)] hover:bg-muted/30 transition-colors">
          <Upload className="h-8 w-8 text-muted-foreground" />
          <span className="text-sm text-muted-foreground">
            {uploading
              ? (locale === 'ar' ? 'جاري الرفع...' : 'Uploading...')
              : (locale === 'ar' ? 'انقر لرفع الشعار' : 'Click to upload logo')}
          </span>
          <input
            type="file"
            accept="image/*"
            onChange={handleUpload}
            disabled={uploading}
            className="hidden"
          />
        </label>
      )}
    </div>
  )
}
