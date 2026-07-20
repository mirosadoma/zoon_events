import ConfirmModal from '@/components/modals/ConfirmModal'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

interface PairingDialogProps {
  open?: boolean
  kiosk: Kiosk | null
  onConfirm: (kioskId: string) => void
  onCancel: () => void
}

export function PairingDialog({ open = true, kiosk, onConfirm, onCancel }: PairingDialogProps) {
  const { locale, t } = useLocale()
  const ar = locale === 'ar'

  if (!kiosk) {
    return null
  }

  return (
    <ConfirmModal
      open={open}
      title={ar ? `إقران الكشك: ${kiosk.device_name}` : `Pair kiosk: ${kiosk.device_name}`}
      message={t('pairingDialogMessage')}
      confirmLabel={t('pairingDialogConfirm')}
      cancelLabel={t('cancel')}
      onConfirm={() => onConfirm(kiosk.id)}
      onCancel={onCancel}
    >
      <dl className="grid gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3 text-sm">
        <div className="flex justify-between gap-3">
          <dt className="text-[var(--muted)]">{t('pairingDialogDeviceCode')}</dt>
          <dd className="font-mono font-medium text-[var(--ink)]">{kiosk.device_code}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-[var(--muted)]">{t('status')}</dt>
          <dd className="font-medium text-[var(--ink)]">{kiosk.status}</dd>
        </div>
      </dl>
    </ConfirmModal>
  )
}
