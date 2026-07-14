import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type WalletPassDetail = {
  id: string
  provider: string
  serial: string
  attendee_id: string
  credential_id: string
  status: string
  pass_url?: string | null
  last_pushed_at?: string | null
  last_push_reason_code?: string | null
  pass_content_updated_at?: string | null
}

type Props = {
  event: EventRow
  walletPass: WalletPassDetail
}

function shortId(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') {
    return '—'
  }

  return String(value).slice(-8)
}

export default function WalletPassDetailPage({ event, walletPass }: Props) {
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const serial = walletPass.serial || walletPass.id
  const attendeeId = String(walletPass.attendee_id ?? '')
  const credentialId = String(walletPass.credential_id ?? '')

  return (
    <DashboardLayout title={serial}>
      <PageHeader
        title={serial}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: ar ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: ar ? 'تذاكر المحفظة' : 'Wallet passes', href: `/tenant/events/${event.id}/wallet-passes` },
          { label: serial },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/wallet-passes`}>
            {ar ? 'العودة للقائمة' : 'Back to list'}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <DetailsCard
          title={ar ? 'تفاصيل تذكرة المحفظة' : 'Wallet pass details'}
          items={[
            { label: ar ? 'الحالة' : 'Status', value: <StatusBadge status={walletPass.status} /> },
            { label: ar ? 'المزود' : 'Provider', value: walletPass.provider },
            {
              label: ar ? 'الحاضر' : 'Attendee',
              value: attendeeId ? (
                <LocalizedLink href={`/tenant/events/${event.id}/attendees/${attendeeId}`} className="text-sky-700 hover:underline">
                  {shortId(attendeeId)}
                </LocalizedLink>
              ) : '—',
            },
            {
              label: ar ? 'بيانات الدخول' : 'Credential',
              value: credentialId ? (
                <LocalizedLink href={`/tenant/events/${event.id}/credentials/${credentialId}`} className="text-sky-700 hover:underline">
                  {shortId(credentialId)}
                </LocalizedLink>
              ) : '—',
            },
            { label: ar ? 'آخر دفع' : 'Last pushed', value: walletPass.last_pushed_at ?? '—' },
            { label: ar ? 'سبب الدفع' : 'Push reason', value: walletPass.last_push_reason_code ?? '—' },
            {
              label: ar ? 'رابط التذكرة' : 'Pass URL',
              value: walletPass.pass_url
                ? (
                  <a href={walletPass.pass_url} className="break-all text-sky-700 hover:underline" target="_blank" rel="noreferrer">
                    {walletPass.pass_url}
                  </a>
                  )
                : '—',
            },
          ]}
        />
      </PageContent>
    </DashboardLayout>
  )
}
