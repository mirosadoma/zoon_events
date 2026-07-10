import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState, DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type TenantRow = {
  id: string
  name: string
  slug: string
  default_locale: string
  timezone: string
  status: string
}

type ConfigurationRow = {
  key: string
  schema_version: string
  status: string
  value: unknown
}

type Props = {
  tenantId: string
  tenant: TenantRow
  configurations: ConfigurationRow[]
}

export default function AdminTenantSettings({ tenant, configurations }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en

  return (
    <DashboardLayout title={messages.tenantSettings}>
      <PageHeader
        title={messages.tenantSettings}
        description={messages.adminTenantSettingsDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.administration, href: '/admin/users' },
          { label: messages.tenantSettings },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={messages.profileTenant}
          items={[
            { label: messages.profileName, value: tenant.name },
            { label: 'Slug', value: tenant.slug },
            { label: messages.adminDefaultLocale, value: tenant.default_locale },
            { label: messages.adminTimezone, value: tenant.timezone },
            { label: messages.orderStatus, value: <StatusBadge status={tenant.status} /> },
          ]}
        />

        <div className="mt-6">
          <h2 className="mb-3 text-lg font-semibold">{messages.configuration}</h2>
          {configurations.length === 0 ? (
            <EmptyState title={messages.adminNoConfigurations} />
          ) : (
            <div className="space-y-3">
              {configurations.map((configuration) => (
                <DetailsCard
                  key={configuration.key}
                  title={configuration.key}
                  items={[
                    { label: messages.adminSchemaVersion, value: configuration.schema_version },
                    { label: messages.orderStatus, value: <StatusBadge status={configuration.status} /> },
                    {
                      label: messages.adminConfigurationValue,
                      value: (
                        <pre className="overflow-x-auto rounded bg-slate-100 p-2 text-xs dark:bg-slate-800">
                          {JSON.stringify(configuration.value, null, 2)}
                        </pre>
                      ),
                    },
                  ]}
                />
              ))}
            </div>
          )}
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
