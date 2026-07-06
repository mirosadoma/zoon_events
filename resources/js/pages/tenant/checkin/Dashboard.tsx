import { CheckInCounters, type CheckInSummaryView } from '@/components/checkin/CheckInCounters'
import { CHECK_IN_SUMMARY_POLL_INTERVAL_MS } from '@/lib/checkin-polling'
import { useEffect, useState } from 'react'

interface CheckInDashboardProps {
  eventId: string
  tenantId: string
  locale?: 'en' | 'ar'
  initialSummary?: CheckInSummaryView | null
  pollIntervalMs?: number
}

const emptySummary: CheckInSummaryView = {
  registered_count: 0,
  checked_in_count: 0,
  rejected_count: 0,
  duplicate_count: 0,
  last_scan_at: null,
}

export default function CheckInDashboard({
  eventId,
  tenantId,
  locale = 'en',
  initialSummary = null,
  pollIntervalMs = CHECK_IN_SUMMARY_POLL_INTERVAL_MS,
}: CheckInDashboardProps) {
  const [summary, setSummary] = useState<CheckInSummaryView | null>(initialSummary)
  const [loading, setLoading] = useState(initialSummary === null)
  const [error, setError] = useState<string | null>(null)

  const title = locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard'
  const emptyLabel = locale === 'ar' ? 'لا توجد عمليات مسح بعد' : 'No scans yet'
  const errorLabel = locale === 'ar' ? 'تعذر تحميل ملخص تسجيل الحضور' : 'Unable to load check-in summary'

  useEffect(() => {
    let active = true

    async function loadSummary() {
      try {
        const response = await fetch(`/api/v1/tenant/events/${eventId}/check-in-summary`, {
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'X-Tenant-ID': tenantId,
          },
        })

        if (!response.ok) {
          throw new Error('summary_failed')
        }

        const body = await response.json()
        if (active) {
          setSummary(body.data as CheckInSummaryView)
          setError(null)
        }
      } catch {
        if (active) {
          setError(errorLabel)
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadSummary()
    const timer = window.setInterval(() => {
      void loadSummary()
    }, pollIntervalMs)

    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [eventId, tenantId, pollIntervalMs, errorLabel])

  const isEmpty = summary !== null
    && summary.checked_in_count === 0
    && summary.rejected_count === 0
    && summary.duplicate_count === 0

  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{title}</h1>
      {loading ? <p role="status">{locale === 'ar' ? 'جارٍ التحميل…' : 'Loading…'}</p> : null}
      {error ? <p role="alert">{error}</p> : null}
      {!loading && !error && summary !== null && isEmpty ? (
        <p role="status">{emptyLabel}</p>
      ) : null}
      {!loading && !error && summary !== null && !isEmpty ? (
        <CheckInCounters summary={summary} locale={locale} />
      ) : null}
      {!loading && !error && summary !== null && isEmpty ? (
        <CheckInCounters summary={emptySummary} locale={locale} />
      ) : null}
    </main>
  )
}
