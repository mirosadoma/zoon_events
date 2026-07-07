import { FormEvent, useState } from 'react'

interface ReprintDialogProps {
  printJobId: string
  onSubmit: (reason: string) => void
  onCancel: () => void
}

export function ReprintDialog({ onSubmit, onCancel }: ReprintDialogProps) {
  const [reason, setReason] = useState('')

  function handleSubmit(e: FormEvent<HTMLFormElement>) {
    e.preventDefault()
    if (reason.trim()) {
      onSubmit(reason.trim())
    }
  }

  return (
    <div>
      <h2>Reprint Badge</h2>
      <form onSubmit={handleSubmit}>
        <label>
          Reason for reprint:
          <textarea
            value={reason}
            onChange={e => setReason(e.target.value)}
            required
            maxLength={500}
          />
        </label>
        <button type="submit" disabled={!reason.trim()}>Reprint</button>
        <button type="button" onClick={onCancel}>Cancel</button>
      </form>
    </div>
  )
}
