import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type BadgeTone = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'neutral' | 'emphasis'

const STATUS_TONE: Record<string, BadgeTone> = {
  draft: 'neutral',
  published: 'success',
  cancelled: 'danger',
  active: 'success',
  inactive: 'neutral',
  pending: 'warning',
  not_required: 'neutral',
  gov_verified: 'success',
  face_verified: 'success',
  manually_approved: 'info',
  revoked: 'danger',
  expired: 'warning',
  reissued: 'info',
  paid: 'success',
  failed: 'danger',
  printing: 'info',
  printed: 'success',
  accepted: 'success',
  rejected: 'danger',
  duplicate: 'warning',
  offline: 'neutral',
  online: 'success',
  registered: 'info',
  not_registered: 'neutral',
  attended: 'success',
  not_attended: 'warning',
  retired: 'neutral',
  ready: 'success',
  error: 'danger',
  disconnected: 'warning',
  manual_override: 'success',
  ok: 'success',
  unavailable: 'danger',
  system: 'info',
  healthy: 'success',
  degraded: 'warning',
  unknown: 'neutral',
  complete: 'success',
  open: 'info',
  closed: 'neutral',
  paired: 'success',
  unpaired: 'warning',
  queued: 'info',
  suspended: 'warning',
  archived: 'neutral',
  maintenance: 'warning',
  requested: 'info',
  completed: 'success',
  under_review: 'warning',
  resolved: 'success',
  issued: 'info',
  superseded: 'neutral',
  private: 'neutral',
  withdrawn: 'warning',
  reserved: 'info',
  released: 'neutral',
  none: 'neutral',
}

const TONE_CLASS: Record<BadgeTone, string> = {
  primary: 'ta-badge-primary',
  success: 'ta-badge-success',
  warning: 'ta-badge-warning',
  danger: 'ta-badge-danger',
  info: 'ta-badge-info',
  neutral: 'ta-badge-neutral',
  emphasis: 'ta-badge-emphasis',
}

type StatusBadgeProps = {
  status: string
  label?: string
  size?: 'sm' | 'md'
}

export default function StatusBadge({ status, label, size = 'sm' }: StatusBadgeProps) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const normalized = status.toLowerCase().replace(/\s+/g, '_')
  const tone = STATUS_TONE[normalized] ?? 'neutral'
  const statusLabels = messages.statusLabels
  const resolvedLabel = label ?? statusLabels[normalized as keyof typeof statusLabels] ?? status.replace(/_/g, ' ')

  return (
    <span
      className={clsx(
        'ta-badge',
        TONE_CLASS[tone],
        size === 'md' && 'px-3 py-1 text-sm',
      )}
    >
      {resolvedLabel}
    </span>
  )
}
