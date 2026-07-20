import { router } from '@inertiajs/react'

/**
 * After browser Back/Forward, Inertia restores cached page props.
 * Re-fetch the restored page so KPIs and lists stay current.
 */
export function installReloadOnBrowserHistory(): void {
  let awaitingHistoryRestore = false
  let reloading = false
  let clearAwaitingTimer: ReturnType<typeof setTimeout> | null = null

  window.addEventListener('popstate', () => {
    awaitingHistoryRestore = true

    if (clearAwaitingTimer) {
      clearTimeout(clearAwaitingTimer)
    }

    // Drop the flag if Inertia never restores (e.g. missing history → hard navigation).
    clearAwaitingTimer = setTimeout(() => {
      awaitingHistoryRestore = false
    }, 2000)
  })

  router.on('navigate', () => {
    if (!awaitingHistoryRestore || reloading) {
      return
    }

    awaitingHistoryRestore = false
    if (clearAwaitingTimer) {
      clearTimeout(clearAwaitingTimer)
      clearAwaitingTimer = null
    }

    reloading = true

    router.reload({
      preserveScroll: true,
      onFinish: () => {
        reloading = false
      },
    })
  })

  // bfcache restore (no popstate) — full reload to get fresh props
  window.addEventListener('pageshow', (event: PageTransitionEvent) => {
    if (event.persisted) {
      window.location.reload()
    }
  })
}
