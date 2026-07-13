import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type WalletPassRow = {
  id: string
  provider: string
  serial: string
  attendee_id?: string | null
  status: string
  pass_url?: string | null
  last_pushed_at?: string | null
}

type Props = {
  event: EventRow
  walletPasses: WalletPassRow[]
}

export default function WalletPasses({ event, walletPasses }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={locale === 'ar' ? 'تذاكر المحفظة' : 'Wallet passes'}>
      <PageHeader
        title={locale === 'ar' ? 'تذاكر المحفظة' : 'Wallet passes'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'تذاكر المحفظة' : 'Wallet passes' },
        ]}
      />
      <PageContent>
        {walletPasses.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد تذاكر محفظة' : 'No wallet passes yet'}
            detail={locale === 'ar' ? 'ستظهر التذاكر بعد الإصدار.' : 'Passes will appear after generation.'}
          />
        ) : (
          <DataTable
            rows={walletPasses as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'serial',
                header: locale === 'ar' ? 'الرقم التسلسلي' : 'Serial',
                render: (row) => {
                  const pass = row as unknown as WalletPassRow

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/wallet-passes/${pass.id}`} className="font-medium text-sky-700 hover:underline">
                      {pass.serial}
                    </LocalizedLink>
                  )
                },
              },
              { key: 'provider', header: locale === 'ar' ? 'المزود' : 'Provider' },
              {
                key: 'status',
                header: locale === 'ar' ? 'الحالة' : 'Status',
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'attendee_id',
                header: locale === 'ar' ? 'الحاضر' : 'Attendee',
                render: (row) => {
                  const pass = row as unknown as WalletPassRow

                  if (!pass.attendee_id) {
                    return '—'
                  }

                  return (
                    <LocalizedLink href={`/tenant/events/${event.id}/attendees/${pass.attendee_id}`} className="text-sky-700 hover:underline">
                      {String(pass.attendee_id).slice(-8)}
                    </LocalizedLink>
                  )
                },
              },
              { key: 'last_pushed_at', header: locale === 'ar' ? 'آخر دفع' : 'Last pushed' },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
