interface LayoutEntry {
  field: string
  x: number
  y: number
  width: number
  height: number
}

interface BadgePreviewCanvasProps {
  entries: LayoutEntry[]
  canvasWidth?: number
  canvasHeight?: number
}

const SAMPLE_VALUES: Record<string, string> = {
  attendee_name: 'Jane Smith',
  company: 'Acme Corp',
  job_title: 'Engineer',
  qr: '▣ QR',
  ticket_type: 'General Admission',
  tier: 'Gold',
  zone: 'Hall A',
  sponsor_logo_ref: '[Sponsor Logo]',
  organizer_logo_ref: '[Organizer Logo]',
  color_code: '#4f46e5',
}

export function BadgePreviewCanvas({
  entries,
  canvasWidth = 340,
  canvasHeight = 500,
}: BadgePreviewCanvasProps) {
  return (
    <div
      style={{
        position: 'relative',
        width: canvasWidth,
        height: canvasHeight,
        border: '1px solid #ccc',
        background: '#fff',
        overflow: 'hidden',
      }}
    >
      {entries.map(entry => (
        <div
          key={entry.field}
          style={{
            position: 'absolute',
            left: entry.x,
            top: entry.y,
            width: entry.width,
            height: entry.height,
            border: '1px dashed #94a3b8',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            fontSize: 11,
            color: '#334155',
            background: '#f8fafc',
          }}
        >
          {SAMPLE_VALUES[entry.field] ?? entry.field}
        </div>
      ))}
      {entries.length === 0 && (
        <p style={{ textAlign: 'center', marginTop: 60, color: '#94a3b8' }}>
          No fields placed yet
        </p>
      )}
    </div>
  )
}
