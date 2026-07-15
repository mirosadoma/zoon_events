import { usePage } from '@inertiajs/react'
import { useCallback, useEffect, useLayoutEffect, useMemo, useState } from 'react'
import { useIsDesktop } from '@/hooks/useMediaQuery'
import { useLocale } from '@/hooks/useLocale'
import {
  buildTourSteps,
  normalizePermissionList,
  resolveTourProfile,
  tourStorageKey,
  type TourStep,
} from '@/lib/productTour'
import en from '@/locales/en'
import ar from '@/locales/ar'

type PageProps = {
  auth?: { user?: { id?: string | number } | null }
  session?: { role_label?: string | null; user?: { id?: string | number } | null }
  permissions?: string[] | Record<string, boolean>
  can?: Record<string, boolean>
}

type TargetRect = {
  top: number
  left: number
  width: number
  height: number
}

type ProductTourProps = {
  errorHint?: string | null
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

export default function ProductTour({ errorHint }: ProductTourProps) {
  const { locale } = useLocale()
  const isDesktop = useIsDesktop()
  const messages = locale === 'ar' ? ar : en
  const { props } = usePage<PageProps>()
  const userId = props.auth?.user?.id ?? props.session?.user?.id
  // Prefer shared string[] permissions; fall back to `can` map. Page-local
  // action flags must not use the `permissions` prop key (see marketplace ViewModels).
  const permissions = normalizePermissionList(
    Array.isArray(props.permissions) ? props.permissions : (props.can ?? props.permissions),
  )
  const profile = resolveTourProfile(props.session?.role_label, permissions)
  const steps = useMemo(() => buildTourSteps(profile, permissions), [permissions, profile])
  const storageKey = userId ? tourStorageKey(userId, profile) : null

  const [open, setOpen] = useState(false)
  const [index, setIndex] = useState(0)
  const [hintMessage, setHintMessage] = useState<string | null>(null)
  const [targetRect, setTargetRect] = useState<TargetRect | null>(null)

  const current: TourStep | null = steps[index] ?? null

  const refreshTarget = useCallback(() => {
    if (!current) {
      setTargetRect(null)
      return
    }

    const element = document.querySelector(current.target)

    if (element instanceof HTMLElement) {
      element.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: 'smooth' })
    }

    window.setTimeout(() => {
      setTargetRect(readTargetRect(current.target))
    }, 180)
  }, [current])

  const finish = useCallback(() => {
    if (storageKey) {
      window.localStorage.setItem(storageKey, '1')
    }
    setOpen(false)
    setIndex(0)
    setTargetRect(null)
  }, [storageKey])

  useEffect(() => {
    if (!isDesktop) {
      setOpen(false)
      return undefined
    }

    const onHint = (event: Event) => {
      const detail = (event as CustomEvent<{ message?: string }>).detail
      if (detail?.message) {
        setHintMessage(detail.message)
        setOpen(true)
        setIndex(0)
      }
    }

    window.addEventListener('zonetec:tour-hint', onHint)

    return () => window.removeEventListener('zonetec:tour-hint', onHint)
  }, [isDesktop])

  useEffect(() => {
    if (!isDesktop) {
      return undefined
    }

    const onStart = () => {
      setHintMessage(null)
      setIndex(0)
      setOpen(true)
    }

    window.addEventListener('zonetec:tour-start', onStart)

    return () => window.removeEventListener('zonetec:tour-start', onStart)
  }, [isDesktop])

  useEffect(() => {
    if (!isDesktop || !storageKey || steps.length === 0) {
      return
    }

    if (window.localStorage.getItem(storageKey) === '1') {
      return
    }

    const timer = window.setTimeout(() => setOpen(true), 800)

    return () => window.clearTimeout(timer)
  }, [isDesktop, steps.length, storageKey])

  useEffect(() => {
    if (!isDesktop || !errorHint || steps.length === 0) {
      return
    }

    setOpen(true)
    setIndex(0)
  }, [errorHint, isDesktop, steps.length])

  useLayoutEffect(() => {
    if (!isDesktop || !open || !current) {
      return
    }

    refreshTarget()

    const onReflow = () => refreshTarget()
    window.addEventListener('resize', onReflow)
    window.addEventListener('scroll', onReflow, true)

    return () => {
      window.removeEventListener('resize', onReflow)
      window.removeEventListener('scroll', onReflow, true)
    }
  }, [current, index, isDesktop, open, refreshTarget])

  if (!isDesktop || !open || !current) {
    return null
  }

  const spotlight = targetRect
    ? {
        top: Math.max(targetRect.top - PADDING, 8),
        left: Math.max(targetRect.left - PADDING, 8),
        width: targetRect.width + PADDING * 2,
        height: targetRect.height + PADDING * 2,
      }
    : null

  const popoverTop = spotlight
    ? Math.min(spotlight.top + spotlight.height + 16, window.innerHeight - 240)
    : window.innerHeight / 2 - 120

  const popoverLeft = spotlight
    ? Math.min(Math.max(spotlight.left, 16), window.innerWidth - 360)
    : Math.max((window.innerWidth - 352) / 2, 16)

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
          aria-label={messages.productTourSkip}
          onClick={finish}
        />
      ) : (
        <div className="product-tour-overlay" aria-hidden onClick={finish} />
      )}

      <div
        className="product-tour-popover fixed z-[90] w-[min(22rem,calc(100vw-2rem))] rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4 shadow-2xl"
        style={{ top: popoverTop, left: popoverLeft }}
        role="dialog"
        aria-labelledby="product-tour-title"
      >
        <p className="text-xs font-semibold uppercase tracking-wide text-[var(--brand)]">
          {messages.productTourKicker}
        </p>
        <h2 id="product-tour-title" className="mt-1 text-lg font-semibold">
          {current.title[locale]}
        </h2>
        <p className="mt-2 text-sm text-[var(--muted)]">{current.body[locale]}</p>
        {!spotlight ? (
          <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            {locale === 'ar' ? 'افتح القائمة الجانبية أو انتقل للصفحة المناسبة لمتابعة الجولة.' : 'Open the sidebar or navigate to the relevant page to continue the tour.'}
          </p>
        ) : null}
        {hintMessage || errorHint ? (
          <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
            {hintMessage ?? errorHint}
          </p>
        ) : null}
        <div className="mt-4 flex items-center justify-between gap-3">
          <span className="text-xs text-[var(--muted)]">
            {index + 1} / {steps.length}
          </span>
          <div className="flex gap-2">
            <button type="button" className="button-secondary text-sm" onClick={finish}>
              {messages.productTourSkip}
            </button>
            {index < steps.length - 1 ? (
              <button
                type="button"
                className="button-primary text-sm"
                onClick={() => setIndex((value) => value + 1)}
              >
                {messages.productTourNext}
              </button>
            ) : (
              <button type="button" className="button-primary text-sm" onClick={finish}>
                {messages.productTourDone}
              </button>
            )}
          </div>
        </div>
      </div>
    </>
  )
}
