import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

type EventSetupProps = {
  event: {
    id: string | null
    name: { en: string; ar: string }
    status: string
    tier: string
    readiness: string[]
  }
  can: {
    manage: boolean
    publish: boolean
  }
}

export default function EventSetup({ event, can }: EventSetupProps) {
  const { locale } = useLocale()
  const title = event.id ? event.name[locale] : (locale === 'ar' ? 'فعالية جديدة' : 'New event')

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        description={locale === 'ar' ? 'إعداد بيانات الفعالية الأساسية.' : 'Configure core event details.'}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: title },
        ]}
      />
      <PageContent>
        <section className="state-panel space-y-4">
          <div className="flex flex-wrap items-center gap-3">
            <StatusBadge status={event.status} />
            <span className="text-sm text-slate-600">{event.tier}</span>
          </div>
          <div className="grid gap-4 md:grid-cols-2">
            <div>
              <p className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الاسم بالإنجليزية' : 'English name'}</p>
              <p className="mt-1 font-medium">{event.name.en}</p>
            </div>
            <div>
              <p className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الاسم بالعربية' : 'Arabic name'}</p>
              <p className="mt-1 font-medium">{event.name.ar}</p>
            </div>
          </div>
          {event.readiness.length > 0 && (
            <section aria-labelledby="readiness-heading">
              <h2 id="readiness-heading" className="text-lg font-semibold">{locale === 'ar' ? 'جاهزية النشر' : 'Publication readiness'}</h2>
              <ul className="mt-2 list-disc ps-5 text-sm text-slate-600">
                {event.readiness.map((item) => <li key={item}>{item}</li>)}
              </ul>
            </section>
          )}
          <div className="flex flex-wrap gap-3">
            {can.manage && <SubmitButtonWithLoader label={locale === 'ar' ? 'حفظ التغييرات' : 'Save changes'} type="button" />}
            <PermissionGate permission="event.publish">
              <SubmitButtonWithLoader label={locale === 'ar' ? 'نشر' : 'Publish'} type="button" disabled={event.readiness.length > 0} />
            </PermissionGate>
          </div>
        </section>
      </PageContent>
    </DashboardLayout>
  )
}
