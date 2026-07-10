import type { Kiosk } from '../../types/phase3'

interface PairingDialogProps {
  kiosk: Kiosk
  onConfirm: (kioskId: string) => void
  onCancel: () => void
}

export function PairingDialog({ kiosk, onConfirm, onCancel }: PairingDialogProps) {
  return (
    <div>
      <h2>Pair Kiosk: {kiosk.device_name}</h2>
      <p>Device code: {kiosk.device_code}</p>
      <button type="button" onClick={() => onConfirm(kiosk.id)}>Pair</button>
      <button type="button" onClick={onCancel}>Cancel</button>
    </div>
  )
}
