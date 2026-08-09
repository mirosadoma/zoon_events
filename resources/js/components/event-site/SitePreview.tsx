import { useState } from 'react'
import { X, Monitor, Tablet, Smartphone, ExternalLink } from 'lucide-react'
import BlockCanvas, { type CanvasBlock } from './BlockCanvas'
import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'

type Device = 'desktop' | 'tablet' | 'mobile'

type Props = {
  blocks: CanvasBlock[]
  event: {
    id: string
    slug: string
    name: { en: string; ar: string }
  }
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBackground?: SiteBackground
  publicUrl?: string
  previewData?: {
    agenda?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
    speakers?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
    venue?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
  }
  onClose: () => void
}

function deviceWidth(device: Device): string {
  if (device === 'mobile') return 'max-w-[390px]'
  if (device === 'tablet') return 'max-w-[768px]'
  return 'max-w-[1100px]'
}

export default function SitePreview({
  blocks,
  event,
  locale,
  registerUrl,
  siteBackground,
  publicUrl,
  previewData,
  onClose,
}: Props) {
  const direction = locale === 'ar' ? 'rtl' : 'ltr'
  const [device, setDevice] = useState<Device>('desktop')
  const bgStyle = backgroundStyle(siteBackground)
  const bgOverlay = backgroundOverlayStyle(siteBackground)
  const hasBg = siteBackground?.type && siteBackground.type !== 'none'

  const devices: Array<{ id: Device; icon: typeof Monitor; label: string }> = [
    { id: 'desktop', icon: Monitor, label: 'Desktop' },
    { id: 'tablet', icon: Tablet, label: 'Tablet' },
    { id: 'mobile', icon: Smartphone, label: 'Mobile' },
  ]

  return (
    <div className="site-preview-root fixed inset-0 z-[70] flex flex-col bg-[#0a0a12]">
      <header className="flex shrink-0 items-center justify-between gap-4 border-b border-white/10 bg-[#12121f] px-4 py-3">
        <div className="min-w-0">
          <p className="text-xs font-medium uppercase tracking-wider text-white/40">
            {locale === 'ar' ? 'معاينة الموقع' : 'Site preview'}
          </p>
          <h2 className="truncate text-sm font-semibold text-white">{event.name[locale]}</h2>
        </div>

        <div className="flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 p-1">
          {devices.map(({ id, icon: Icon, label }) => (
            <button
              key={id}
              type="button"
              title={label}
              onClick={() => setDevice(id)}
              className={`rounded-md p-2 transition ${
                device === id ? 'bg-violet-600 text-white' : 'text-white/50 hover:bg-white/10 hover:text-white'
              }`}
            >
              <Icon className="h-4 w-4" />
            </button>
          ))}
        </div>

        <div className="flex items-center gap-2">
          {publicUrl && (
            <a
              href={publicUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="hidden items-center gap-1.5 rounded-md border border-white/10 px-3 py-1.5 text-xs text-white/70 hover:bg-white/10 hover:text-white sm:flex"
            >
              <ExternalLink className="h-3.5 w-3.5" />
              {locale === 'ar' ? 'فتح الموقع' : 'Open live'}
            </a>
          )}
          <button
            type="button"
            onClick={onClose}
            className="rounded-md border border-white/10 p-2 text-white/70 hover:bg-white/10 hover:text-white"
            title={locale === 'ar' ? 'إغلاق' : 'Close'}
          >
            <X className="h-5 w-5" />
          </button>
        </div>
      </header>

      <div className="site-preview-stage relative flex-1 overflow-auto">
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_50%_0%,rgb(139_92_246/0.08),transparent_50%)]" />
        <div className="flex min-h-full items-start justify-center p-6 md:p-10">
          <div
            className={`site-preview-device relative w-full overflow-hidden rounded-xl shadow-2xl ring-1 ring-white/10 transition-all duration-300 ${deviceWidth(device)} ${hasBg ? '' : 'bg-background'}`}
            dir={direction}
            lang={locale}
            style={{ direction, minHeight: device === 'mobile' ? '70vh' : '85vh', ...bgStyle }}
          >
            {bgOverlay && <div className="pointer-events-none absolute inset-0 z-0" style={bgOverlay} />}
            <div className="relative z-[1]">
              <BlockCanvas
                blocks={blocks}
                locale={locale}
                registerUrl={registerUrl}
                siteBaseUrl={publicUrl}
                previewData={previewData}
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
