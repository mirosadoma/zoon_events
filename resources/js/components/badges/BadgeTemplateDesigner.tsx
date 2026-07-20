import { useState, useRef, useCallback, useEffect, type CSSProperties, type PointerEvent as ReactPointerEvent } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import {
  Type, Building2, Briefcase, QrCode, Ticket, Layers, MapPin,
  Image, Palette, PenLine, Plus, Trash2, Save, GripVertical, UserCheck,
} from 'lucide-react'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { useToast } from '@/hooks/useToast'
import { useLocale } from '@/hooks/useLocale'
import type { BadgeBackgroundGradient, BadgeTemplate } from '@/types/phase3'

/* ------------------------------------------------------------------ */
/*  Types                                                              */
/* ------------------------------------------------------------------ */

interface BadgeFieldLayout {
  id: string
  field: string
  x: number
  y: number
  width: number
  height: number
  fontSize?: number
  /** Font size as % of badge canvas height (preferred over legacy fontSize px). */
  fontSizePercent?: number
  fontFamily?: string
  fontWeight?: string
  color?: string
  textAlign?: 'left' | 'center' | 'right'
  verticalAlign?: 'top' | 'center' | 'bottom'
  backgroundColor?: string
  borderRadius?: number
  rotation?: number
  /** Static copy for custom_text fields (shown on printed/email badges). */
  text?: string
}

interface BadgeTemplateData {
  name: string
  paper_size: string
  printer_type: string
  orientation: string
  background_color: string | null
  background_gradient: BadgeBackgroundGradient | null
  canvas_width: number
  canvas_height: number
  layout: BadgeFieldLayout[]
}

interface BadgeTemplateDesignerProps {
  template?: {
    id: string
    name: string
    layout: BadgeFieldLayout[] | Record<string, unknown>
    paper_size: string
    printer_type: string
    status: string
    background_color?: string
    background_gradient?: BadgeBackgroundGradient | null
    background_image_path?: string
    orientation?: string
    canvas_width?: number
    canvas_height?: number
  }
  eventId: string
  tenantId?: string
  onSave?: (data: BadgeTemplateData) => void
  onSaved?: (template: BadgeTemplate) => void
  saving?: boolean
}

/* ------------------------------------------------------------------ */
/*  Constants                                                          */
/* ------------------------------------------------------------------ */

const SNAP = 4

const AVAILABLE_FIELDS = [
  'attendee_name', 'company', 'job_title', 'qr', 'ticket_type', 'attendee_type',
  'tier', 'zone', 'sponsor_logo_ref', 'organizer_logo_ref',
  'color_code', 'custom_text',
] as const

const FIELD_ICONS: Record<string, typeof Type> = {
  attendee_name: Type,
  company: Building2,
  job_title: Briefcase,
  qr: QrCode,
  ticket_type: Ticket,
  attendee_type: UserCheck,
  tier: Layers,
  zone: MapPin,
  sponsor_logo_ref: Image,
  organizer_logo_ref: Image,
  color_code: Palette,
  custom_text: PenLine,
}

const FIELD_LABELS: Record<string, string> = {
  attendee_name: 'Attendee Name',
  company: 'Company',
  job_title: 'Job Title',
  qr: 'QR Code',
  ticket_type: 'Ticket Type',
  attendee_type: 'Attendee Type',
  tier: 'Tier',
  zone: 'Zone',
  sponsor_logo_ref: 'Sponsor Logo',
  organizer_logo_ref: 'Organizer Logo',
  color_code: 'Color Code',
  custom_text: 'Custom Text',
}

const PAPER_PRESETS: Record<string, { w: number; h: number }> = {
  CR80: { w: 242, h: 153 },
  A6: { w: 298, h: 420 },
  '4x3': { w: 288, h: 216 },
  '4x6': { w: 288, h: 432 },
}

function normalizePaperSize(value?: string | null): string {
  if (!value) return 'A6'
  if (value === 'custom') return 'custom'
  const match = Object.keys(PAPER_PRESETS).find((key) => key.toLowerCase() === value.toLowerCase())
  return match ?? 'A6'
}

function resolveCanvasSize(
  paperSize: string,
  orientation: 'portrait' | 'landscape',
  custom?: { w: number; h: number } | null,
): { w: number; h: number } {
  if (paperSize === 'custom') {
    const base = custom ?? { w: 298, h: 420 }

    return {
      w: Math.max(1, Math.round(base.w)),
      h: Math.max(1, Math.round(base.h)),
    }
  }

  const preset = PAPER_PRESETS[paperSize] ?? PAPER_PRESETS.A6

  return orientation === 'landscape'
    ? { w: Math.max(preset.w, preset.h), h: Math.min(preset.w, preset.h) }
    : { w: Math.min(preset.w, preset.h), h: Math.max(preset.w, preset.h) }
}

function clampFieldsToCanvas(fields: BadgeFieldLayout[], canvasW: number, canvasH: number): BadgeFieldLayout[] {
  return fields.map((field) => {
    const width = Math.min(field.width, canvasW)
    const height = Math.min(field.height, canvasH)
    return {
      ...field,
      width,
      height,
      x: snap(Math.max(0, Math.min(canvasW - width, field.x))),
      y: snap(Math.max(0, Math.min(canvasH - height, field.y))),
    }
  })
}

const FONT_OPTIONS = ['Inter', 'Arial', 'Cairo', 'Tajawal', 'monospace']

const DEFAULT_FIELD_SIZE: Record<string, { w: number; h: number }> = {
  qr: { w: 80, h: 80 },
  sponsor_logo_ref: { w: 80, h: 40 },
  organizer_logo_ref: { w: 80, h: 40 },
  color_code: { w: 40, h: 12 },
}

type HorizontalAlign = NonNullable<BadgeFieldLayout['textAlign']>
type VerticalAlign = NonNullable<BadgeFieldLayout['verticalAlign']>

function resolveHorizontalAlign(value?: BadgeFieldLayout['textAlign']): HorizontalAlign {
  return value === 'right' || value === 'center' ? value : 'left'
}

function resolveVerticalAlign(value?: BadgeFieldLayout['verticalAlign']): VerticalAlign {
  return value === 'top' || value === 'bottom' ? value : 'center'
}

function normalizeHexColor(value?: string | null, fallback = '#ffffff'): string {
  if (!value) return fallback
  const short = /^#([0-9a-f]{3})$/i.exec(value)
  if (short) {
    const [r, g, b] = short[1].split('')
    return `#${r}${r}${g}${g}${b}${b}`.toLowerCase()
  }
  return /^#[0-9a-f]{6}$/i.test(value) ? value.toLowerCase() : fallback
}

function darkenHexColor(hex: string, amount: number): string {
  const normalized = normalizeHexColor(hex)
  const value = normalized.slice(1)
  const clamp = (channel: number) => Math.max(0, Math.min(255, Math.round(channel * (1 - amount))))
  const r = clamp(parseInt(value.slice(0, 2), 16))
  const g = clamp(parseInt(value.slice(2, 4), 16))
  const b = clamp(parseInt(value.slice(4, 6), 16))

  return `#${r.toString(16).padStart(2, '0')}${g.toString(16).padStart(2, '0')}${b.toString(16).padStart(2, '0')}`
}

function defaultBackgroundGradient(fromColor: string): BadgeBackgroundGradient {
  const base = normalizeHexColor(fromColor)
  return {
    type: 'linear',
    angle: 135,
    stops: [
      { color: base, position: 0 },
      { color: darkenHexColor(base, 0.28), position: 100 },
    ],
  }
}

function normalizeBackgroundGradient(value: unknown): BadgeBackgroundGradient | null {
  if (!value || typeof value !== 'object') return null
  const raw = value as BadgeBackgroundGradient
  if (raw.type !== 'linear' || !Array.isArray(raw.stops) || raw.stops.length < 2) return null

  const stops = raw.stops
    .map((stop) => ({
      color: normalizeHexColor(stop.color, ''),
      position: Math.max(0, Math.min(100, Number(stop.position) || 0)),
    }))
    .filter((stop) => stop.color !== '')

  if (stops.length < 2) return null

  return {
    type: 'linear',
    angle: ((Number(raw.angle) || 0) % 360 + 360) % 360,
    stops,
  }
}

function badgeCanvasBackgroundStyle(
  bgColor: string,
  bgMode: 'solid' | 'gradient',
  gradient: BadgeBackgroundGradient | null,
): CSSProperties {
  if (bgMode === 'gradient' && gradient) {
    const stops = gradient.stops.map((stop) => `${stop.color} ${stop.position}%`).join(', ')
    return { background: `linear-gradient(${gradient.angle}deg, ${stops})` }
  }

  return { backgroundColor: bgColor }
}

function fieldAlignFlexStyle(item: BadgeFieldLayout): CSSProperties {
  const horizontal = resolveHorizontalAlign(item.textAlign)
  const vertical = resolveVerticalAlign(item.verticalAlign)

  return {
    display: 'flex',
    flexDirection: 'column',
    width: '100%',
    height: '100%',
    justifyContent: vertical === 'top' ? 'flex-start' : vertical === 'bottom' ? 'flex-end' : 'center',
    alignItems: horizontal === 'left' ? 'flex-start' : horizontal === 'right' ? 'flex-end' : 'center',
  }
}

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

function resolveFontSizePercent(item: BadgeFieldLayout, canvasH: number): number {
  if (item.fontSizePercent != null && Number.isFinite(item.fontSizePercent)) {
    return Math.max(0.5, Math.min(50, item.fontSizePercent))
  }

  const legacyPx = item.fontSize ?? 12

  return Math.max(0.5, Math.min(50, (legacyPx / Math.max(1, canvasH)) * 100))
}

function fontSizePxFromPercent(percent: number, canvasH: number): number {
  return Math.max(8, Math.round((Math.max(1, canvasH) * percent) / 100))
}

function normalizeFieldFontSize(item: BadgeFieldLayout, canvasH: number): BadgeFieldLayout {
  return { ...item, fontSizePercent: resolveFontSizePercent(item, canvasH) }
}

const snap = (v: number) => Math.round(v / SNAP) * SNAP
let idCounter = 0
const nextId = () => `field_${Date.now()}_${++idCounter}`

function defaultLayout(field: string, cx: number, cy: number): BadgeFieldLayout {
  const size = DEFAULT_FIELD_SIZE[field] ?? { w: 120, h: 28 }
  return {
    id: nextId(),
    field,
    x: snap(cx - size.w / 2),
    y: snap(cy - size.h / 2),
    width: size.w,
    height: size.h,
    fontSizePercent: field === 'attendee_name' ? 8 : 6,
    fontFamily: 'Inter',
    fontWeight: field === 'attendee_name' ? 'bold' : 'normal',
    color: '#000000',
    textAlign: 'center',
    verticalAlign: 'center',
    borderRadius: 0,
    rotation: 0,
    ...(field === 'custom_text' ? { text: 'Custom text' } : {}),
  }
}

/* ------------------------------------------------------------------ */
/*  Sub-components                                                     */
/* ------------------------------------------------------------------ */

function FieldPreview({ item, canvasH }: { item: BadgeFieldLayout; canvasH: number }) {
  const horizontal = resolveHorizontalAlign(item.textAlign)

  if (item.field === 'qr') {
    return (
      <QRCodeSVG
        value="SAMPLE-BADGE-QR"
        style={{ maxWidth: '100%', maxHeight: '100%', width: '100%', height: '100%' }}
      />
    )
  }

  if (item.field === 'color_code') {
    return (
      <div
        className="h-full w-full max-h-full max-w-full rounded"
        style={{ backgroundColor: item.backgroundColor || '#3b82f6' }}
      />
    )
  }

  if (item.field === 'sponsor_logo_ref' || item.field === 'organizer_logo_ref') {
    return (
      <div className="flex max-h-full max-w-full items-center justify-center border border-dashed border-gray-400 bg-gray-50 px-1 text-[10px] text-gray-400">
        {FIELD_LABELS[item.field]}
      </div>
    )
  }

  return (
    <span
      className="block max-h-full max-w-full overflow-hidden leading-tight"
      style={{
        width: '100%',
        fontSize: fontSizePxFromPercent(resolveFontSizePercent(item, canvasH), canvasH),
        fontFamily: item.fontFamily ?? 'Inter',
        fontWeight: item.fontWeight ?? 'normal',
        color: item.color ?? '#000',
        textAlign: horizontal,
      }}
    >
      {item.field === 'custom_text'
        ? (item.text?.trim() || FIELD_LABELS.custom_text)
        : (FIELD_LABELS[item.field] ?? item.field)}
    </span>
  )
}

/* ------------------------------------------------------------------ */
/*  Main Component                                                     */
/* ------------------------------------------------------------------ */

export default function BadgeTemplateDesigner({
  template,
  eventId,
  tenantId,
  onSave,
  onSaved,
  saving = false,
}: BadgeTemplateDesignerProps) {
  const { t } = useLocale()
  const { toast } = useToast()
  const [name, setName] = useState(template?.name ?? '')
  const [paperSize, setPaperSize] = useState(() => normalizePaperSize(template?.paper_size))
  const [printerType] = useState(template?.printer_type ?? 'thermal')
  const [orientation, setOrientation] = useState<'portrait' | 'landscape'>(() =>
    template?.orientation === 'landscape' ? 'landscape' : 'portrait',
  )
  const initialBgColor = normalizeHexColor(template?.background_color ?? '#ffffff')
  const initialGradient = normalizeBackgroundGradient(template?.background_gradient)
  const [bgColor, setBgColor] = useState(initialBgColor)
  const [bgMode, setBgMode] = useState<'solid' | 'gradient'>(initialGradient ? 'gradient' : 'solid')
  const [bgGradient, setBgGradient] = useState<BadgeBackgroundGradient>(
    initialGradient ?? defaultBackgroundGradient(initialBgColor),
  )
  const [bgPanelOpen, setBgPanelOpen] = useState(false)
  const bgPanelRef = useRef<HTMLDivElement>(null)
  const [status, setStatus] = useState(template?.status ?? 'draft')
  const [templateId, setTemplateId] = useState(template?.id)
  const [savingInternal, setSavingInternal] = useState(false)
  const [activating, setActivating] = useState(false)
  const [customSize, setCustomSize] = useState<{ w: number; h: number } | null>(() => {
    if (normalizePaperSize(template?.paper_size) !== 'custom') return null
    if (template?.canvas_width && template?.canvas_height) {
      return { w: template.canvas_width, h: template.canvas_height }
    }
    return { w: 298, h: 420 }
  })

  const [fields, setFields] = useState<BadgeFieldLayout[]>(() => {
    const raw = Array.isArray(template?.layout) ? template.layout as BadgeFieldLayout[] : []
    const paper = normalizePaperSize(template?.paper_size)
    const orient = template?.orientation === 'landscape' ? 'landscape' : 'portrait'
    const custom =
      paper === 'custom' && template?.canvas_width && template?.canvas_height
        ? { w: template.canvas_width, h: template.canvas_height }
        : null
    const { h } = resolveCanvasSize(paper, orient, custom)

    return raw.map((item) => normalizeFieldFontSize(item, h))
  })
  const [selectedId, setSelectedId] = useState<string | null>(null)

  const canvasRef = useRef<HTMLDivElement>(null)
  const interactionRef = useRef<{
    type: 'move' | 'resize'
    corner?: string
    startX: number
    startY: number
    origX: number
    origY: number
    origW: number
    origH: number
    fieldId: string
  } | null>(null)

  /* Canvas dimensions follow the selected paper size + orientation (not a frozen saved canvas). */
  const { w: canvasW, h: canvasH } = resolveCanvasSize(paperSize, orientation, customSize)

  useEffect(() => {
    const handlePointerDown = (event: MouseEvent) => {
      if (!bgPanelRef.current?.contains(event.target as Node)) {
        setBgPanelOpen(false)
      }
    }

    if (bgPanelOpen) {
      document.addEventListener('mousedown', handlePointerDown)
    }

    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [bgPanelOpen])

  const handleBgModeChange = (mode: 'solid' | 'gradient') => {
    setBgMode(mode)
    if (mode === 'gradient') {
      setBgGradient((current) => current ?? defaultBackgroundGradient(bgColor))
    }
  }

  const canvasBackgroundStyle = badgeCanvasBackgroundStyle(
    bgColor,
    bgMode,
    bgMode === 'gradient' ? bgGradient : null,
  )

  const bgPreviewStyle = canvasBackgroundStyle
  const wrapRef = useRef<HTMLDivElement>(null)
  const [scale, setScale] = useState(1)

  useEffect(() => {
    const el = wrapRef.current
    if (!el) return
    const observer = new ResizeObserver(([entry]) => {
      const { width, height } = entry.contentRect
      const pad = 40
      const s = Math.min((width - pad) / canvasW, (height - pad) / canvasH, 1.5)
      setScale(Math.max(0.3, s))
    })
    observer.observe(el)
    return () => observer.disconnect()
  }, [canvasW, canvasH])

  const selected = fields.find((f) => f.id === selectedId) ?? null

  /* ---- pointer helpers ---- */

  const toCanvas = useCallback(
    (clientX: number, clientY: number) => {
      const rect = canvasRef.current?.getBoundingClientRect()
      if (!rect) return { x: 0, y: 0 }
      return { x: (clientX - rect.left) / scale, y: (clientY - rect.top) / scale }
    },
    [scale],
  )

  const onFieldPointerDown = useCallback(
    (e: ReactPointerEvent, fieldId: string, corner?: string) => {
      e.stopPropagation()
      e.preventDefault()
      ;(e.target as HTMLElement).setPointerCapture(e.pointerId)
      const item = fields.find((f) => f.id === fieldId)
      if (!item) return
      setSelectedId(fieldId)
      interactionRef.current = {
        type: corner ? 'resize' : 'move',
        corner,
        startX: e.clientX,
        startY: e.clientY,
        origX: item.x,
        origY: item.y,
        origW: item.width,
        origH: item.height,
        fieldId,
      }
    },
    [fields],
  )

  const onPointerMove = useCallback(
    (e: ReactPointerEvent) => {
      const ref = interactionRef.current
      if (!ref) return
      const dx = (e.clientX - ref.startX) / scale
      const dy = (e.clientY - ref.startY) / scale

      setFields((prev) =>
        prev.map((f) => {
          if (f.id !== ref.fieldId) return f
          if (ref.type === 'move') {
            return {
              ...f,
              x: snap(Math.max(0, Math.min(canvasW - f.width, ref.origX + dx))),
              y: snap(Math.max(0, Math.min(canvasH - f.height, ref.origY + dy))),
            }
          }
          /* resize */
          let newX = f.x, newY = f.y, newW = f.width, newH = f.height
          const minSize = 20
          if (ref.corner?.includes('r')) newW = snap(Math.max(minSize, ref.origW + dx))
          if (ref.corner?.includes('b')) newH = snap(Math.max(minSize, ref.origH + dy))
          if (ref.corner?.includes('l')) {
            newW = snap(Math.max(minSize, ref.origW - dx))
            newX = snap(ref.origX + (ref.origW - newW))
          }
          if (ref.corner?.includes('t')) {
            newH = snap(Math.max(minSize, ref.origH - dy))
            newY = snap(ref.origY + (ref.origH - newH))
          }
          return { ...f, x: newX, y: newY, width: newW, height: newH }
        }),
      )
    },
    [scale, canvasW, canvasH],
  )

  const onPointerUp = useCallback(() => {
    interactionRef.current = null
  }, [])

  /* ---- field mutations ---- */

  const addField = (field: string) => {
    if (fields.some((f) => f.field === field)) return
    setFields((prev) => [...prev, defaultLayout(field, canvasW / 2, canvasH / 2)])
  }

  const deleteField = (id: string) => {
    setFields((prev) => prev.filter((f) => f.id !== id))
    if (selectedId === id) setSelectedId(null)
  }

  const updateField = (id: string, patch: Partial<BadgeFieldLayout>) => {
    setFields((prev) => prev.map((f) => (f.id === id ? { ...f, ...patch } : f)))
  }

  /* ---- save ---- */

  const handleSave = async () => {
    const trimmedName = name.trim()
    if (!trimmedName) {
      toast('Template name is required.', 'error')
      return
    }

    if (!tenantId) {
      toast(t('requestFailed'), 'error')
      return
    }

    const data: BadgeTemplateData = {
      name: trimmedName,
      paper_size: paperSize,
      printer_type: printerType,
      orientation,
      background_color: bgMode === 'gradient' ? (bgGradient.stops[0]?.color ?? bgColor) : (bgColor || null),
      background_gradient: bgMode === 'gradient' ? bgGradient : null,
      canvas_width: canvasW,
      canvas_height: canvasH,
      layout: fields,
    }

    onSave?.(data)

    setSavingInternal(true)
    try {
      const isUpdate = Boolean(templateId)
      const saved = await apiFetch<BadgeTemplate>(
        isUpdate
          ? `/api/v1/tenant/events/${eventId}/badge-templates/${templateId}`
          : `/api/v1/tenant/events/${eventId}/badge-templates`,
        {
          method: isUpdate ? 'PATCH' : 'POST',
          tenantId,
          idempotency: true,
          body: data,
        },
      )
      setTemplateId(String(saved.id))
      setStatus(saved.status)
      onSaved?.(saved)
      toast(t('saved'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setSavingInternal(false)
    }
  }

  const handleActivate = async () => {
    if (!tenantId || !templateId) {
      toast(t('badgeTemplateSaveBeforeActivate'), 'error')
      return
    }

    setActivating(true)
    try {
      const activated = await apiFetch<BadgeTemplate>(
        `/api/v1/tenant/events/${eventId}/badge-templates/${templateId}/activate`,
        {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: {},
        },
      )
      setStatus(activated.status)
      onSaved?.(activated)
      toast(t('badgeTemplateActivated'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setActivating(false)
    }
  }

  const handlePaperChange = (val: string) => {
    const next = val === 'custom' ? 'custom' : normalizePaperSize(val)
    setPaperSize(next)
    if (next === 'custom') {
      const current = resolveCanvasSize(paperSize, orientation, customSize)
      setCustomSize((prev) => prev ?? { w: current.w, h: current.h })
      return
    }

    setCustomSize(null)
    const nextSize = resolveCanvasSize(next, orientation, null)
    setFields((prev) => clampFieldsToCanvas(prev, nextSize.w, nextSize.h))
  }

  const handleCustomDimensionChange = (axis: 'w' | 'h', value: number) => {
    const nextValue = Math.max(1, Math.min(5000, Math.round(value)))
    setCustomSize((prev) => {
      const base = prev ?? { w: canvasW, h: canvasH }
      const next = axis === 'w' ? { ...base, w: nextValue } : { ...base, h: nextValue }
      setFields((fields) => clampFieldsToCanvas(fields, next.w, next.h))
      return next
    })
  }

  const handleOrientationToggle = () => {
    if (paperSize === 'custom') {
      setCustomSize((prev) => {
        const base = prev ?? { w: canvasW, h: canvasH }
        const swapped = { w: base.h, h: base.w }
        setFields((fields) => clampFieldsToCanvas(fields, swapped.w, swapped.h))
        return swapped
      })
      setOrientation((current) => (current === 'portrait' ? 'landscape' : 'portrait'))
      return
    }

    setOrientation((current) => {
      const next = current === 'portrait' ? 'landscape' : 'portrait'
      const nextSize = resolveCanvasSize(paperSize, next, null)
      setFields((prev) => clampFieldsToCanvas(prev, nextSize.w, nextSize.h))
      return next
    })
  }

  const isSaving = saving || savingInternal

  /* ---------------------------------------------------------------- */
  /*  Render                                                           */
  /* ---------------------------------------------------------------- */

  const corners = ['tl', 'tr', 'bl', 'br']
  const cornerCursors: Record<string, string> = {
    tl: 'nwse-resize', tr: 'nesw-resize', bl: 'nesw-resize', br: 'nwse-resize',
  }
  const cornerPositions: Record<string, React.CSSProperties> = {
    tl: { top: -4, left: -4 },
    tr: { top: -4, right: -4 },
    bl: { bottom: -4, left: -4 },
    br: { bottom: -4, right: -4 },
  }

  return (
    <div className="flex h-full flex-col overflow-hidden bg-gray-100">
      {/* ===== Top toolbar ===== */}
      <div className="flex items-center gap-3 border-b border-slate-700 bg-slate-800 px-4 py-2 text-sm text-white">
        <input
          value={name}
          onChange={(e) => setName(e.target.value)}
          placeholder="Template name…"
          className="w-48 rounded bg-slate-700 px-2 py-1 text-white placeholder-slate-400 outline-none focus:ring-1 focus:ring-blue-500"
        />

        <select
          value={paperSize}
          onChange={(e) => handlePaperChange(e.target.value)}
          className="rounded bg-slate-700 px-2 py-1 text-white outline-none"
        >
          {Object.keys(PAPER_PRESETS).map((k) => (
            <option key={k} value={k}>{k} ({PAPER_PRESETS[k].w}×{PAPER_PRESETS[k].h})</option>
          ))}
          <option value="custom">Custom</option>
        </select>

        {paperSize === 'custom' && (
          <div className="flex items-center gap-1.5">
            <PropNumber
              label="W"
              value={customSize?.w ?? canvasW}
              min={1}
              max={5000}
              compact
              onChange={(v) => handleCustomDimensionChange('w', v)}
            />
            <PropNumber
              label="H"
              value={customSize?.h ?? canvasH}
              min={1}
              max={5000}
              compact
              onChange={(v) => handleCustomDimensionChange('h', v)}
            />
          </div>
        )}

        <button
          type="button"
          onClick={handleOrientationToggle}
          className="rounded bg-slate-700 px-2 py-1 transition hover:bg-slate-600"
        >
          {orientation === 'portrait' ? '▯ Portrait' : '▭ Landscape'}
        </button>

        <div className="relative" ref={bgPanelRef}>
          <button
            type="button"
            onClick={() => setBgPanelOpen((open) => !open)}
            className="flex items-center gap-1.5 rounded bg-slate-700 px-2 py-1 transition hover:bg-slate-600"
          >
            <span className="text-slate-300">BG</span>
            <span
              className="h-6 w-10 rounded border border-slate-500"
              style={bgPreviewStyle}
            />
          </button>

          {bgPanelOpen && (
            <div className="absolute left-0 top-full z-50 mt-1 w-72 rounded-lg border border-slate-600 bg-slate-800 p-3 shadow-xl">
              <div className="mb-3 flex gap-1">
                {(['solid', 'gradient'] as const).map((mode) => (
                  <button
                    key={mode}
                    type="button"
                    onClick={() => handleBgModeChange(mode)}
                    className={`flex-1 rounded px-2 py-1 text-xs capitalize transition ${
                      bgMode === mode ? 'bg-blue-600 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                    }`}
                  >
                    {mode}
                  </button>
                ))}
              </div>

              {bgMode === 'solid' ? (
                <label className="flex items-center justify-between gap-3">
                  <span className="text-xs text-slate-400">Color</span>
                  <input
                    type="color"
                    value={bgColor}
                    onChange={(e) => setBgColor(normalizeHexColor(e.target.value))}
                    className="h-8 w-12 cursor-pointer rounded border-0 bg-transparent"
                  />
                </label>
              ) : (
                <div className="space-y-3">
                  <label className="flex items-center justify-between gap-3">
                    <span className="text-xs text-slate-400">From</span>
                    <input
                      type="color"
                      value={bgGradient.stops[0]?.color ?? bgColor}
                      onChange={(e) => {
                        const color = normalizeHexColor(e.target.value)
                        setBgGradient((current) => ({
                          ...current,
                          stops: [
                            { ...current.stops[0], color },
                            current.stops[1] ?? { color: darkenHexColor(color, 0.28), position: 100 },
                          ],
                        }))
                      }}
                      className="h-8 w-12 cursor-pointer rounded border-0 bg-transparent"
                    />
                  </label>
                  <label className="flex items-center justify-between gap-3">
                    <span className="text-xs text-slate-400">To</span>
                    <input
                      type="color"
                      value={bgGradient.stops[1]?.color ?? darkenHexColor(bgColor, 0.28)}
                      onChange={(e) => {
                        const color = normalizeHexColor(e.target.value)
                        setBgGradient((current) => ({
                          ...current,
                          stops: [
                            current.stops[0] ?? { color: bgColor, position: 0 },
                            { ...current.stops[1], color, position: 100 },
                          ],
                        }))
                      }}
                      className="h-8 w-12 cursor-pointer rounded border-0 bg-transparent"
                    />
                  </label>
                  <PropNumber
                    label="Angle (deg)"
                    value={Math.round(bgGradient.angle)}
                    min={0}
                    max={359}
                    onChange={(angle) => setBgGradient((current) => ({ ...current, angle }))}
                  />
                  <div
                    className="h-10 w-full rounded border border-slate-600"
                    style={badgeCanvasBackgroundStyle(bgColor, 'gradient', bgGradient)}
                  />
                </div>
              )}
            </div>
          )}
        </div>

        <div className="flex-1" />

        <span
          className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
            status === 'active'
              ? 'bg-emerald-500/20 text-emerald-300'
              : 'bg-amber-500/20 text-amber-300'
          }`}
        >
          {status === 'active' ? t('badgeTemplateStatusActive') : t('badgeTemplateStatusDraft')}
        </span>

        {status !== 'active' && templateId ? (
          <button
            type="button"
            onClick={() => void handleActivate()}
            disabled={activating || isSaving}
            className="rounded bg-emerald-600 px-3 py-1 font-medium transition hover:bg-emerald-500 disabled:opacity-50"
          >
            {activating ? t('badgeTemplateActivating') : t('badgeTemplateActivate')}
          </button>
        ) : null}

        <button
          type="button"
          onClick={() => void handleSave()}
          disabled={isSaving}
          className="flex items-center gap-1.5 rounded bg-blue-600 px-3 py-1 font-medium transition hover:bg-blue-500 disabled:opacity-50"
        >
          <Save size={14} />
          {isSaving ? 'Saving…' : 'Save'}
        </button>
      </div>

      {/* ===== Main 3-panel layout ===== */}
      <div className="flex flex-1 overflow-hidden">
        {/* --- Left: Field Palette --- */}
        <aside className="flex w-56 flex-col border-r border-slate-700 bg-slate-800 text-sm text-white">
          <div className="border-b border-slate-700 px-3 py-2 font-semibold text-slate-300">
            Fields
          </div>
          <div className="flex-1 overflow-y-auto p-2">
            {AVAILABLE_FIELDS.map((field) => {
              const Icon = FIELD_ICONS[field] ?? Type
              const used = fields.some((f) => f.field === field)
              return (
                <div
                  key={field}
                  className={`mb-1 flex items-center rounded px-2 py-1.5 ${
                    used ? 'opacity-40' : 'hover:bg-slate-700'
                  }`}
                >
                  <Icon size={14} className="mr-2 shrink-0 text-slate-400" />
                  <span className="flex-1 truncate">{FIELD_LABELS[field]}</span>
                  <button
                    type="button"
                    disabled={used}
                    onClick={() => addField(field)}
                    className="ml-1 rounded p-0.5 text-slate-400 transition hover:bg-slate-600 hover:text-white disabled:cursor-not-allowed disabled:text-slate-600"
                  >
                    <Plus size={14} />
                  </button>
                </div>
              )
            })}
          </div>
          <div className="border-t border-slate-700 px-3 py-2 text-xs text-slate-500">
            {fields.length} field{fields.length !== 1 && 's'} placed
          </div>
        </aside>

        {/* --- Center: Canvas --- */}
        <div
          ref={wrapRef}
          className="relative flex flex-1 items-center justify-center overflow-auto bg-gray-200 p-6"
          onClick={() => setSelectedId(null)}
        >
          <div
            className="relative shrink-0"
            style={{ width: canvasW * scale, height: canvasH * scale }}
          >
            <div
              ref={canvasRef}
              className="relative shadow-lg"
              style={{
                width: canvasW,
                height: canvasH,
                ...canvasBackgroundStyle,
                transform: `scale(${scale})`,
                transformOrigin: 'top left',
              }}
              onPointerMove={onPointerMove}
              onPointerUp={onPointerUp}
              onPointerLeave={onPointerUp}
              onClick={(e) => {
                e.stopPropagation()
                setSelectedId(null)
              }}
            >
              {fields.map((item) => {
                const isSelected = item.id === selectedId
                return (
                  <div
                    key={item.id}
                    className="absolute"
                    style={{
                      left: item.x,
                      top: item.y,
                      width: item.width,
                      height: item.height,
                      backgroundColor: item.backgroundColor ?? 'transparent',
                      borderRadius: item.borderRadius ?? 0,
                      transform: item.rotation ? `rotate(${item.rotation}deg)` : undefined,
                      outline: isSelected ? '2px solid #3b82f6' : '1px dashed #cbd5e1',
                      outlineOffset: isSelected ? 1 : 0,
                      cursor: 'move',
                      userSelect: 'none',
                      touchAction: 'none',
                      zIndex: isSelected ? 50 : 1,
                      overflow: 'hidden',
                    }}
                    onPointerDown={(e) => onFieldPointerDown(e, item.id)}
                    onClick={(e) => {
                      e.stopPropagation()
                      setSelectedId(item.id)
                    }}
                  >
                    <div style={fieldAlignFlexStyle(item)}>
                      <FieldPreview item={item} canvasH={canvasH} />
                    </div>

                    {isSelected &&
                      corners.map((c) => (
                        <div
                          key={c}
                          className="absolute z-50 h-2.5 w-2.5 rounded-sm border border-white bg-blue-500"
                          style={{
                            ...cornerPositions[c],
                            cursor: cornerCursors[c],
                          }}
                          onPointerDown={(e) => {
                            const corner =
                              c === 'tl' ? 'tl' : c === 'tr' ? 'tr' : c === 'bl' ? 'bl' : 'br'
                            onFieldPointerDown(e, item.id, corner)
                          }}
                        />
                      ))}
                  </div>
                )
              })}

              {/* Grid dots overlay */}
              <svg
                className="pointer-events-none absolute inset-0"
                width={canvasW}
                height={canvasH}
                style={{ opacity: 0.08 }}
              >
                <defs>
                  <pattern id="grid" width={SNAP * 4} height={SNAP * 4} patternUnits="userSpaceOnUse">
                    <circle cx={1} cy={1} r={0.5} fill="#000" />
                  </pattern>
                </defs>
                <rect width="100%" height="100%" fill="url(#grid)" />
              </svg>
            </div>
          </div>

          {/* Canvas size label */}
          <div className="absolute bottom-2 right-2 rounded bg-black/40 px-2 py-0.5 text-xs text-white">
            {canvasW} × {canvasH}px &nbsp;·&nbsp; {Math.round(scale * 100)}% · {orientation}
          </div>
        </div>

        {/* --- Right: Property Inspector --- */}
        <aside className="flex flex-col border-l border-slate-700 bg-slate-800 text-sm text-white">
          <div className="border-b border-slate-700 px-3 py-2 font-semibold text-slate-300">
            Properties
          </div>

          {selected ? (
            <div className="flex-1 space-y-3 overflow-y-auto p-3">
              <div className="rounded bg-slate-700/60 px-2 py-1 text-xs font-medium text-blue-300">
                <GripVertical size={12} className="mr-1 inline" />
                {FIELD_LABELS[selected.field] ?? selected.field}
              </div>

              {/* Position */}
              <fieldset className="space-y-1.5">
                <legend className="text-xs font-semibold text-slate-400">Position</legend>
                <div className="grid grid-cols-2 gap-2">
                  <PropNumber label="X" value={selected.x} onChange={(v) => updateField(selected.id, { x: snap(v) })} />
                  <PropNumber label="Y" value={selected.y} onChange={(v) => updateField(selected.id, { y: snap(v) })} />
                  <PropNumber label="W" value={selected.width} onChange={(v) => updateField(selected.id, { width: snap(Math.max(20, v)) })} />
                  <PropNumber label="H" value={selected.height} onChange={(v) => updateField(selected.id, { height: snap(Math.max(20, v)) })} />
                </div>
              </fieldset>

              {selected.field === 'custom_text' && (
                <fieldset className="space-y-1.5">
                  <legend className="text-xs font-semibold text-slate-400">Content</legend>
                  <label className="block">
                    <span className="mb-0.5 block text-xs text-slate-400">Text</span>
                    <input
                      type="text"
                      value={selected.text ?? ''}
                      onChange={(e) => updateField(selected.id, { text: e.target.value })}
                      placeholder="Custom text…"
                      className="w-full rounded bg-slate-700 px-2 py-1 text-white outline-none focus:ring-1 focus:ring-blue-500"
                    />
                  </label>
                </fieldset>
              )}

              {/* Alignment — all field types */}
              <fieldset className="space-y-1.5">
                <legend className="text-xs font-semibold text-slate-400">Alignment</legend>
                <div>
                  <span className="mb-1 block text-xs text-slate-400">Horizontal</span>
                  <div className="flex gap-1">
                    {(['left', 'center', 'right'] as const).map((a) => (
                      <button
                        key={a}
                        type="button"
                        onClick={() => updateField(selected.id, { textAlign: a })}
                        className={`flex-1 rounded px-2 py-1 text-xs transition ${
                          resolveHorizontalAlign(selected.textAlign) === a
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                        }`}
                      >
                        {a === 'left' ? 'Left' : a === 'center' ? 'Center' : 'Right'}
                      </button>
                    ))}
                  </div>
                </div>
                <div>
                  <span className="mb-1 block text-xs text-slate-400">Vertical</span>
                  <div className="flex gap-1">
                    {(['top', 'center', 'bottom'] as const).map((a) => (
                      <button
                        key={a}
                        type="button"
                        onClick={() => updateField(selected.id, { verticalAlign: a })}
                        className={`flex-1 rounded px-2 py-1 text-xs transition ${
                          resolveVerticalAlign(selected.verticalAlign) === a
                            ? 'bg-blue-600 text-white'
                            : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                        }`}
                      >
                        {a === 'top' ? 'Top' : a === 'center' ? 'Middle' : 'Bottom'}
                      </button>
                    ))}
                  </div>
                </div>
              </fieldset>

              {/* Typography (hide for non-text fields) */}
              {!['qr', 'sponsor_logo_ref', 'organizer_logo_ref', 'color_code'].includes(selected.field) && (
                <fieldset className="space-y-1.5">
                  <legend className="text-xs font-semibold text-slate-400">Typography</legend>
                  <PropNumber
                    label="Size (%)"
                    value={Math.round(resolveFontSizePercent(selected, canvasH) * 10) / 10}
                    min={0.5}
                    max={50}
                    step={0.1}
                    onChange={(v) => updateField(selected.id, { fontSizePercent: v })}
                  />
                  <PropSelect
                    label="Font"
                    value={selected.fontFamily ?? 'Inter'}
                    options={FONT_OPTIONS}
                    onChange={(v) => updateField(selected.id, { fontFamily: v })}
                  />
                  <PropSelect
                    label="Weight"
                    value={selected.fontWeight ?? 'normal'}
                    options={['normal', 'bold']}
                    onChange={(v) => updateField(selected.id, { fontWeight: v })}
                  />
                  <PropColor label="Color" value={selected.color ?? '#000000'} onChange={(v) => updateField(selected.id, { color: v })} />
                </fieldset>
              )}

              {/* Appearance */}
              <fieldset className="space-y-1.5">
                <legend className="text-xs font-semibold text-slate-400">Appearance</legend>
                <div className="space-y-1.5">
                  <span className="block text-xs text-slate-400">Background</span>
                  <div className="flex gap-1">
                    <button
                      type="button"
                      onClick={() => updateField(selected.id, { backgroundColor: undefined })}
                      className={`flex-1 rounded px-2 py-1 text-xs transition ${
                        !selected.backgroundColor
                          ? 'bg-blue-600 text-white'
                          : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                      }`}
                    >
                      Transparent
                    </button>
                    <button
                      type="button"
                      onClick={() => updateField(selected.id, {
                        backgroundColor: selected.backgroundColor || '#ffffff',
                      })}
                      className={`flex-1 rounded px-2 py-1 text-xs transition ${
                        selected.backgroundColor
                          ? 'bg-blue-600 text-white'
                          : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                      }`}
                    >
                      Color
                    </button>
                  </div>
                  {selected.backgroundColor ? (
                    <PropColor
                      label="Fill"
                      value={selected.backgroundColor}
                      onChange={(v) => updateField(selected.id, { backgroundColor: v || undefined })}
                    />
                  ) : null}
                </div>
                <PropNumber label="Radius" value={selected.borderRadius ?? 0} min={0} max={100} onChange={(v) => updateField(selected.id, { borderRadius: v })} />
                <PropSelect
                  label="Rotation"
                  value={String(selected.rotation ?? 0)}
                  options={['0', '90', '180', '270']}
                  onChange={(v) => updateField(selected.id, { rotation: Number(v) })}
                />
              </fieldset>

              <button
                type="button"
                onClick={() => deleteField(selected.id)}
                className="mt-4 flex w-full items-center justify-center gap-1.5 rounded bg-red-600/80 px-3 py-1.5 text-xs font-medium text-white transition hover:bg-red-600"
              >
                <Trash2 size={13} />
                Delete field
              </button>
            </div>
          ) : paperSize === 'custom' ? (
            <div className="flex flex-1 flex-col gap-3 overflow-y-auto p-4 text-xs text-slate-400">
              <p className="font-semibold text-slate-300">Canvas</p>
              <p>Use the width and height inputs in the toolbar to set a custom badge size (1–5000 px).</p>
              <p className="text-slate-500">Portrait / Landscape swaps the current width and height.</p>
            </div>
          ) : (
            <div className="flex flex-1 items-center justify-center p-4 text-center text-xs text-slate-500">
              Select a field on the canvas to edit its properties
            </div>
          )}
        </aside>
      </div>
    </div>
  )
}

/* ------------------------------------------------------------------ */
/*  Property input primitives                                          */
/* ------------------------------------------------------------------ */

function PropNumber({
  label,
  value,
  min,
  max,
  step,
  compact = false,
  onChange,
}: {
  label: string
  value: number
  min?: number
  max?: number
  step?: number
  compact?: boolean
  onChange: (v: number) => void
}) {
  return (
    <label className={compact ? 'block w-20' : 'block'}>
      <span className={`mb-0.5 block text-xs text-slate-400${compact ? ' text-center' : ''}`}>{label}</span>
      <input
        type="number"
        value={value}
        min={min}
        max={max}
        step={step}
        onChange={(e) => onChange(Number(e.target.value))}
        className={`rounded bg-slate-700 py-1 text-white outline-none focus:ring-1 focus:ring-blue-500${compact ? ' w-full px-1 text-center' : ' w-full px-2'}`}
      />
    </label>
  )
}

function PropSelect({
  label,
  value,
  options,
  onChange,
}: {
  label: string
  value: string
  options: string[]
  onChange: (v: string) => void
}) {
  return (
    <label className="block">
      <span className="mb-0.5 block text-xs text-slate-400">{label}</span>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded bg-slate-700 px-2 py-1 text-white outline-none focus:ring-1 focus:ring-blue-500"
      >
        {options.map((o) => (
          <option key={o} value={o}>{o}</option>
        ))}
      </select>
    </label>
  )
}

function PropColor({
  label,
  value,
  onChange,
}: {
  label: string
  value: string
  onChange: (v: string) => void
}) {
  return (
    <label className="block">
      <span className="mb-0.5 block text-xs text-slate-400">{label}</span>
      <div className="flex gap-1.5">
        <input
          type="color"
          value={value || '#ffffff'}
          onChange={(e) => onChange(e.target.value)}
          className="h-7 w-7 shrink-0 cursor-pointer rounded border-0 bg-transparent"
        />
        <input
          type="text"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder="#000000"
          className="w-full rounded bg-slate-700 px-2 py-1 text-white outline-none focus:ring-1 focus:ring-blue-500"
        />
      </div>
    </label>
  )
}
