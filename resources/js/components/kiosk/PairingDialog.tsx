import ConfirmModal from '@/components/modals/ConfirmModal'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

interface PairingDialogProps {
  kiosk: Kiosk
  onConfirm: (kioskId: string) => void
  onCancel: () => void
}

export function PairingDialog({ kiosk, onConfirm, onCancel }: PairingDialogProps) {
  const { locale } = useLocale()
  const ar = locale === 'ar'

  return (
    <ConfirmModal
      open
      title={ar ? `إقران الكشك: ${kiosk.device_name}` : `Pair kiosk: ${kiosk.device_name}`}
      message={ar
        ? 'سيتم إنشاء رمز جلسة جديد لهذا الجهاز. أكمل الإقران فقط على الجهاز المقصود.'
        : 'A new session secret will be issued for this device. Only complete pairing on the intended kiosk.'}
      confirmLabel={ar ? 'إقران' : 'Pair'}
      cancelLabel={ar ? 'إلغاء' : 'Cancel'}
      onConfirm={() => onConfirm(kiosk.id)}
      onCancel={onCancel}
    >
      <dl className="grid gap-2 rounded-xl border border-[var(--border)] bg-[var(--surface)] p-3 text-sm">
        <div className="flex justify-between gap-3">
          <dt className="text-[var(--muted)]">{ar ? 'رمز الجهاز' : 'Device code'}</dt>
          <dd className="font-mono font-medium text-[var(--ink)]">{kiosk.device_code}</dd>
        </div>
        <div className="flex justify-between gap-3">
          <dt className="text-[var(--muted)]">{ar ? 'الحالة' : 'Status'}</dt>
          <dd className="font-medium text-[var(--ink)]">{kiosk.status}</dd>
        </div>
      </dl>
    </ConfirmModal>
  )
}
