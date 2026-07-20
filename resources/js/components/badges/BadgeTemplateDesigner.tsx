import { useState, useRef, useCallback, useEffect, type PointerEvent as ReactPointerEvent } from 'react'
import { QRCodeSVG } from 'qrcode.react'
import {
  Type, Building2, Briefcase, QrCode, Ticket, Layers, MapPin,
  Image, Palette, PenLine, Plus, Trash2, Save, GripVertical, UserCheck,
} from 'lucide-react'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { useToast } from '@/hooks/useToast'
import { useLocale } from '@/hooks/useLocale'
import type { BadgeTemplate } from '@/types/phase3'

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
  fontFamily?: string
  fontWeight?: string
  color?: string
  textAlign?: 'left' | 'center' | 'right'
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
  const preset = paperSize === 'custom' && custom
    ? custom
    : (PAPER_PRESETS[paperSize] ?? PAPER_PRESETS.A6)

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

/* ------------------------------------------------------------------ */
/*  Helpers                                                            */
/* ------------------------------------------------------------------ */

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
    fontSize: field === 'attendee_name' ? 16 : 12,
    fontFamily: 'Inter',
    fontWeight: field === 'attendee_name' ? 'bold' : 'normal',
    color: '#000000',
    textAlign: 'center',
    borderRadius: 0,
    rotation: 0,
    ...(field === 'custom_text' ? { text: 'Custom text' } : {}),
  }
}

/* ------------------------------------------------------------------ */
/*  Sub-components                                                     */
/* ------------------------------------------------------------------ */

function FieldPreview({ item }: { item: BadgeFieldLayout }) {
  if (item.field === 'qr') {
    return <QRCodeSVG value="SAMPLE-BADGE-QR" width="100%" height="100%" />
  }

  if (item.field === 'color_code') {
    return (
      <div
        className="h-full w-full rounded"
        style={{ backgroundColor: item.backgroundColor || '#3b82f6' }}
      />
    )
  }

  if (item.field === 'sponsor_logo_ref' || item.field === 'organizer_logo_ref') {
    return (
      <div className="flex h-full w-full items-center justify-center border border-dashed border-gray-400 bg-gray-50 text-[10px] text-gray-400">
        {FIELD_LABELS[item.field]}
      </div>
    )
  }

  return (
    <span
      className="block h-full w-full overflow-hidden leading-tight"
      style={{
        fontSize: item.fontSize ?? 12,
        fontFamily: item.fontFamily ?? 'Inter',
        fontWeight: item.fontWeight ?? 'normal',
        color: item.color ?? '#000',
        textAlign: item.textAlign ?? 'left',
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
  const [bgColor, setBgColor] = useState(template?.background_color ?? '#ffffff')
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

  const [fields, setFields] = useState<BadgeFieldLayout[]>(
    Array.isArray(template?.layout) ? template.layout as BadgeFieldLayout[] : [],
  )
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

  /* Scale canvas to fit the viewport area */
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
      background_color: bgColor || null,
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
    const next = normalizePaperSize(val === 'custom' ? 'custom' : val)
    setPaperSize(next)
    if (next === 'custom') {
      setCustomSize((prev) => prev ?? { w: canvasW, h: canvasH })
    } else {
      setCustomSize(null)
      const nextSize = resolveCanvasSize(next, orientation, null)
      setFields((prev) => clampFieldsToCanvas(prev, nextSize.w, nextSize.h))
    }
  }

  const handleOrientationToggle = () => {
    setOrientation((current) => {
      const next = current === 'portrait' ? 'landscape' : 'portrait'
      const nextSize = resolveCanvasSize(paperSize, next, customSize)
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

        <button
          type="button"
          onClick={handleOrientationToggle}
          className="rounded bg-slate-700 px-2 py-1 transition hover:bg-slate-600"
        >
          {orientation === 'portrait' ? '▯ Portrait' : '▭ Landscape'}
        </button>

        <label className="flex items-center gap-1.5">
          <span className="text-slate-300">BG</span>
          <input
            type="color"
            value={bgColor}
            onChange={(e) => setBgColor(e.target.value)}
            className="h-6 w-6 cursor-pointer rounded border-0 bg-transparent"
          />
        </label>

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
                backgroundColor: bgColor,
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
                    <FieldPreview item={item} />

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
        <aside className="flex w-64 flex-col border-l border-slate-700 bg-slate-800 text-sm text-white">
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

              {/* Typography (hide for non-text fields) */}
              {!['qr', 'sponsor_logo_ref', 'organizer_logo_ref', 'color_code'].includes(selected.field) && (
                <fieldset className="space-y-1.5">
                  <legend className="text-xs font-semibold text-slate-400">Typography</legend>
                  <PropNumber label="Size" value={selected.fontSize ?? 12} min={8} max={72} onChange={(v) => updateField(selected.id, { fontSize: v })} />
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

                  <div>
                    <span className="mb-1 block text-xs text-slate-400">Align</span>
                    <div className="flex gap-1">
                      {(['left', 'center', 'right'] as const).map((a) => (
                        <button
                          key={a}
                          type="button"
                          onClick={() => updateField(selected.id, { textAlign: a })}
                          className={`flex-1 rounded px-2 py-1 text-xs transition ${
                            selected.textAlign === a
                              ? 'bg-blue-600 text-white'
                              : 'bg-slate-700 text-slate-300 hover:bg-slate-600'
                          }`}
                        >
                          {a[0].toUpperCase() + a.slice(1)}
                        </button>
                      ))}
                    </div>
                  </div>
                </fieldset>
              )}

              {/* Appearance */}
              <fieldset className="space-y-1.5">
                <legend className="text-xs font-semibold text-slate-400">Appearance</legend>
                <PropColor label="Background" value={selected.backgroundColor ?? ''} onChange={(v) => updateField(selected.id, { backgroundColor: v || undefined })} />
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
  onChange,
}: {
  label: string
  value: number
  min?: number
  max?: number
  onChange: (v: number) => void
}) {
  return (
    <label className="block">
      <span className="mb-0.5 block text-xs text-slate-400">{label}</span>
      <input
        type="number"
        value={value}
        min={min}
        max={max}
        onChange={(e) => onChange(Number(e.target.value))}
        className="w-full rounded bg-slate-700 px-2 py-1 text-white outline-none focus:ring-1 focus:ring-blue-500"
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
