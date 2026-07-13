import { useCallback, useLayoutEffect, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'

type TargetRect = {
  top: number
  left: number
  width: number
  height: number
}

type Props = {
  open: boolean
  onClose: () => void
  title?: string
  messages: string[]
  targetSelector?: string | null
}

const PADDING = 8

function readTargetRect(selector: string): TargetRect | null {
  const element = document.querySelector(selector)

  if (!(element instanceof HTMLElement)) {
    return null
  }

  const rect = element.getBoundingClientRect()

  if (rect.width === 0 && rect.height === 0) {
    return null
  }

  return {
    top: rect.top,
    left: rect.left,
    width: rect.width,
    height: rect.height,
  }
}

export default function ValidationHintPopover({
  open,
  onClose,
  title,
  messages,
  targetSelector = null,
}: Props) {
  const { t } = useLocale()
  const [targetRect, setTargetRect] = useState<TargetRect | null>(null)

  const refreshTarget = useCallback(() => {
    if (!targetSelector) {
      setTargetRect(null)
      return
    }

    setTargetRect(readTargetRect(targetSelector))
  }, [targetSelector])

  useLayoutEffect(() => {
    if (!open) {
      return
    }

    const element = targetSelector ? document.querySelector(targetSelector) : null
    if (element instanceof HTMLElement && typeof element.scrollIntoView === 'function') {
      element.scrollIntoView({ block: 'center', inline: 'nearest', behavior: 'smooth' })
    }

    const refreshFrame = window.requestAnimationFrame(refreshTarget)
    const settleTimer = window.setTimeout(refreshTarget, 180)

    const onReflow = () => refreshTarget()
    window.addEventListener('resize', onReflow)
    window.addEventListener('scroll', onReflow, true)

    return () => {
      window.cancelAnimationFrame(refreshFrame)
      window.clearTimeout(settleTimer)
      window.removeEventListener('resize', onReflow)
      window.removeEventListener('scroll', onReflow, true)
    }
  }, [open, refreshTarget, targetSelector])

  if (!open || messages.length === 0) {
    return null
  }

  const resolvedTitle = title ?? t('checkTheFields')
  const spotlight = targetRect
    ? {
        top: Math.max(targetRect.top - PADDING, 8),
        left: Math.max(targetRect.left - PADDING, 8),
        width: targetRect.width + PADDING * 2,
        height: targetRect.height + PADDING * 2,
      }
    : null

  const popoverLeft = spotlight
    ? Math.min(Math.max(spotlight.left, 16), window.innerWidth - 360)
    : Math.max((window.innerWidth - 352) / 2, 16)
  const popoverStyle = spotlight
    ? {
        bottom: Math.max(window.innerHeight - spotlight.top + 16, 16),
        left: popoverLeft,
        maxHeight: Math.max(80, spotlight.top - 32),
        overflowY: 'auto' as const,
      }
    : {
        top: Math.max(window.innerHeight / 2 - 140, 16),
        left: popoverLeft,
      }

  return (
    <>
      {spotlight ? (
        <button
          type="button"
          className="product-tour-spotlight"
          style={{
            top: spotlight.top,
            left: spotlight.left,
            width: spotlight.width,
            height: spotlight.height,
          }}
          aria-label={t('close')}
          onClick={onClose}
        />
      ) : (
        <div className="product-tour-overlay" aria-hidden onClick={onClose} />
      )}

      <div
        className="product-tour-popover fixed z-[90] w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4 shadow-2xl"
        style={popoverStyle}
        role="alertdialog"
        aria-labelledby="validation-hint-title"
      >
        <p className="text-xs font-semibold uppercase tracking-wide text-red-600">
          {t('validationError')}
        </p>
        <h2 id="validation-hint-title" className="mt-1 text-lg font-semibold">
          {resolvedTitle}
        </h2>
        <ul className="mt-3 space-y-2">
          {messages.map((message) => (
            <li
              key={message}
              className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
            >
              {message}
            </li>
          ))}
        </ul>
        <div className="mt-4 flex justify-end">
          <button type="button" className="button-primary text-sm" onClick={onClose}>
            {t('gotIt')}
          </button>
        </div>
      </div>
    </>
  )
}
