import { useState } from 'react'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  disabled?: boolean
  onSubmit: (capture: string) => Promise<void>
}

export default function FaceCapturePanel({ disabled = false, onSubmit }: Props) {
  const { t } = useLocale()
  const [capture, setCapture] = useState('')
  const [busy, setBusy] = useState(false)

  async function handleSubmit() {
    if (capture.trim().length < 8) {
      return
    }
    setBusy(true)
    try {
      await onSubmit(capture.trim())
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className="identity-face-capture" aria-labelledby="identity-face-capture-title">
      <h2 id="identity-face-capture-title">{t('identityFaceCaptureTitle')}</h2>
      <p>{t('identityFaceCaptureDescription')}</p>
      <label className="block">
        <span className="sr-only">{t('identityFaceCaptureInput')}</span>
        <textarea
          className="mt-2 w-full rounded-lg border border-slate-300 p-3"
          rows={4}
          value={capture}
          disabled={disabled || busy}
          onChange={(event) => setCapture(event.target.value)}
          placeholder={t('identityFaceCapturePlaceholder')}
        />
      </label>
      <button
        type="button"
        className="button-primary mt-4"
        disabled={disabled || busy || capture.trim().length < 8}
        onClick={() => void handleSubmit()}
      >
        {busy ? t('identityVerifyInProgress') : t('identityFaceCaptureSubmit')}
      </button>
    </section>
  )
}
