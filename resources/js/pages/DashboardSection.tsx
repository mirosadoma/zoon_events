import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'

export default function DashboardSection({ section, items }: { section: string; scope: string; items: unknown[] }) {
  const { t } = useLocale()
  const title = section.replace('-', ' ')

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        description={t('authorizedSectionRecords')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: title },
        ]}
      />
      <PageContent>
        {items.length === 0 ? (
          <EmptyState title={t('noRecords')} detail={t('noAuthorizedRecords')} />
        ) : (
          <pre className="overflow-x-auto rounded-xl bg-slate-100 p-4 text-sm dark:bg-slate-900">{JSON.stringify(items, null, 2)}</pre>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
