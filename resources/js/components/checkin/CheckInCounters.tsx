export interface CheckInSummaryView {
  registered_count: number
  checked_in_count: number
  rejected_count: number
  duplicate_count: number
  last_scan_at: string | null
}

interface CheckInCountersProps {
  summary: CheckInSummaryView
  locale?: 'en' | 'ar'
}

export function CheckInCounters({ summary, locale = 'en' }: CheckInCountersProps) {
  const labels = locale === 'ar'
    ? {
        checkedIn: 'تم تسجيل الحضور',
        rejected: 'مرفوض',
        duplicate: 'مكرر',
        registered: 'مسجل',
      }
    : {
        checkedIn: 'Checked in',
        rejected: 'Rejected',
        duplicate: 'Duplicate',
        registered: 'Registered',
      }

  return (
    <section aria-label={labels.checkedIn}>
      <dl>
        <div>
          <dt>{labels.registered}</dt>
          <dd data-testid="registered-count">{summary.registered_count}</dd>
        </div>
        <div>
          <dt>{labels.checkedIn}</dt>
          <dd data-testid="checked-in-count">{summary.checked_in_count}</dd>
        </div>
        <div>
          <dt>{labels.rejected}</dt>
          <dd data-testid="rejected-count">{summary.rejected_count}</dd>
        </div>
        <div>
          <dt>{labels.duplicate}</dt>
          <dd data-testid="duplicate-count">{summary.duplicate_count}</dd>
        </div>
      </dl>
    </section>
  )
}
