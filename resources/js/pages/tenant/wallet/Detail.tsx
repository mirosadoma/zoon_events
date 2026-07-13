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

export default function WalletPassDetailPage({ event, walletPass }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={walletPass.serial}>
      <PageHeader
        title={walletPass.serial}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'تذاكر المحفظة' : 'Wallet passes', href: `/tenant/events/${event.id}/wallet-passes` },
          { label: walletPass.serial },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'تفاصيل تذكرة المحفظة' : 'Wallet pass details'}
          items={[
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={walletPass.status} /> },
            { label: locale === 'ar' ? 'المزود' : 'Provider', value: walletPass.provider },
            {
              label: locale === 'ar' ? 'الحاضر' : 'Attendee',
              value: (
                <LocalizedLink href={`/tenant/events/${event.id}/attendees/${walletPass.attendee_id}`} className="text-sky-700 hover:underline">
                  {walletPass.attendee_id.slice(-8)}
                </LocalizedLink>
              ),
            },
            {
              label: locale === 'ar' ? 'بيانات الدخول' : 'Credential',
              value: (
                <LocalizedLink href={`/tenant/events/${event.id}/credentials/${walletPass.credential_id}`} className="text-sky-700 hover:underline">
                  {walletPass.credential_id.slice(-8)}
                </LocalizedLink>
              ),
            },
            { label: locale === 'ar' ? 'آخر دفع' : 'Last pushed', value: walletPass.last_pushed_at ?? '—' },
            { label: locale === 'ar' ? 'سبب الدفع' : 'Push reason', value: walletPass.last_push_reason_code ?? '—' },
            {
              label: locale === 'ar' ? 'رابط التذكرة' : 'Pass URL',
              value: walletPass.pass_url
                ? <a href={walletPass.pass_url} className="text-sky-700 hover:underline" target="_blank" rel="noreferrer">{walletPass.pass_url}</a>
                : '—',
            },
          ]}
        />
      </PageContent>
    </DashboardLayout>
  )
}
