import FoundationLayout from '@/layouts/FoundationLayout'
import { EmptyState } from '@/components/foundation/States'

export default function DashboardSection({ section, items }: { section: string; scope: string; items: unknown[] }) {
  return <FoundationLayout><h1 className="text-3xl font-semibold capitalize">{section.replace('-', ' ')}</h1><div className="mt-6">{items.length === 0 ? <EmptyState title="No records" detail="No authorized records are available in this scope." /> : <pre>{JSON.stringify(items, null, 2)}</pre>}</div></FoundationLayout>
}
