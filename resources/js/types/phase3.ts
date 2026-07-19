export interface Kiosk {
  id: string
  device_name: string
  device_code: string
  status: 'registered' | 'online' | 'offline' | 'degraded' | 'retired'
  printer_status: 'unknown' | 'ready' | 'error' | 'disconnected'
  last_heartbeat_at: string | null
  confirmation_required: boolean
}

export interface BadgeTemplate {
  id: string
  name: string
  layout: Record<string, unknown> | unknown[]
  paper_size: string
  printer_type: string
  orientation?: string
  background_color?: string | null
  canvas_width?: number | null
  canvas_height?: number | null
  status: 'draft' | 'active' | 'inactive'
}

export interface BadgePrintJob {
  id: string
  status: 'queued' | 'printed' | 'failed'
  failure_reason: string | null
  is_reprint: boolean
  reprint_reason: string | null
  original_print_job_id: string | null
  printed_at: string | null
}

export const BADGE_TEMPLATE_ALLOWED_FIELDS = [
  'attendee_name',
  'company',
  'job_title',
  'qr',
  'ticket_type',
  'tier',
  'zone',
  'sponsor_logo_ref',
  'organizer_logo_ref',
  'color_code',
  'custom_text',
] as const

export type BadgeTemplateField = (typeof BADGE_TEMPLATE_ALLOWED_FIELDS)[number]
