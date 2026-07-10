import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'

export default function DashboardSection({ section, items }: { section: string; scope: string; items: unknown[] }) {
  const title = section.replace('-', ' ')

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        description="Authorized platform records for this section."
        breadcrumbs={[
          { label: 'Overview', href: '/dashboard' },
          { label: title },
        ]}
      />
      <PageContent>
        {items.length === 0 ? (
          <EmptyState title="No records" detail="No authorized records are available in this scope." />
        ) : (
          <pre className="overflow-x-auto rounded-xl bg-slate-100 p-4 text-sm dark:bg-slate-900">{JSON.stringify(items, null, 2)}</pre>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
