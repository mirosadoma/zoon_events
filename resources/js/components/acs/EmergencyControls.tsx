import { useState } from 'react'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'

type EmergencyControlsProps = {
  eventId: string
  tenantId: string
  activeEmergency: boolean
  onChanged?: () => void
}

export function EmergencyControls({ eventId, tenantId, activeEmergency, onChanged }: EmergencyControlsProps) {
  const { locale } = useLocale()
  const [raiseOpen, setRaiseOpen] = useState(false)
  const [clearOpen, setClearOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  async function submitEmergency(action: 'raise' | 'clear') {
    setLoading(true)
    try {
      await fetch(`/api/v1/tenant/events/${eventId}/acs/emergency`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify({ action }),
      })
      onChanged?.()
    } finally {
      setLoading(false)
      setRaiseOpen(false)
      setClearOpen(false)
    }
  }

  return (
    <PermissionGate permission="acs.emergency.manage">
      <div className="flex flex-wrap gap-3">
        {!activeEmergency && (
          <button type="button" className="button-primary" onClick={() => setRaiseOpen(true)}>
            {locale === 'ar' ? 'تفعيل خروج الطوارئ' : 'Raise emergency egress'}
          </button>
        )}
        {activeEmergency && (
          <button type="button" className="button-secondary" onClick={() => setClearOpen(true)}>
            {locale === 'ar' ? 'إلغاء خروج الطوارئ' : 'Clear emergency egress'}
          </button>
        )}
      </div>

      <ReasonModal
        open={raiseOpen}
        title={locale === 'ar' ? 'تفعيل خروج الطوارئ' : 'Raise emergency egress'}
        message={locale === 'ar' ? 'يرجى تقديم سبب لتفعيل خروج الطوارئ.' : 'Please provide a reason for raising emergency egress.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'تفعيل' : 'Raise'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        loading={loading}
        onConfirm={() => void submitEmergency('raise')}
        onCancel={() => setRaiseOpen(false)}
      />

      <ConfirmModal
        open={clearOpen}
        title={locale === 'ar' ? 'إلغاء خروج الطوارئ' : 'Clear emergency egress'}
        message={locale === 'ar' ? 'هل أنت متأكد من إلغاء خروج الطوارئ؟' : 'Are you sure you want to clear emergency egress?'}
        confirmLabel={locale === 'ar' ? 'إلغاء الطوارئ' : 'Clear emergency'}
        cancelLabel={locale === 'ar' ? 'إغلاق' : 'Cancel'}
        loading={loading}
        onConfirm={() => void submitEmergency('clear')}
        onCancel={() => setClearOpen(false)}
      />
    </PermissionGate>
  )
}
