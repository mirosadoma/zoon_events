import { Link } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  fields: Array<{ id: string; label: string; type: string; required: boolean }>
}

export default function RegistrationBuilder({ event, fields }: Props) {
  const { locale } = useLocale()

  return (
    <DashboardLayout title={locale === 'ar' ? 'نموذج التسجيل' : 'Registration form'}>
      <PageHeader
        title={locale === 'ar' ? 'نموذج التسجيل' : 'Registration form'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'نموذج التسجيل' : 'Registration form' },
        ]}
        actions={<Link className="button-secondary" href={`/tenant/events/${event.id}/registration-preview`}>{locale === 'ar' ? 'معاينة' : 'Preview'}</Link>}
      />
      <PageContent>
        {fields.length === 0 ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد حقول بعد' : 'No fields yet'}
            detail={locale === 'ar' ? 'أضف حقول التسجيل من هذه الصفحة عند توفر إجراءات الحفظ.' : 'Add registration fields from this page once save actions are wired.'}
          />
        ) : (
          <ul className="space-y-2">
            {fields.map((field) => (
              <li key={field.id} className="state-panel">
                <strong>{field.label}</strong>
                <p className="text-sm text-slate-600">{field.type}{field.required ? ' • required' : ''}</p>
              </li>
            ))}
          </ul>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
