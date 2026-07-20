import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import Pagination from '@/components/tables/Pagination'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { defaultPagination, type PaginationMeta, withPage } from '@/lib/pagination'

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
  pagination?: PaginationMeta
}

export default function WalletPasses({
  event,
  walletPasses,
  pagination = defaultPagination,
}: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()

  function changePage(page: number) {
    localizedRouter.get(`/tenant/events/${event.id}/wallet-passes`, withPage({}, page), {
      preserveState: true,
      preserveScroll: true,
    })
  }

  return (
    <DashboardLayout title={t('walletPasses')}>
      <PageHeader
        title={t('walletPasses')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('walletPasses') },
        ]}
      />
      <PageContent>
        {walletPasses.length === 0 ? (
          <EmptyState
            title={t('walletPassesNoWalletPasses')}
            detail={t('walletPassesNoWalletPassesDetail')}
          />
        ) : (
          <>
            <DataTable
              rows={walletPasses as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'serial',
                  header: t('walletPassesSerial'),
                  render: (row) => {
                    const pass = row as unknown as WalletPassRow

                    return (
                      <LocalizedLink href={`/tenant/events/${event.id}/wallet-passes/${pass.id}`} className="font-medium text-sky-700 hover:underline">
                        {pass.serial}
                      </LocalizedLink>
                    )
                  },
                },
                { key: 'provider', header: t('walletPassesProvider') },
                {
                  key: 'status',
                  header: t('walletPassesStatus'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'attendee_id',
                  header: t('attendees'),
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
                { key: 'last_pushed_at', header: t('walletPassesLastPushed') },
              ]}
            />
            <Pagination
              page={pagination.page}
              totalPages={pagination.last_page}
              onPageChange={changePage}
              previousLabel={t('previousPage')}
              nextLabel={t('nextPage')}
              pageLabel={t('pageOf').replace(':page', String(pagination.page)).replace(':total', String(pagination.last_page))}
            />
          </>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
