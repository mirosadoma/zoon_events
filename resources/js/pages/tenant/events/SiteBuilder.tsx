import { useCallback, useEffect, useMemo, useState } from 'react'
import { arrayMove } from '@dnd-kit/sortable'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import BlockEditor from '@/components/event-site/BlockEditor'
import BlockCanvas from '@/components/event-site/BlockCanvas'
import SitePreview from '@/components/event-site/SitePreview'
import VersionHistory from '@/components/event-site/VersionHistory'
import AssistantConfigPanel from '@/components/ai/AssistantConfigPanel'
import FormSubmissionsPanel from '@/components/event-site/FormSubmissionsPanel'
import BackgroundEditor from '@/components/event-site/BackgroundEditor'
import BlockActionsToolbar from '@/components/event-site/BlockActionsToolbar'
import LogoEditor from '@/components/event-site/LogoEditor'
import SiteBuilderShell from '@/components/event-site/builder/SiteBuilderShell'
import BuilderTopBar from '@/components/event-site/builder/BuilderTopBar'
import ElementLibrary from '@/components/event-site/builder/ElementLibrary'
import ElementLayoutQuickPanel from '@/components/event-site/builder/ElementLayoutQuickPanel'
import LayoutDropPicker from '@/components/event-site/builder/LayoutDropPicker'
import PageStructurePanel from '@/components/event-site/builder/PageStructurePanel'
import PropertiesInspector, {
  buildPropertiesTabs,
  type PropertiesTabId,
} from '@/components/event-site/builder/PropertiesInspector'
import { BuilderInspectorSection } from '@/components/event-site/builder/BuilderPanelTabs'
import BuilderDragProvider from '@/components/event-site/builder/BuilderDragProvider'
import { SECTION_PRESETS, presetElementsWithIds } from '@/lib/sectionPresets'
import { columnGridPlacement } from '@/lib/layoutDropPresets'
import { applyLayoutPresetToSection } from '@/lib/sectionLayoutUtils'
import {
  resolvePaletteBlock,
  type BuilderDragData,
  isPaletteBlock,
  isPaletteElement,
  isSectionElement,
} from '@/lib/siteBuilderDnd'
import { asElementArray, createSectionElement } from '@/lib/sectionElementFactory'
import { defaultFreeformPlacement } from '@/lib/sectionFreeformLayout'
import { inheritElementPlacement, insertElementAfter, insertNewAfter, mergeElementLayoutPatch } from '@/lib/elementLayoutStyle'
import { Plus, Pencil, Trash2, X, LayoutGrid, FileStack, Settings, Inbox } from 'lucide-react'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'
import { backgroundStyle, backgroundOverlayStyle } from '@/lib/siteBackgroundStyle'

type LogoValue = {
  url?: string
  path?: string
  position?: 'left' | 'center' | 'right'
  size?: 'sm' | 'md' | 'lg' | 'custom'
  max_height?: number
}

type LocalizedName = { en: string; ar: string }

type SitePage = {
  id: string
  slug: string
  title_en: string
  title_ar: string
  is_home: boolean
  background?: SiteBackground
}

type SiteBlock = {
  id: string
  type: string
  visible: boolean
  page_id?: string
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
}

type LiveVersion = {
  id: number
  version: number
  published_at: string
  blocks_hash: string
  block_count: number
}

type SiteSettings = {
  show_assistant?: boolean
  page_mode?: 'single' | 'multi'
  pages?: SitePage[]
  site_background?: SiteBackground
  logo?: LogoValue
  public_path_prefix?: 'e' | 'events'
  public_slug?: string
}

type SiteData = {
  status: string
  page_mode: string
  draft_revision: number
  draft_blocks: SiteBlock[]
  settings: SiteSettings
  live_version: LiveVersion | null
  public_url: string
  publish_blockers: string[]
}

type Props = {
  tenantId: string
  event: {
    id: string
    slug: string
    name: LocalizedName
    status: string
    timezone: string
  }
  site: SiteData
  preview?: {
    agenda?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
    speakers?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
    venue?: { en?: Record<string, unknown>; ar?: Record<string, unknown> }
  }
  locale: string
}

type Viewport = 'desktop' | 'tablet' | 'mobile'
type LeftTab = 'pages' | 'settings' | 'submissions'
type RightTab = PropertiesTabId

const BLOCK_TYPE_LABELS: Record<string, { en: string; ar: string }> = {
  header: { en: 'Header', ar: 'رأس الصفحة' },
  hero: { en: 'Hero', ar: 'البطل' },
  about: { en: 'About', ar: 'حول' },
  agenda: { en: 'Agenda', ar: 'جدول الأعمال' },
  speakers: { en: 'Speakers', ar: 'المتحدثون' },
  venue: { en: 'Venue', ar: 'الموقع' },
  faq: { en: 'FAQ', ar: 'الأسئلة الشائعة' },
  sponsors: { en: 'Sponsors', ar: 'الرعاة' },
  gallery: { en: 'Gallery', ar: 'المعرض' },
  section: { en: 'Grid section', ar: 'قسم شبكي' },
  register_cta: { en: 'Register CTA', ar: 'دعوة للتسجيل' },
  footer: { en: 'Footer', ar: 'تذييل' },
  media_text: { en: 'Media + Text', ar: 'وسائط ونص' },
  image_showcase: { en: 'Carousel / Showcase', ar: 'سلايدر / معرض' },
  form: { en: 'Form', ar: 'نموذج' },
}

const DEFAULT_CONTENT: Record<string, { en: Record<string, unknown>; ar: Record<string, unknown>; options: Record<string, unknown> }> = {
  header: {
    en: { brand: 'Event', links: [], cta_label: 'Register' },
    ar: { brand: 'الحدث', links: [], cta_label: 'تسجيل' },
    options: { style: 'solid', sticky: true, show_cta: true, cta_href: 'registration', mobile_menu: true },
  },
  hero: {
    en: { title: '', subtitle: '' },
    ar: { title: '', subtitle: '' },
    options: {
      background_style: 'gradient',
      text_alignment: 'center',
      show_date: true,
      show_location: true,
    },
  },
  footer: {
    en: { tagline: 'Event', columns: [], copyright: '', social_links: [] },
    ar: { tagline: 'الحدث', columns: [], copyright: '', social_links: [] },
    options: {
      design: 'columns',
      show_social: false,
      show_brand: true,
      show_copyright: true,
      show_logo: true,
    },
  },
  section: {
    en: { title: 'New section', subtitle: '', elements: [] },
    ar: { title: 'قسم جديد', subtitle: '', elements: [] },
    options: { columns: 12, gap: 'md', background: { type: 'none' }, padding: 'lg', max_width: '6xl', align: 'center', layout_preset: '2' },
  },
  media_text: {
    en: { title: 'Our Story', body: 'Tell your story here...', button_label: 'Learn More', button_href: '' },
    ar: { title: 'قصتنا', body: 'اكتب قصتك هنا...', button_label: 'اعرف المزيد', button_href: '' },
    options: { layout: 'image_left', background: { type: 'none' } },
  },
  image_showcase: {
    en: { title: 'Gallery', subtitle: 'Explore our collection', items: [] },
    ar: { title: 'المعرض', subtitle: 'استكشف مجموعتنا', items: [] },
    options: { display: 'grid', columns: 3, autoplay: false, background: { type: 'none' } },
  },
  form: {
    en: { title: 'Contact Us', description: 'Get in touch with us', submit_label: 'Submit', success_message: 'Thank you!', fields: [] },
    ar: { title: 'اتصل بنا', description: 'تواصل معنا', submit_label: 'إرسال', success_message: 'شكراً لك!', fields: [] },
    options: { background: { type: 'none' } },
  },
}

const DEFAULT_PAGE: SitePage = {
  id: 'home',
  slug: '',
  title_en: 'Home',
  title_ar: 'الرئيسية',
  is_home: true,
}

function generateBlockId(type: string): string {
  return `b_${type}_${Math.random().toString(36).substring(2, 10)}`
}

function generatePageId(): string {
  return `p_${Math.random().toString(36).substring(2, 10)}`
}

function viewportWidth(viewport: Viewport): string {
  if (viewport === 'mobile') return 'max-w-[390px]'
  if (viewport === 'tablet') return 'max-w-[768px]'
  return 'max-w-5xl'
}

const REGISTER_OK_STATUSES = new Set(['published', 'registration_open', 'registration_closed', 'live'])

function computePublishBlockers(
  blocks: SiteBlock[],
  eventStatus: string,
  uiLocale: 'en' | 'ar',
): string[] {
  const messages: string[] = []
  const visible = blocks.filter((block) => block.visible !== false)

  if (visible.length === 0) {
    messages.push(
      uiLocale === 'ar'
        ? 'مطلوب قسم مرئي واحد على الأقل قبل النشر.'
        : 'At least one visible section is required before publishing.',
    )
  }

  const registerCta = visible.find((block) => block.type === 'register_cta')
  if (registerCta && !REGISTER_OK_STATUSES.has(eventStatus)) {
    messages.push(
      uiLocale === 'ar'
        ? 'دعوة التسجيل لا تشير إلى صفحة تسجيل مفتوحة.'
        : 'The registration call to action does not point at an open registration page.',
    )
  }

  return messages
}

function insertBlockAtPageIndex(
  blocks: SiteBlock[],
  newBlock: SiteBlock,
  indexInPage: number,
  pageId: string,
): SiteBlock[] {
  const pageBlocks = blocks.filter((b) => (b.page_id || 'home') === pageId)
  const targetBlock = pageBlocks[indexInPage]
  if (!targetBlock) {
    const footerIndex = blocks.findIndex((block) => block.type === 'footer')
    if (footerIndex >= 0) {
      const next = [...blocks]
      next.splice(footerIndex, 0, newBlock)
      return next
    }
    return [...blocks, newBlock]
  }
  const insertAt = blocks.findIndex((b) => b.id === targetBlock.id)
  if (insertAt === -1) return [...blocks, newBlock]
  const next = [...blocks]
  next.splice(insertAt, 0, newBlock)
  return next
}

export default function SiteBuilder({ tenantId, event, site: initialSite, preview, locale: pageLocale }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()

  const [blocks, setBlocks] = useState<SiteBlock[]>(initialSite.draft_blocks)
  const [settings, setSettings] = useState<SiteSettings>(initialSite.settings)
  const [draftRevision, setDraftRevision] = useState(initialSite.draft_revision)
  const [siteStatus, setSiteStatus] = useState(initialSite.status)
  const [liveVersion, setLiveVersion] = useState<LiveVersion | null>(initialSite.live_version)
  const [publishBlockers, setPublishBlockers] = useState<string[]>(initialSite.publish_blockers)
  const [saving, setSaving] = useState(false)
  const [publishing, setPublishing] = useState(false)
  const [unpublishing, setUnpublishing] = useState(false)

  const [selectedBlockId, setSelectedBlockId] = useState<string | null>(null)
  const [selectedSectionElementId, setSelectedSectionElementId] = useState<string | null>(null)
  const [editLocale, setEditLocale] = useState<'en' | 'ar'>(pageLocale === 'ar' ? 'ar' : 'en')
  const [viewport, setViewport] = useState<Viewport>('desktop')
  const [showVersions, setShowVersions] = useState(false)
  const [showPreview, setShowPreview] = useState(false)
  const [confirmUnpublish, setConfirmUnpublish] = useState(false)

  const [leftTab, setLeftTab] = useState<LeftTab | null>(null)
  const [rightTab, setRightTab] = useState<RightTab>('structure')
  const [panelFocus, setPanelFocus] = useState<'element' | 'structure' | 'site'>('structure')
  const [widgetSearch, setWidgetSearch] = useState('')
  const [showLayoutPicker, setShowLayoutPicker] = useState(false)
  const [layoutInsertIndex, setLayoutInsertIndex] = useState(0)
  const [pendingDragData, setPendingDragData] = useState<BuilderDragData | null>(null)
  const [relayoutBlockId, setRelayoutBlockId] = useState<string | null>(null)
  const [currentPageId, setCurrentPageId] = useState('home')
  const [editingPage, setEditingPage] = useState<SitePage | null>(null)
  const [showPageEditor, setShowPageEditor] = useState(false)

  const pages: SitePage[] = settings.pages?.length ? settings.pages : [DEFAULT_PAGE]
  const pageMode = settings.page_mode || 'single'
  const siteBackground = settings.site_background || { type: 'none' as const }
  const siteBgStyle = backgroundStyle(siteBackground)
  const siteBgOverlay = backgroundOverlayStyle(siteBackground)
  const hasSiteBg = siteBackground.type && siteBackground.type !== 'none'
  const siteLogo: LogoValue = settings.logo || { url: '', path: '', position: 'left', size: 'md' }
  const currentPage = pages.find((p) => p.id === currentPageId) || pages[0]

  const filteredBlocks = useMemo(() => {
    return blocks.filter((block) => {
      if (block.type === 'header' || block.type === 'footer') return true
      const blockPageId = block.page_id || 'home'
      return blockPageId === currentPageId
    })
  }, [blocks, currentPageId])

  const selectedBlock = useMemo(
    () => blocks.find((b) => b.id === selectedBlockId) ?? null,
    [blocks, selectedBlockId],
  )

  const selectedSectionElement = useMemo(() => {
    if (!selectedBlock || selectedBlock.type !== 'section' || !selectedSectionElementId) return null
    const content = editLocale === 'ar' ? selectedBlock.content_ar : selectedBlock.content_en
    return asElementArray(content.elements).find((e) => e.id === selectedSectionElementId) ?? null
  }, [editLocale, selectedBlock, selectedSectionElementId])

  const registerUrl = localizedPath(`/events/${event.slug}/register`)

  useEffect(() => {
    setBlocks(initialSite.draft_blocks)
    setDraftRevision(initialSite.draft_revision)
    setSiteStatus(initialSite.status)
    setLiveVersion(initialSite.live_version)
    setPublishBlockers(initialSite.publish_blockers)
    setSettings(initialSite.settings)
  }, [initialSite])

  const livePublishBlockers = useMemo(
    () => computePublishBlockers(blocks, event.status, locale === 'ar' ? 'ar' : 'en'),
    [blocks, event.status, locale],
  )

  useEffect(() => {
    setPublishBlockers(livePublishBlockers)
  }, [livePublishBlockers])

  const updateSettings = useCallback((updates: Partial<SiteSettings>) => {
    setSettings((prev) => ({ ...prev, ...updates }))
  }, [])

  const saveDraft = useCallback(async () => {
    setSaving(true)
    try {
      const response = await apiFetch<{
        draft_revision: number
        draft_blocks: SiteBlock[]
        settings: SiteSettings
      }>(`/api/v1/tenant/events/${event.id}/site/draft`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { draft_revision: draftRevision, blocks, settings },
      })
      setDraftRevision(response.draft_revision)
      toast(t('siteBuilderSaved') || 'Draft saved.', 'success')
    } catch (err) {
      if (err instanceof ApiFetchError && err.code === 'event_site.stale_revision') {
        toast(t('siteBuilderStale') || 'Someone else saved. Reload to get the latest.', 'error')
      } else {
        toast(err instanceof ApiFetchError ? err.message : 'Failed to save.', 'error')
      }
    } finally {
      setSaving(false)
    }
  }, [blocks, draftRevision, event.id, settings, t, tenantId, toast])

  const publish = useCallback(async () => {
    setPublishing(true)
    try {
      const saved = await apiFetch<{
        draft_revision: number
        draft_blocks: SiteBlock[]
        settings: SiteSettings
      }>(`/api/v1/tenant/events/${event.id}/site/draft`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { draft_revision: draftRevision, blocks, settings },
      })
      setDraftRevision(saved.draft_revision)

      const response = await apiFetch<{
        version: number
        published_at: string
        blocks_hash: string
        public_url: string
        already_published: boolean
      }>(`/api/v1/tenant/events/${event.id}/site/publish`, {
        method: 'POST',
        tenantId,
        idempotency: true,
      })
      setLiveVersion({
        id: response.version,
        version: response.version,
        published_at: response.published_at,
        blocks_hash: response.blocks_hash,
        block_count: blocks.length,
      })
      setSiteStatus('published')
      setPublishBlockers([])
      toast(t('siteBuilderPublished') || 'Site published!', 'success')
    } catch (err) {
      if (err instanceof ApiFetchError && err.publishBlockers.length > 0) {
        setPublishBlockers(err.publishBlockers)
        toast(err.publishBlockers.join(' · '), 'error')
      } else {
        toast(err instanceof ApiFetchError ? err.message : 'Failed to publish.', 'error')
      }
    } finally {
      setPublishing(false)
    }
  }, [blocks, draftRevision, event.id, settings, t, tenantId, toast])

  const unpublish = useCallback(async () => {
    setUnpublishing(true)
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/site/unpublish`, {
        method: 'POST',
        tenantId,
        idempotency: true,
      })
      setSiteStatus('unpublished')
      toast(t('siteBuilderUnpublished') || 'Site unpublished.', 'success')
    } catch (err) {
      toast(err instanceof ApiFetchError ? err.message : 'Failed to unpublish.', 'error')
    } finally {
      setUnpublishing(false)
      setConfirmUnpublish(false)
    }
  }, [event.id, t, tenantId, toast])

  const insertBlockAtIndex = useCallback((rawType: string, indexInPage: number, presetId?: string) => {
    if (presetId) {
      const preset = SECTION_PRESETS.find((p) => p.id === presetId)
      if (!preset) return

      const sectionDefaults = DEFAULT_CONTENT.section
      const newBlock: SiteBlock = {
        id: generateBlockId('section'),
        type: 'section',
        visible: true,
        page_id: currentPageId,
        content_en: {
          title: preset.en.title,
          subtitle: preset.en.subtitle,
          elements: presetElementsWithIds(preset.en.elements),
        },
        content_ar: {
          title: preset.ar.title,
          subtitle: preset.ar.subtitle,
          elements: presetElementsWithIds(preset.ar.elements),
        },
        options: { ...sectionDefaults.options, ...preset.options },
        refs: {},
      }

      setBlocks((prev) => insertBlockAtPageIndex(prev, newBlock, indexInPage, currentPageId))
      setSelectedBlockId(newBlock.id)
      setSelectedSectionElementId(null)
      return
    }

    const { type: resolvedType, optionsPatch } = resolvePaletteBlock(rawType)
    const defaults = DEFAULT_CONTENT[resolvedType]
    const eventNameEn = event.name.en || 'Event'
    const eventNameAr = event.name.ar || eventNameEn
    const contentEn =
      resolvedType === 'hero'
        ? { ...(defaults?.en ?? {}), title: eventNameEn }
        : (defaults?.en ?? {})
    const contentAr =
      resolvedType === 'hero'
        ? { ...(defaults?.ar ?? {}), title: eventNameAr }
        : (defaults?.ar ?? {})
    const newBlock: SiteBlock = {
      id: generateBlockId(resolvedType),
      type: resolvedType,
      visible: true,
      page_id: currentPageId,
      content_en: contentEn,
      content_ar: contentAr,
      options: { ...defaults?.options ?? {}, ...optionsPatch },
      refs: {},
    }

    setBlocks((prev) => {
      if (resolvedType === 'header') {
        return [newBlock, ...prev.filter((block) => block.type !== 'header')]
      }
      if (resolvedType === 'footer') {
        return [...prev.filter((block) => block.type !== 'footer'), newBlock]
      }
      if (resolvedType === 'hero') {
        const withoutHero = prev.filter((block) => block.type !== 'hero')
        const headerIndex = withoutHero.findIndex((block) => block.type === 'header')
        if (headerIndex >= 0) {
          const next = [...withoutHero]
          next.splice(headerIndex + 1, 0, newBlock)
          return next
        }
        return [newBlock, ...withoutHero]
      }
      return insertBlockAtPageIndex(prev, newBlock, indexInPage, currentPageId)
    })
    setSelectedBlockId(newBlock.id)
    setSelectedSectionElementId(null)
  }, [currentPageId, event.name.ar, event.name.en])

  const addBlock = useCallback((type: string) => {
    const pageBlocks = blocks.filter((b) => (b.page_id || 'home') === currentPageId)
    insertBlockAtIndex(type, pageBlocks.length)
  }, [blocks, currentPageId, insertBlockAtIndex])

  const updateBlock = useCallback(
    (id: string, updates: Partial<SiteBlock> | ((prev: SiteBlock) => Partial<SiteBlock>)) => {
      setBlocks((prev) =>
        prev.map((b) => {
          if (b.id !== id) return b
          const patch = typeof updates === 'function' ? updates(b) : updates
          return { ...b, ...patch }
        }),
      )
    },
    [],
  )

  const updateSelectedSectionElementLayout = useCallback(
    (patch: Record<string, unknown>) => {
      if (!selectedBlock || selectedBlock.type !== 'section' || !selectedSectionElementId) return
      const apply = (content: Record<string, unknown>) => {
        const raw = content.elements
        if (!Array.isArray(raw)) return content
        return {
          ...content,
          elements: raw.map((item) => {
            if (typeof item !== 'object' || item === null) return item
            const el = item as Record<string, unknown>
            if (el.id !== selectedSectionElementId) return item
            return mergeElementLayoutPatch(el, patch)
          }),
        }
      }
      updateBlock(selectedBlock.id, {
        content_en: apply(selectedBlock.content_en as Record<string, unknown>),
        content_ar: apply(selectedBlock.content_ar as Record<string, unknown>),
      })
    },
    [selectedBlock, selectedSectionElementId, updateBlock],
  )

  const removeBlock = useCallback((id: string) => {
    setBlocks((prev) => prev.filter((b) => b.id !== id))
    if (selectedBlockId === id) setSelectedBlockId(null)
    if (selectedSectionElementId) setSelectedSectionElementId(null)
  }, [selectedBlockId, selectedSectionElementId])

  const moveBlock = useCallback((fromIndex: number, toIndex: number) => {
    const filteredIds = filteredBlocks.map((b) => b.id)
    const fromId = filteredIds[fromIndex]
    const toId = filteredIds[toIndex]

    setBlocks((prev) => {
      const actualFromIndex = prev.findIndex((b) => b.id === fromId)
      const actualToIndex = prev.findIndex((b) => b.id === toId)
      if (actualFromIndex === -1 || actualToIndex === -1) return prev
      return arrayMove(prev, actualFromIndex, actualToIndex)
    })
  }, [filteredBlocks])

  const reorderBlocksById = useCallback((activeId: string, overId: string) => {
    setBlocks((prev) => {
      const from = prev.findIndex((b) => b.id === activeId)
      const to = prev.findIndex((b) => b.id === overId)
      if (from === -1 || to === -1) return prev
      return arrayMove(prev, from, to)
    })
  }, [])

  const addElementToSection = useCallback((blockId: string, kind: string) => {
    let newElementId: string | null = null
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId || block.type !== 'section') return block
        const enEls = asElementArray(block.content_en.elements)
        const arEls = asElementArray(block.content_ar.elements)
        const layoutMode = block.options.layout_mode === 'freeform' ? 'freeform' : 'grid'
        const newEn = createSectionElement(
          kind,
          'en',
          enEls.length,
          event.name[editLocale] ?? event.name.en,
          layoutMode,
        )
        const newAr = {
          ...createSectionElement(
            kind,
            'ar',
            arEls.length,
            event.name[editLocale] ?? event.name.ar,
            layoutMode,
          ),
          id: newEn.id,
        }
        newElementId = newEn.id
        return {
          ...block,
          content_en: { ...block.content_en, elements: [...enEls, newEn] },
          content_ar: { ...block.content_ar, elements: [...arEls, newAr] },
        }
      }),
    )
    setSelectedBlockId(blockId)
    if (newElementId) setSelectedSectionElementId(newElementId)
  }, [editLocale, event.name])

  const addElementAfterTarget = useCallback(
    (blockId: string, kind: string, afterElementId: string) => {
      let newElementId: string | null = null
      setBlocks((prev) =>
        prev.map((block) => {
          if (block.id !== blockId || block.type !== 'section') return block
          const enEls = asElementArray(block.content_en.elements)
          const arEls = asElementArray(block.content_ar.elements)
          const afterEn = enEls.find((e) => e.id === afterElementId)
          if (!afterEn) return block

          const layoutMode = block.options.layout_mode === 'freeform' ? 'freeform' : 'grid'
          const placement = inheritElementPlacement(afterEn)
          const newEn = {
            ...createSectionElement(
              kind,
              'en',
              enEls.length,
              event.name[editLocale] ?? event.name.en,
              layoutMode,
            ),
            ...placement,
          }
          const newAr = {
            ...createSectionElement(
              kind,
              'ar',
              arEls.length,
              event.name[editLocale] ?? event.name.ar,
              layoutMode,
            ),
            id: newEn.id,
            ...placement,
          }
          newElementId = newEn.id

          const nextEn = insertNewAfter(enEls, newEn, afterElementId).map((e, i) => ({ ...e, order: i }))
          const nextAr = insertNewAfter(arEls, newAr, afterElementId).map((e, i) => ({ ...e, order: i }))

          return {
            ...block,
            content_en: { ...block.content_en, elements: nextEn },
            content_ar: { ...block.content_ar, elements: nextAr },
          }
        }),
      )
      setSelectedBlockId(blockId)
      if (newElementId) setSelectedSectionElementId(newElementId)
    },
    [editLocale, event.name],
  )

  const addElementToTargetSection = useCallback((kind: string) => {
    const target =
      blocks.find((b) => b.id === selectedBlockId && b.type === 'section') ??
      blocks.find((b) => b.type === 'section' && (b.page_id || 'home') === currentPageId)

    if (target) {
      addElementToSection(target.id, kind)
      return
    }

    const sectionDefaults = DEFAULT_CONTENT.section
    const newEn = createSectionElement(kind, 'en', 0, event.name[editLocale] ?? event.name.en)
    const newAr = { ...createSectionElement(kind, 'ar', 0, event.name[editLocale] ?? event.name.ar), id: newEn.id }
    const newBlock: SiteBlock = {
      id: generateBlockId('section'),
      type: 'section',
      visible: true,
      page_id: currentPageId,
      content_en: { title: '', subtitle: '', elements: [newEn] },
      content_ar: { title: '', subtitle: '', elements: [newAr] },
      options: sectionDefaults.options,
      refs: {},
    }
    const pageBlocks = blocks.filter((b) => (b.page_id || 'home') === currentPageId)
    setBlocks((prev) => insertBlockAtPageIndex(prev, newBlock, pageBlocks.length, currentPageId))
    setSelectedBlockId(newBlock.id)
    setSelectedSectionElementId(newEn.id)
  }, [addElementToSection, blocks, currentPageId, selectedBlockId])

  const moveElementBetweenSections = useCallback((fromBlockId: string, elementId: string, toBlockId: string) => {
    setBlocks((prev) => {
      const fromBlock = prev.find((b) => b.id === fromBlockId)
      const toBlock = prev.find((b) => b.id === toBlockId)
      if (!fromBlock || !toBlock || fromBlock.type !== 'section' || toBlock.type !== 'section') return prev

      const fromEn = asElementArray(fromBlock.content_en.elements)
      const fromAr = asElementArray(fromBlock.content_ar.elements)
      const enIdx = fromEn.findIndex((e) => e.id === elementId)
      const arIdx = fromAr.findIndex((e) => e.id === elementId)
      if (enIdx === -1) return prev

      const enEl = fromEn[enIdx]
      const arEl = arIdx >= 0 ? fromAr[arIdx] : enEl

      const nextFromEn = fromEn.filter((e) => e.id !== elementId)
      const nextFromAr = fromAr.filter((e) => e.id !== elementId)

      return prev.map((block) => {
        if (block.id === fromBlockId) {
          return {
            ...block,
            content_en: { ...block.content_en, elements: nextFromEn.map((e, i) => ({ ...e, order: i })) },
            content_ar: { ...block.content_ar, elements: nextFromAr.map((e, i) => ({ ...e, order: i })) },
          }
        }
        if (block.id === toBlockId) {
          const toEn = asElementArray(block.content_en.elements)
          const toAr = asElementArray(block.content_ar.elements)
          const order = toEn.length
          const isFreeform = block.options.layout_mode === 'freeform'
          const placement = isFreeform ? defaultFreeformPlacement(order) : {}
          return {
            ...block,
            content_en: { ...block.content_en, elements: [...toEn, { ...enEl, order, ...placement }] },
            content_ar: { ...block.content_ar, elements: [...toAr, { ...arEl, id: enEl.id, order, ...placement }] },
          }
        }
        return block
      })
    })
    setSelectedBlockId(toBlockId)
    setSelectedSectionElementId(elementId)
  }, [])

  const reorderSectionElement = useCallback((blockId: string, activeElementId: string, overElementId: string) => {
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId || block.type !== 'section') return block
        const en = asElementArray(block.content_en.elements)
        const ar = asElementArray(block.content_ar.elements)
        const overEl = en.find((e) => e.id === overElementId)
        if (!overEl) return block
        const placement = inheritElementPlacement(overEl)

        const nextEn = insertElementAfter(en, activeElementId, overElementId).map((e, i) =>
          e.id === activeElementId ? { ...e, ...placement, order: i } : { ...e, order: i },
        )
        const nextAr = insertElementAfter(ar, activeElementId, overElementId).map((e, i) =>
          e.id === activeElementId ? { ...e, ...placement, order: i } : { ...e, order: i },
        )

        return {
          ...block,
          content_en: { ...block.content_en, elements: nextEn },
          content_ar: { ...block.content_ar, elements: nextAr },
        }
      }),
    )
    setSelectedBlockId(blockId)
    setSelectedSectionElementId(activeElementId)
  }, [])

  const moveSectionElementBefore = useCallback(
    (fromBlockId: string, elementId: string, toBlockId: string, beforeElementId: string) => {
      setBlocks((prev) => {
        const fromBlock = prev.find((b) => b.id === fromBlockId)
        const toBlock = prev.find((b) => b.id === toBlockId)
        if (!fromBlock || !toBlock || fromBlock.type !== 'section' || toBlock.type !== 'section') return prev

        const fromEn = asElementArray(fromBlock.content_en.elements)
        const fromAr = asElementArray(fromBlock.content_ar.elements)
        const enIdx = fromEn.findIndex((e) => e.id === elementId)
        if (enIdx === -1) return prev

        const enEl = fromEn[enIdx]
        const arEl = fromAr.find((e) => e.id === elementId) ?? enEl
        const nextFromEn = fromEn.filter((e) => e.id !== elementId)
        const nextFromAr = fromAr.filter((e) => e.id !== elementId)

        return prev.map((block) => {
          if (block.id === fromBlockId) {
            return {
              ...block,
              content_en: { ...block.content_en, elements: nextFromEn.map((e, i) => ({ ...e, order: i })) },
              content_ar: { ...block.content_ar, elements: nextFromAr.map((e, i) => ({ ...e, order: i })) },
            }
          }
          if (block.id === toBlockId) {
            const toEn = asElementArray(block.content_en.elements)
            const toAr = asElementArray(block.content_ar.elements)
            const insertIdx = toEn.findIndex((e) => e.id === beforeElementId)
            if (insertIdx === -1) return block
            const targetEl = toEn[insertIdx]
            const isFreeform = block.options.layout_mode === 'freeform'
            const placement = isFreeform
              ? defaultFreeformPlacement(insertIdx + 1)
              : inheritElementPlacement(targetEl)
            const movedEn = { ...enEl, ...placement }
            const movedAr = { ...arEl, id: enEl.id, ...placement }
            return {
              ...block,
              content_en: {
                ...block.content_en,
                elements: [...toEn.slice(0, insertIdx + 1), movedEn, ...toEn.slice(insertIdx + 1)].map((e, i) => ({
                  ...e,
                  order: i,
                })),
              },
              content_ar: {
                ...block.content_ar,
                elements: [...toAr.slice(0, insertIdx + 1), movedAr, ...toAr.slice(insertIdx + 1)].map((e, i) => ({
                  ...e,
                  order: i,
                })),
              },
            }
          }
          return block
        })
      })
      setSelectedBlockId(toBlockId)
      setSelectedSectionElementId(elementId)
    },
    [],
  )

  const shiftSectionElement = useCallback((blockId: string, elementId: string, direction: -1 | 1) => {
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId || block.type !== 'section') return block
        const en = asElementArray(block.content_en.elements)
        const ar = asElementArray(block.content_ar.elements)
        const idx = en.findIndex((e) => e.id === elementId)
        const targetIdx = idx + direction
        if (idx === -1 || targetIdx < 0 || targetIdx >= en.length) return block
        return {
          ...block,
          content_en: {
            ...block.content_en,
            elements: arrayMove(en, idx, targetIdx).map((e, i) => ({ ...e, order: i })),
          },
          content_ar: {
            ...block.content_ar,
            elements: arrayMove(ar, idx, targetIdx).map((e, i) => ({ ...e, order: i })),
          },
        }
      }),
    )
    setSelectedBlockId(blockId)
    setSelectedSectionElementId(elementId)
  }, [])

  const duplicateSectionElement = useCallback((blockId: string, elementId: string) => {
    let newId: string | null = null
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId || block.type !== 'section') return block
        const en = asElementArray(block.content_en.elements)
        const ar = asElementArray(block.content_ar.elements)
        const idx = en.findIndex((e) => e.id === elementId)
        if (idx === -1) return block
        newId = `e_${Math.random().toString(36).slice(2, 8)}`
        const dupEn = {
          ...en[idx],
          id: newId,
          x_pct: en[idx].x_pct !== undefined ? Math.min(90, (en[idx].x_pct ?? 0) + 4) : undefined,
          y_pct: en[idx].y_pct !== undefined ? Math.min(90, (en[idx].y_pct ?? 0) + 4) : undefined,
        }
        const dupAr = { ...(ar[idx] ?? en[idx]), ...dupEn, id: newId }
        return {
          ...block,
          content_en: {
            ...block.content_en,
            elements: [...en.slice(0, idx + 1), dupEn, ...en.slice(idx + 1)].map((e, i) => ({ ...e, order: i })),
          },
          content_ar: {
            ...block.content_ar,
            elements: [...ar.slice(0, idx + 1), dupAr, ...ar.slice(idx + 1)].map((e, i) => ({ ...e, order: i })),
          },
        }
      }),
    )
    if (newId) {
      setSelectedBlockId(blockId)
      setSelectedSectionElementId(newId)
    }
  }, [])

  const removeSectionElement = useCallback((blockId: string, elementId: string) => {
    setBlocks((prev) =>
      prev.map((block) => {
        if (block.id !== blockId || block.type !== 'section') return block
        return {
          ...block,
          content_en: {
            ...block.content_en,
            elements: asElementArray(block.content_en.elements)
              .filter((e) => e.id !== elementId)
              .map((e, i) => ({ ...e, order: i })),
          },
          content_ar: {
            ...block.content_ar,
            elements: asElementArray(block.content_ar.elements)
              .filter((e) => e.id !== elementId)
              .map((e, i) => ({ ...e, order: i })),
          },
        }
      }),
    )
    setSelectedSectionElementId(null)
  }, [])

  const openLayoutPicker = useCallback(
    (insertIndex?: number, dragData?: BuilderDragData | null) => {
      const count = blocks.filter(
        (b) => (b.page_id || 'home') === currentPageId && b.type !== 'header' && b.type !== 'footer',
      ).length
      setLayoutInsertIndex(insertIndex ?? count)
      if (dragData) setPendingDragData(dragData)
      setShowLayoutPicker(true)
    },
    [blocks, currentPageId],
  )

  const closeLayoutPicker = useCallback(() => {
    setShowLayoutPicker(false)
    setPendingDragData(null)
    setRelayoutBlockId(null)
  }, [])

  const openRelayoutPicker = useCallback((blockId: string) => {
    setRelayoutBlockId(blockId)
    setPendingDragData(null)
    setShowLayoutPicker(true)
  }, [])

  const handleRelayoutPick = useCallback(
    (layoutPreset: string) => {
      if (!relayoutBlockId) return
      setBlocks((prev) =>
        prev.map((block) => {
          if (block.id !== relayoutBlockId || block.type !== 'section') return block
          return { ...block, ...applyLayoutPresetToSection(block, layoutPreset) }
        }),
      )
      setSelectedBlockId(relayoutBlockId)
      closeLayoutPicker()
    },
    [closeLayoutPicker, relayoutBlockId],
  )

  const handleOpenSectionStyle = useCallback((blockId: string) => {
    setSelectedBlockId(blockId)
    setSelectedSectionElementId(null)
    setPanelFocus('element')
    setRightTab('style')
  }, [])

  const insertSectionWithPlacement = useCallback(
    (layoutPreset: string, columnIndex: number, insertIndex: number, elementKind?: string) => {
      const sectionDefaults = DEFAULT_CONTENT.section
      const placement = columnGridPlacement(layoutPreset, columnIndex)
      const eventName = event.name[editLocale] ?? event.name.en ?? 'Event'

      let newElementId: string | null = null
      const elementsEn: ReturnType<typeof createSectionElement>[] = []
      const elementsAr: ReturnType<typeof createSectionElement>[] = []

      if (elementKind) {
        const newEn = createSectionElement(elementKind, 'en', 0, eventName)
        newEn.col_span = placement.col_span
        newEn.col_start = placement.col_start
        const newAr = {
          ...createSectionElement(elementKind, 'ar', 0, eventName),
          id: newEn.id,
          col_span: placement.col_span,
          col_start: placement.col_start,
        }
        elementsEn.push(newEn)
        elementsAr.push(newAr)
        newElementId = newEn.id
      }

      const newBlock: SiteBlock = {
        id: generateBlockId('section'),
        type: 'section',
        visible: true,
        page_id: currentPageId,
        content_en: { title: '', subtitle: '', elements: elementsEn },
        content_ar: { title: '', subtitle: '', elements: elementsAr },
        options: { ...sectionDefaults.options, layout_preset: layoutPreset },
        refs: {},
      }

      setBlocks((prev) => insertBlockAtPageIndex(prev, newBlock, insertIndex, currentPageId))
      setSelectedBlockId(newBlock.id)
      setSelectedSectionElementId(newElementId)
      closeLayoutPicker()
    },
    [closeLayoutPicker, currentPageId, editLocale, event.name],
  )

  const handleLayoutColumnDrop = useCallback(
    (layoutPreset: string, columnIndex: number, insertIndex: number, activeData: BuilderDragData) => {
      if (isPaletteElement(activeData)) {
        insertSectionWithPlacement(layoutPreset, columnIndex, insertIndex, activeData.elementKind)
        return
      }
      if (isPaletteBlock(activeData)) {
        const { type: resolvedType } = resolvePaletteBlock(activeData.blockType)
        if (resolvedType === 'section') {
          insertSectionWithPlacement(layoutPreset, columnIndex, insertIndex)
        } else {
          insertBlockAtIndex(activeData.blockType, insertIndex, activeData.presetId)
          closeLayoutPicker()
        }
      }
    },
    [closeLayoutPicker, insertBlockAtIndex, insertSectionWithPlacement],
  )

  const handleLayoutColumnPick = useCallback(
    (layoutPreset: string, columnIndex: number, insertIndex: number) => {
      const data = pendingDragData
      if (data && isPaletteElement(data)) {
        insertSectionWithPlacement(layoutPreset, columnIndex, insertIndex, data.elementKind)
        return
      }
      if (data && isPaletteBlock(data)) {
        const { type: resolvedType } = resolvePaletteBlock(data.blockType)
        if (resolvedType === 'section') {
          insertSectionWithPlacement(layoutPreset, columnIndex, insertIndex)
        } else {
          insertBlockAtIndex(data.blockType, insertIndex, data.presetId)
          closeLayoutPicker()
        }
        return
      }
      insertSectionWithPlacement(layoutPreset, columnIndex, insertIndex)
    },
    [closeLayoutPicker, insertBlockAtIndex, insertSectionWithPlacement, pendingDragData],
  )

  const handleDragStateChange = useCallback(
    (active: boolean, data: BuilderDragData | null) => {
      if (active && data && (isPaletteElement(data) || isPaletteBlock(data))) {
        setPendingDragData(data)
        openLayoutPicker(undefined, data)
      } else if (!active) {
        closeLayoutPicker()
      }
    },
    [closeLayoutPicker, openLayoutPicker],
  )

  const pickElement = useCallback(
    (kind: string) => {
      openLayoutPicker(undefined, { kind: 'palette-element', elementKind: kind })
    },
    [openLayoutPicker],
  )

  const pickWidget = useCallback(
    (blockType: string) => {
      if (['header', 'hero', 'footer', 'carousel', 'form'].includes(blockType)) {
        addBlock(blockType)
        closeLayoutPicker()
        return
      }
      openLayoutPicker(undefined, { kind: 'palette-block', blockType })
    },
    [addBlock, closeLayoutPicker, openLayoutPicker],
  )

  const duplicateBlock = useCallback((id: string) => {
    const block = blocks.find((b) => b.id === id)
    if (!block) return

    const newBlock: SiteBlock = {
      ...JSON.parse(JSON.stringify(block)),
      id: generateBlockId(block.type),
    }

    setBlocks((prev) => {
      const index = prev.findIndex((b) => b.id === id)
      if (index === -1) return prev
      const next = [...prev]
      next.splice(index + 1, 0, newBlock)
      return next
    })
    setSelectedBlockId(newBlock.id)
  }, [blocks])

  const moveBlockUp = useCallback((id: string) => {
    const filteredIds = filteredBlocks.map((b) => b.id)
    const index = filteredIds.indexOf(id)
    if (index <= 0) return
    moveBlock(index, index - 1)
  }, [filteredBlocks, moveBlock])

  const moveBlockDown = useCallback((id: string) => {
    const filteredIds = filteredBlocks.map((b) => b.id)
    const index = filteredIds.indexOf(id)
    if (index === -1 || index >= filteredIds.length - 1) return
    moveBlock(index, index + 1)
  }, [filteredBlocks, moveBlock])

  const restoreVersion = useCallback(async (versionId: number) => {
    try {
      const response = await apiFetch<{
        draft_revision: number
        draft_blocks: SiteBlock[]
        restored_from_version: number
      }>(`/api/v1/tenant/events/${event.id}/site/versions/${versionId}/restore`, {
        method: 'POST',
        tenantId,
        idempotency: true,
      })
      setBlocks(response.draft_blocks)
      setDraftRevision(response.draft_revision)
      setShowVersions(false)
      toast(`Restored version ${response.restored_from_version}`, 'success')
    } catch (err) {
      toast(err instanceof ApiFetchError ? err.message : 'Failed to restore.', 'error')
    }
  }, [event.id, tenantId, toast])

  const addPage = useCallback(() => {
    const newPage: SitePage = {
      id: generatePageId(),
      slug: `page-${pages.length}`,
      title_en: 'New Page',
      title_ar: 'صفحة جديدة',
      is_home: false,
    }
    updateSettings({ pages: [...pages, newPage] })
    setEditingPage(newPage)
    setShowPageEditor(true)
  }, [pages, updateSettings])

  const savePage = useCallback((page: SitePage) => {
    const updatedPages = pages.map((p) => (p.id === page.id ? page : p))
    if (!updatedPages.find((p) => p.id === page.id)) {
      updatedPages.push(page)
    }
    updateSettings({ pages: updatedPages })
    setShowPageEditor(false)
    setEditingPage(null)
  }, [pages, updateSettings])

  const deletePage = useCallback((pageId: string) => {
    if (pageId === 'home') return
    const updatedPages = pages.filter((p) => p.id !== pageId)
    const updatedBlocks = blocks.map((b) => (b.page_id === pageId ? { ...b, page_id: 'home' } : b))
    updateSettings({ pages: updatedPages })
    setBlocks(updatedBlocks)
    if (currentPageId === pageId) setCurrentPageId('home')
  }, [pages, blocks, currentPageId, updateSettings])

  const statusBadge = useMemo(() => siteStatus, [siteStatus])

  const direction = editLocale === 'ar' ? 'rtl' : 'ltr'

  const publicPathPrefix = settings.public_path_prefix === 'events' ? 'events' : 'e'
  const publicSlug = (settings.public_slug || event.slug || '').trim() || event.slug
  const livePublicPath = `/${publicPathPrefix}/${publicSlug}`
  const livePublicUrl = localizedPath(livePublicPath)
  const publicUrl = siteStatus === 'published'
    ? (initialSite.public_url
        ? localizedPath(initialSite.public_url.replace(/^\/(en|ar)/, '') || livePublicPath)
        : livePublicUrl)
    : livePublicUrl

  const openLivePreview = useCallback(() => {
    if (siteStatus !== 'published') {
      toast(
        locale === 'ar'
          ? 'انشر الموقع أولاً لفتح المعاينة الحية في تاب جديد.'
          : 'Publish the site first to open the live preview in a new tab.',
        'info',
      )
      setShowPreview(true)
      return
    }
    window.open(livePublicUrl, '_blank', 'noopener,noreferrer')
  }, [livePublicUrl, locale, siteStatus, toast])

  const selectedTypeLabel = useMemo(() => {
    if (selectedSectionElementId && selectedBlock?.type === 'section') {
      const content = editLocale === 'ar' ? selectedBlock.content_ar : selectedBlock.content_en
      const el = asElementArray(content.elements).find((e) => e.id === selectedSectionElementId)
      return el?.kind ?? 'element'
    }
    if (selectedBlock) {
      return BLOCK_TYPE_LABELS[selectedBlock.type]?.[locale] ?? selectedBlock.type
    }
    return locale === 'ar' ? 'لا شيء محدد' : 'Nothing selected'
  }, [editLocale, locale, selectedBlock, selectedSectionElementId])

  const showLayoutTab = Boolean(
    selectedBlock &&
      (selectedSectionElement ||
        (selectedBlock.type !== 'header' && selectedBlock.type !== 'footer')),
  )

  const inspectorTabs = useMemo(() => {
    const uiLocale = locale === 'ar' ? 'ar' : 'en'
    const showElementTabs = Boolean(selectedBlock) && panelFocus === 'element'
    return buildPropertiesTabs({
      locale: uiLocale,
      hasSelection: showElementTabs,
      showLayout: showElementTabs && showLayoutTab,
    })
  }, [locale, panelFocus, selectedBlock, showLayoutTab])

  useEffect(() => {
    if (!inspectorTabs.some((tab) => tab.id === rightTab)) {
      setRightTab(inspectorTabs[0]?.id ?? 'structure')
    }
  }, [inspectorTabs, rightTab])

  const selectBlock = useCallback((id: string) => {
    setSelectedBlockId(id)
    setSelectedSectionElementId(null)
    setPanelFocus('element')
    setRightTab('content')
  }, [])

  const selectSectionElement = useCallback((blockId: string, elementId: string | null) => {
    setSelectedBlockId(blockId)
    setSelectedSectionElementId(elementId)
    setPanelFocus('element')
    setRightTab('content')
  }, [])

  const clearSelection = useCallback(() => {
    setSelectedBlockId(null)
    setSelectedSectionElementId(null)
    setPanelFocus('structure')
    setRightTab('structure')
  }, [])

  const openStructurePanel = useCallback(() => {
    setPanelFocus('structure')
    setRightTab('structure')
  }, [])

  const openSitePanel = useCallback(() => {
    setPanelFocus('site')
    setRightTab('site')
  }, [])

  return (
    <BuilderDragProvider
      onDragStateChange={handleDragStateChange}
      onPaletteBlockDrop={(blockType, index, presetId) => insertBlockAtIndex(blockType, index, presetId)}
      onPaletteElementDrop={(blockId, elementKind) => addElementToSection(blockId, elementKind)}
      onPaletteElementDropAfter={(blockId, elementKind, afterElementId) =>
        addElementAfterTarget(blockId, elementKind, afterElementId)
      }
      onLayoutColumnDrop={handleLayoutColumnDrop}
      onMoveSectionElement={moveElementBetweenSections}
      onReorderSectionElement={reorderSectionElement}
      onMoveSectionElementBefore={moveSectionElementBefore}
      onReorderBlocks={reorderBlocksById}
    >
    <SiteBuilderShell
      title={`${event.name[locale]} — ${locale === 'ar' ? 'محرر الموقع' : 'Site Builder'}`}
      uiLocale={locale === 'ar' ? 'ar' : 'en'}
      topBar={
        <BuilderTopBar
          eventName={event.name[locale]}
          locale={locale}
          editLocale={editLocale}
          onEditLocaleChange={setEditLocale}
          viewport={viewport}
          onViewportChange={setViewport}
          status={statusBadge}
          saving={saving}
          publishing={publishing}
          onSave={saveDraft}
          onPublish={publish}
          onPreview={openLivePreview}
          onVersions={() => setShowVersions(true)}
          publicUrl={siteStatus === 'published' ? publicUrl : null}
          backHref={`/tenant/events/${event.id}`}
        />
      }
      leftPanel={
        <div className="flex min-h-0 flex-1 flex-col">
          <ElementLibrary
            locale={locale}
            search={widgetSearch}
            onSearchChange={setWidgetSearch}
            onAddSection={() => openLayoutPicker()}
            onPickElement={pickElement}
            onPickWidget={pickWidget}
          />
          <div className="shrink-0 flex gap-1 border-t border-white/10 p-2">
            {pageMode === 'multi' && (
              <button
                type="button"
                onClick={() => setLeftTab(leftTab === 'pages' ? null : 'pages')}
                className={`flex-1 rounded-md px-2 py-1.5 text-[10px] font-semibold uppercase ${leftTab === 'pages' ? 'bg-violet-600 text-white' : 'text-white/50 hover:bg-white/5'}`}
              >
                <FileStack className="mx-auto h-3.5 w-3.5" />
              </button>
            )}
            <button
              type="button"
              onClick={() => setLeftTab(leftTab === 'settings' ? null : 'settings')}
              className={`flex-1 rounded-md px-2 py-1.5 text-[10px] font-semibold uppercase ${leftTab === 'settings' ? 'bg-violet-600 text-white' : 'text-white/50 hover:bg-white/5'}`}
            >
              <Settings className="mx-auto h-3.5 w-3.5" />
            </button>
            <button
              type="button"
              onClick={() => setLeftTab(leftTab === 'submissions' ? null : 'submissions')}
              className={`flex-1 rounded-md px-2 py-1.5 text-[10px] font-semibold uppercase ${leftTab === 'submissions' ? 'bg-violet-600 text-white' : 'text-white/50 hover:bg-white/5'}`}
            >
              <Inbox className="mx-auto h-3.5 w-3.5" />
            </button>
          </div>
          {leftTab === 'pages' && pageMode === 'multi' && (
            <div className="builder-inspector max-h-48 space-y-3 overflow-y-auto border-t border-white/10 p-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-white/80">{locale === 'ar' ? 'الصفحات' : 'Pages'}</span>
                <button type="button" onClick={addPage} className="rounded-md p-1 text-violet-300 hover:bg-white/10">
                  <Plus className="h-4 w-4" />
                </button>
              </div>
              {pages.map((page) => (
                <button
                  key={page.id}
                  type="button"
                  onClick={() => { setCurrentPageId(page.id); setLeftTab(null) }}
                  className={`w-full rounded-lg border px-3 py-2 text-left text-sm ${
                    currentPageId === page.id ? 'border-violet-400/60 bg-violet-500/10 text-white' : 'border-white/10 text-white/70'
                  }`}
                >
                  {locale === 'ar' ? page.title_ar : page.title_en}
                </button>
              ))}
            </div>
          )}
          {leftTab === 'settings' && (
            <div className="builder-inspector max-h-64 space-y-3 overflow-y-auto border-t border-white/10 p-3 text-sm">
              <BackgroundEditor
                value={siteBackground}
                onChange={(bg) => updateSettings({ site_background: bg })}
                locale={editLocale}
                tenantId={tenantId}
                eventId={event.id}
                label={locale === 'ar' ? 'خلفية الموقع' : 'Site Background'}
              />
              <div className="space-y-2 rounded-lg border border-white/10 bg-white/[0.03] p-3">
                <p className="text-xs font-semibold text-white/80">
                  {locale === 'ar' ? 'رابط الموقع العام' : 'Public site URL'}
                </p>
                <select
                  value={publicPathPrefix}
                  onChange={(e) =>
                    updateSettings({ public_path_prefix: e.target.value as 'e' | 'events' })
                  }
                  className="w-full rounded-md border border-white/10 bg-white/5 px-2 py-1.5 text-xs text-white"
                >
                  <option value="e">/e/slug (قصير)</option>
                  <option value="events">/events/slug</option>
                </select>
                <input
                  type="text"
                  value={settings.public_slug ?? ''}
                  onChange={(e) =>
                    updateSettings({
                      public_slug: e.target.value.replace(/\s+/g, '-').toLowerCase(),
                    })
                  }
                  placeholder={event.slug}
                  className="w-full rounded-md border border-white/10 bg-white/5 px-2 py-1.5 text-xs text-white placeholder:text-white/30"
                />
                <p className="break-all text-[10px] text-violet-300/90">{livePublicUrl}</p>
                <p className="text-[10px] leading-relaxed text-white/40">
                  {locale === 'ar'
                    ? 'اترك الـ slug فارغاً لاستخدام slug الفعالية. احفظ ثم انشر لتفعيل الرابط.'
                    : 'Leave slug empty to use the event slug. Save then publish to activate the URL.'}
                </p>
              </div>
            </div>
          )}
          {leftTab === 'submissions' && (
            <div className="builder-inspector max-h-64 overflow-y-auto border-t border-white/10 p-3">
              <FormSubmissionsPanel
                eventId={event.id}
                tenantId={tenantId}
                locale={locale}
                onClose={() => setLeftTab(null)}
              />
            </div>
          )}
        </div>
      }
      canvas={
        <div className="site-builder-canvas flex h-full flex-col">
          {pageMode === 'multi' && (
            <div className="flex shrink-0 items-center gap-2 border-b border-white/10 bg-[#12121f] px-4 py-2">
              <span className="text-[11px] font-medium uppercase tracking-wider text-white/40">{locale === 'ar' ? 'تحرير:' : 'Editing:'}</span>
              {pages.map((page) => (
                <button
                  key={page.id}
                  type="button"
                  onClick={() => setCurrentPageId(page.id)}
                  className={`rounded-full px-3 py-1 text-xs font-medium transition ${
                    currentPageId === page.id ? 'bg-violet-600 text-white' : 'bg-white/5 text-white/60 hover:text-white'
                  }`}
                >
                  {locale === 'ar' ? page.title_ar : page.title_en}
                </button>
              ))}
            </div>
          )}
          <div
            className="flex flex-1 items-start justify-center overflow-auto p-4 md:p-6"
            onClick={clearSelection}
          >
            <div className="w-full max-w-5xl rounded-2xl border border-slate-700/50 bg-slate-800/40 p-3 shadow-inner md:p-6">
              <LayoutDropPicker
                visible={showLayoutPicker}
                locale={locale}
                mode={relayoutBlockId ? 'relayout' : 'insert'}
                insertIndex={layoutInsertIndex}
                onClose={closeLayoutPicker}
                onColumnPick={handleLayoutColumnPick}
                onRelayoutPick={handleRelayoutPick}
              />
              <div
                className={`site-builder-device relative mx-auto overflow-visible rounded-xl shadow-xl transition-all duration-300 ${viewportWidth(viewport)} ${hasSiteBg ? '' : 'bg-slate-50'}`}
                dir={direction}
                lang={editLocale}
                style={{ direction, minHeight: '70vh', ...siteBgStyle }}
                onClick={clearSelection}
              >
                {siteBgOverlay && (
                  <div className="pointer-events-none absolute inset-0 z-0" style={siteBgOverlay} />
                )}
                <div className="relative z-[1] border border-1 border-gray-400">
                  <BlockCanvas
                    blocks={filteredBlocks}
                    locale={editLocale}
                    registerUrl={registerUrl}
                    siteBaseUrl={publicUrl}
                    eventSlug={event.slug}
                    currentPageId={currentPageId}
                    selectedId={selectedBlockId}
                    onSelect={selectBlock}
                    interactive
                    blockTypeLabels={BLOCK_TYPE_LABELS}
                    previewData={preview}
                    onDuplicate={duplicateBlock}
                    onMoveUp={moveBlockUp}
                    onMoveDown={moveBlockDown}
                    onToggleVisibility={(id) => {
                      const block = blocks.find((b) => b.id === id)
                      if (block) {
                        updateBlock(id, { visible: !block.visible })
                        selectBlock(id)
                      }
                    }}
                    onRemove={removeBlock}
                    onUpdateBlock={(id, updates) => updateBlock(id, updates)}
                    selectedSectionElementId={selectedSectionElementId}
                    onSelectSectionElement={selectSectionElement}
                    onAppendSection={() => openLayoutPicker()}
                    onBlockSlotHover={(index) => setLayoutInsertIndex(index)}
                    onChangeSectionLayout={openRelayoutPicker}
                    onOpenSectionStyle={handleOpenSectionStyle}
                    onMoveSectionElementUp={(blockId, elementId) => shiftSectionElement(blockId, elementId, -1)}
                    onMoveSectionElementDown={(blockId, elementId) => shiftSectionElement(blockId, elementId, 1)}
                    onDuplicateSectionElement={duplicateSectionElement}
                    onRemoveSectionElement={removeSectionElement}
                  />
                </div>
              </div>
            </div>
          </div>
        </div>
      }
      rightPanel={
        <PropertiesInspector
          locale={locale === 'ar' ? 'ar' : 'en'}
          selectionLabel={selectedTypeLabel}
          hasSelection={Boolean(selectedBlock)}
          editingSelection={panelFocus === 'element' && Boolean(selectedBlock)}
          tabs={inspectorTabs}
          activeTab={rightTab}
          onTabChange={setRightTab}
          onOpenStructure={openStructurePanel}
          onOpenSite={openSitePanel}
          onEditSelection={() => {
            setPanelFocus('element')
            setRightTab('content')
          }}
        >
          {rightTab === 'content' && (
            selectedBlock ? (
              <div dir={editLocale === 'ar' ? 'rtl' : 'ltr'} lang={editLocale}>
                <BlockEditor
                  block={selectedBlock}
                  locale={editLocale}
                  eventId={event.id}
                  tenantId={tenantId}
                  pages={pageMode === 'multi' ? pages : undefined}
                  mode="content"
                  onChange={(updates) => updateBlock(selectedBlock.id, updates)}
                  selectedSectionElementId={selectedSectionElementId}
                />
              </div>
            ) : (
              <div className="flex flex-col items-center justify-center px-4 py-10 text-center">
                <LayoutGrid className="mb-3 h-8 w-8 text-white/20" />
                <p className="text-sm text-white/50">
                  {t('siteBuilderSelectBlock') || 'Select an element on the canvas to edit its content.'}
                </p>
              </div>
            )
          )}

          {rightTab === 'layout' && selectedBlock && (
            <div dir={editLocale === 'ar' ? 'rtl' : 'ltr'} lang={editLocale} className="space-y-2 p-1">
              {selectedSectionElement ? (
                <ElementLayoutQuickPanel
                  element={selectedSectionElement}
                  locale={editLocale}
                  freeform={selectedBlock.options.layout_mode === 'freeform'}
                  onChange={updateSelectedSectionElementLayout}
                />
              ) : selectedBlock.type !== 'header' && selectedBlock.type !== 'footer' ? (
                <BlockActionsToolbar
                  blockId={selectedBlock.id}
                  locale={editLocale}
                  canMoveUp={filteredBlocks.findIndex((b) => b.id === selectedBlock.id) > 0}
                  canMoveDown={filteredBlocks.findIndex((b) => b.id === selectedBlock.id) < filteredBlocks.length - 1}
                  onDuplicate={() => duplicateBlock(selectedBlock.id)}
                  onMoveUp={() => moveBlockUp(selectedBlock.id)}
                  onMoveDown={() => moveBlockDown(selectedBlock.id)}
                  onToggleVisibility={() => updateBlock(selectedBlock.id, { visible: !selectedBlock.visible })}
                  onRemove={() => removeBlock(selectedBlock.id)}
                  visible={selectedBlock.visible}
                  onAlignChange={(align) =>
                    updateBlock(selectedBlock.id, {
                      options: { ...selectedBlock.options, content_align: align },
                    })
                  }
                  align={(selectedBlock.options.content_align as 'start' | 'center' | 'end') || 'start'}
                  onWidthChange={(width) =>
                    updateBlock(selectedBlock.id, { options: { ...selectedBlock.options, width } })
                  }
                  width={(selectedBlock.options.width as 'full' | 'boxed' | 'narrow') || 'boxed'}
                />
              ) : (
                <p className="px-4 py-8 text-center text-sm text-white/50">
                  {locale === 'ar' ? 'لا توجد خيارات تخطيط لهذا العنصر.' : 'No layout options for this element.'}
                </p>
              )}
            </div>
          )}

          {rightTab === 'style' && (
            selectedBlock ? (
              <div dir={editLocale === 'ar' ? 'rtl' : 'ltr'} lang={editLocale}>
                <BlockEditor
                  block={selectedBlock}
                  locale={editLocale}
                  eventId={event.id}
                  tenantId={tenantId}
                  mode="style"
                  onChange={(updates) => updateBlock(selectedBlock.id, updates)}
                />
              </div>
            ) : (
              <div className="flex items-center justify-center px-4 py-10 text-center text-sm text-white/50">
                {locale === 'ar' ? 'اختر قسمًا لتعديل التنسيق' : 'Select a section to edit style'}
              </div>
            )
          )}

          {rightTab === 'structure' && (
            <div className="p-4">
              <PageStructurePanel
                blocks={filteredBlocks}
                selectedBlockId={selectedBlockId}
                selectedElementId={selectedSectionElementId}
                locale={locale}
                blockTypeLabels={BLOCK_TYPE_LABELS}
                onSelectBlock={selectBlock}
                onSelectElement={selectSectionElement}
              />
            </div>
          )}

          {rightTab === 'site' && (
            <BuilderInspectorSection title={locale === 'ar' ? 'إعدادات الموقع' : 'Site settings'}>
              <div className="space-y-3">
                <div>
                  <label className="mb-1.5 block text-xs font-medium text-white/60">
                    {locale === 'ar' ? 'وضع الصفحات' : 'Page mode'}
                  </label>
                  <select
                    value={pageMode}
                    onChange={(e) => updateSettings({ page_mode: e.target.value as 'single' | 'multi' })}
                    className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"
                  >
                    <option value="single">{locale === 'ar' ? 'صفحة واحدة' : 'Single page'}</option>
                    <option value="multi">{locale === 'ar' ? 'متعدد الصفحات' : 'Multi-page'}</option>
                  </select>
                </div>
                <LogoEditor
                  value={siteLogo}
                  onChange={(logo) => updateSettings({ logo })}
                  locale={editLocale}
                  tenantId={tenantId}
                  eventId={event.id}
                />
                <div className="space-y-2 rounded-lg border border-white/10 bg-white/[0.03] p-3">
                  <p className="text-xs font-semibold text-white/80">
                    {locale === 'ar' ? 'رابط المعاينة العام' : 'Public preview URL'}
                  </p>
                  <select
                    value={publicPathPrefix}
                    onChange={(e) =>
                      updateSettings({ public_path_prefix: e.target.value as 'e' | 'events' })
                    }
                    className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"
                  >
                    <option value="e">{locale === 'ar' ? 'قصير: /e/slug' : 'Short: /e/slug'}</option>
                    <option value="events">{locale === 'ar' ? 'كامل: /events/slug' : 'Full: /events/slug'}</option>
                  </select>
                  <div>
                    <label className="mb-1 block text-xs text-white/50">
                      {locale === 'ar' ? 'Slug الرابط العام' : 'Public URL slug'}
                    </label>
                    <input
                      type="text"
                      value={settings.public_slug ?? ''}
                      onChange={(e) =>
                        updateSettings({
                          public_slug: e.target.value.replace(/\s+/g, '-').toLowerCase(),
                        })
                      }
                      placeholder={event.slug}
                      className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white placeholder:text-white/30"
                    />
                  </div>
                  <p className="break-all rounded-md bg-black/20 px-2 py-1.5 text-[11px] text-violet-300">
                    {typeof window !== 'undefined' ? window.location.origin : ''}
                    {livePublicUrl}
                  </p>
                </div>
                <AssistantConfigPanel eventId={event.id} tenantId={tenantId} />
                {publishBlockers.length > 0 && (
                  <div className="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-200">
                    <p className="mb-1 font-medium">{t('siteBuilderBlockers') || 'Cannot publish:'}</p>
                    <ul className="list-inside list-disc space-y-0.5">
                      {publishBlockers.map((blocker) => <li key={blocker}>{blocker}</li>)}
                    </ul>
                  </div>
                )}
                {siteStatus === 'published' && (
                  <button
                    type="button"
                    onClick={() => setConfirmUnpublish(true)}
                    disabled={unpublishing}
                    className="w-full rounded-md border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300 hover:bg-red-500/20"
                  >
                    {t('siteBuilderUnpublish') || 'Unpublish'}
                  </button>
                )}
              </div>
            </BuilderInspectorSection>
          )}
        </PropertiesInspector>
      }
      overlays={
        <>
          {showPreview && (
            <SitePreview
              blocks={blocks}
              event={event}
              locale={editLocale}
              registerUrl={registerUrl}
              siteBackground={siteBackground}
              publicUrl={publicUrl}
              previewData={preview}
              onClose={() => setShowPreview(false)}
            />
          )}

          {showVersions && (
            <VersionHistory
              eventId={event.id}
              tenantId={tenantId}
              currentVersionId={liveVersion?.id ?? null}
              onRestore={restoreVersion}
              onClose={() => setShowVersions(false)}
            />
          )}

          <ConfirmModal
            open={confirmUnpublish}
            title={t('siteBuilderUnpublishTitle') || 'Unpublish site?'}
            message={t('siteBuilderUnpublishMessage') || 'The public site will no longer be visible. The registration page will remain available.'}
            confirmLabel={t('siteBuilderUnpublish') || 'Unpublish'}
            cancelLabel={t('cancel') || 'Cancel'}
            loading={unpublishing}
            onConfirm={unpublish}
            onCancel={() => setConfirmUnpublish(false)}
          />

          {showPageEditor && editingPage && (
            <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/60 backdrop-blur-sm">
              <div className="w-full max-w-md rounded-xl border border-white/10 bg-[#1a1a2e] p-6 text-white shadow-2xl">
                <div className="mb-4 flex items-center justify-between">
                  <h3 className="text-lg font-semibold">
                    {locale === 'ar' ? 'تعديل الصفحة' : 'Edit Page'}
                  </h3>
                  <button type="button" onClick={() => setShowPageEditor(false)} className="rounded p-1 hover:bg-white/10">
                    <X className="h-5 w-5" />
                  </button>
                </div>
                <div className="space-y-4">
                  <div>
                    <label className="mb-1 block text-sm font-medium text-white/70">{locale === 'ar' ? 'المسار (Slug)' : 'Slug'}</label>
                    <input
                      type="text"
                      value={editingPage.slug}
                      onChange={(e) => setEditingPage({ ...editingPage, slug: e.target.value.replace(/\s/g, '-').toLowerCase() })}
                      className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"
                      placeholder="page-slug"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-white/70">Title (EN)</label>
                    <input
                      type="text"
                      value={editingPage.title_en}
                      onChange={(e) => setEditingPage({ ...editingPage, title_en: e.target.value })}
                      className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"
                    />
                  </div>
                  <div>
                    <label className="mb-1 block text-sm font-medium text-white/70">العنوان (AR)</label>
                    <input
                      type="text"
                      value={editingPage.title_ar}
                      onChange={(e) => setEditingPage({ ...editingPage, title_ar: e.target.value })}
                      className="w-full rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-white"
                      dir="rtl"
                    />
                  </div>
                  <div className="flex gap-2 pt-2">
                    <button type="button" onClick={() => setShowPageEditor(false)} className="flex-1 rounded-md border border-white/15 px-3 py-2 text-sm hover:bg-white/5">
                      {locale === 'ar' ? 'إلغاء' : 'Cancel'}
                    </button>
                    <button type="button" onClick={() => savePage(editingPage)} className="flex-1 rounded-md bg-violet-600 px-3 py-2 text-sm font-medium hover:bg-violet-500">
                      {locale === 'ar' ? 'حفظ' : 'Save'}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          )}
        </>
      }
    />
    </BuilderDragProvider>
  )
}
