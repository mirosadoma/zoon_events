import LocalizedLink from '@/components/routing/LocalizedLink'
import {
  ArrowLeft,
  Monitor,
  Tablet,
  Smartphone,
  Eye,
  Save,
  Upload,
  History,
  Globe,
  Loader2,
} from 'lucide-react'

type Viewport = 'desktop' | 'tablet' | 'mobile'

type Props = {
  eventName: string
  locale: 'en' | 'ar'
  editLocale: 'en' | 'ar'
  onEditLocaleChange: (locale: 'en' | 'ar') => void
  viewport: Viewport
  onViewportChange: (v: Viewport) => void
  status: string
  saving: boolean
  publishing: boolean
  onSave: () => void
  onPublish: () => void
  onPreview: () => void
  onVersions: () => void
  publicUrl?: string | null
  backHref: string
}

export default function BuilderTopBar({
  eventName,
  locale,
  editLocale,
  onEditLocaleChange,
  viewport,
  onViewportChange,
  status,
  saving,
  publishing,
  onSave,
  onPublish,
  onPreview,
  onVersions,
  publicUrl,
  backHref,
}: Props) {
  return (
    <header className="flex h-14 shrink-0 items-center gap-3 border-b border-white/10 bg-[#1a1a2e] px-4">
      <LocalizedLink
        href={backHref}
        className="inline-flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-white/70 transition hover:bg-white/5 hover:text-white"
      >
        <ArrowLeft className="h-4 w-4" />
        <span className="hidden sm:inline">{locale === 'ar' ? 'رجوع' : 'Back'}</span>
      </LocalizedLink>

      <div className="h-6 w-px bg-white/10" />

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold">{eventName}</p>
        <p className="text-[11px] text-white/45">
          {locale === 'ar' ? 'محرر الصفحة' : 'Visual Page Builder'}
          {' · '}
          <span className={
            status === 'published' ? 'text-emerald-400' : status === 'unpublished' ? 'text-amber-400' : 'text-white/60'
          }>
            {status === 'published'
              ? (locale === 'ar' ? 'منشور' : 'Published')
              : status === 'unpublished'
                ? (locale === 'ar' ? 'غير منشور' : 'Unpublished')
                : (locale === 'ar' ? 'مسودة' : 'Draft')}
          </span>
        </p>
      </div>

      <div className="hidden items-center rounded-lg border border-white/10 bg-white/5 p-0.5 md:flex">
        <button
          type="button"
          onClick={() => onEditLocaleChange('en')}
          className={`rounded-md px-2.5 py-1 text-xs font-medium ${editLocale === 'en' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'}`}
        >
          EN
        </button>
        <button
          type="button"
          onClick={() => onEditLocaleChange('ar')}
          className={`rounded-md px-2.5 py-1 text-xs font-medium ${editLocale === 'ar' ? 'bg-violet-600 text-white' : 'text-white/60 hover:text-white'}`}
        >
          AR
        </button>
      </div>

      <div className="flex items-center rounded-lg border border-white/10 bg-white/5 p-0.5">
        {([
          ['desktop', Monitor],
          ['tablet', Tablet],
          ['mobile', Smartphone],
        ] as const).map(([id, Icon]) => (
          <button
            key={id}
            type="button"
            title={id}
            onClick={() => onViewportChange(id)}
            className={`rounded-md p-2 transition ${viewport === id ? 'bg-violet-600 text-white' : 'text-white/50 hover:text-white'}`}
          >
            <Icon className="h-4 w-4" />
          </button>
        ))}
      </div>

      <div className="flex items-center gap-1.5">
        {publicUrl && status === 'published' && (
          <a
            href={publicUrl}
            target="_blank"
            rel="noreferrer"
            className="hidden items-center gap-1.5 rounded-md px-2.5 py-2 text-xs text-white/70 hover:bg-white/5 hover:text-white sm:inline-flex"
          >
            <Globe className="h-3.5 w-3.5" />
            {locale === 'ar' ? 'فتح الموقع' : 'View Site'}
          </a>
        )}
        <button type="button" onClick={onVersions} className="rounded-md p-2 text-white/60 hover:bg-white/5 hover:text-white" title="Versions">
          <History className="h-4 w-4" />
        </button>
        <button type="button" onClick={onPreview} className="inline-flex items-center gap-1.5 rounded-md px-2.5 py-2 text-xs text-white/80 hover:bg-white/5">
          <Eye className="h-3.5 w-3.5" />
          <span className="hidden sm:inline">{locale === 'ar' ? 'معاينة' : 'Preview'}</span>
        </button>
        <button
          type="button"
          onClick={onSave}
          disabled={saving}
          className="inline-flex items-center gap-1.5 rounded-md border border-white/15 bg-white/5 px-3 py-2 text-xs font-medium text-white hover:bg-white/10 disabled:opacity-50"
        >
          {saving ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Save className="h-3.5 w-3.5" />}
          {locale === 'ar' ? 'حفظ' : 'Save'}
        </button>
        <button
          type="button"
          onClick={onPublish}
          disabled={publishing}
          className="inline-flex items-center gap-1.5 rounded-md bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-500 disabled:opacity-50"
        >
          {publishing ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Upload className="h-3.5 w-3.5" />}
          {locale === 'ar' ? 'نشر' : 'Publish'}
        </button>
      </div>
    </header>
  )
}
