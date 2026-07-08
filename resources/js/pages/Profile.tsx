import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Profile = {
  name: string
  email: string
  phone?: string | null
  role: string
  tenant?: { id: string; name: string; slug: string } | null
  last_login_at?: string | null
}

type Props = {
  profile: Profile
}

export default function Profile({ profile }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en

  return (
    <DashboardLayout title={messages.profileTitle}>
      <PageHeader
        title={messages.profileTitle}
        breadcrumbs={[
          { label: messages.overview, href: '/' },
          { label: messages.profile },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={messages.profileDetails}
          items={[
            { label: messages.profileName, value: profile.name },
            { label: messages.profileEmail, value: profile.email },
            { label: messages.profilePhone, value: profile.phone ?? '—' },
            { label: messages.profileRole, value: profile.role },
            { label: messages.profileTenant, value: profile.tenant?.name ?? '—' },
            { label: messages.profileLastLogin, value: profile.last_login_at ?? '—' },
          ]}
        />
      </PageContent>
    </DashboardLayout>
  )
}
