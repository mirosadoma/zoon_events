import { useState } from 'react'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'
import { apiFetch } from '@/lib/apiFetch'

type EmergencyControlsProps = {
  eventId: string
  tenantId: string
  activeEmergency: boolean
  onChanged?: () => void
}

export function EmergencyControls({ eventId, tenantId, activeEmergency, onChanged }: EmergencyControlsProps) {
  const { locale, t } = useLocale()
  const [raiseOpen, setRaiseOpen] = useState(false)
  const [clearOpen, setClearOpen] = useState(false)
  const [loading, setLoading] = useState(false)

  async function submitEmergency(action: 'raise' | 'clear') {
    setLoading(true)
    try {
      await apiFetch(`/api/v1/tenant/events/${eventId}/acs/emergency`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { action },
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
            {t('acsEmergencyRaise')}
          </button>
        )}
        {activeEmergency && (
          <button type="button" className="button-secondary" onClick={() => setClearOpen(true)}>
            {t('acsEmergencyClear')}
          </button>
        )}
      </div>

      <ReasonModal
        open={raiseOpen}
        title={t('acsEmergencyRaiseTitle')}
        message={t('acsEmergencyRaiseMessage')}
        reasonLabel={t('acsEmergencyReason')}
        confirmLabel={t('acsEmergencyRaiseConfirm')}
        cancelLabel={t('cancel')}
        loading={loading}
        onConfirm={() => void submitEmergency('raise')}
        onCancel={() => setRaiseOpen(false)}
      />

      <ConfirmModal
        open={clearOpen}
        title={t('acsEmergencyClearTitle')}
        message={t('acsEmergencyClearMessage')}
        confirmLabel={t('acsEmergencyClearConfirm')}
        cancelLabel={t('cancel')}
        loading={loading}
        onConfirm={() => void submitEmergency('clear')}
        onCancel={() => setClearOpen(false)}
      />
    </PermissionGate>
  )
}
