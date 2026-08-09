import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import PlatformChatBot from '@/components/ai/PlatformChatBot'
import { useLocale } from '@/hooks/useLocale'
import { AlertCircle } from 'lucide-react'

type Props = {
  tenantId: string
  aiAvailable: boolean
  aiStatus?: {
    available: boolean
    adapter: string
    reason?: string | null
    hint?: string | null
  }
}

export default function PlatformChat({ tenantId, aiAvailable, aiStatus }: Props) {
  const { locale, t } = useLocale()

  return (
    <DashboardLayout title={t('platformChatTitle') || 'AI Assistant'}>
      <PageHeader
        title={t('platformChatTitle') || 'AI Assistant'}
        description={t('platformChatPageDescription') || 'Chat with your event data using RAG and analytics.'}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('platformChatTitle') || 'AI Assistant' },
        ]}
      />
      <PageContent>
        {!aiAvailable && (
          <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
            <div className="flex items-start gap-2">
              <AlertCircle className="h-5 w-5 shrink-0 mt-0.5" />
              <div className="space-y-1">
                <p className="text-sm font-medium">
                  {locale === 'ar'
                    ? 'مزود الذكاء الاصطناعي غير متصل. ستُستخدم إجابات التحليلات من قاعدة البيانات.'
                    : 'AI provider is offline. Analytics answers still use live database data.'}
                </p>
                {aiStatus?.hint ? (
                  <p className="text-xs opacity-90">{aiStatus.hint}</p>
                ) : null}
              </div>
            </div>
          </div>
        )}

        <div className="max-w-3xl mx-auto">
          <PlatformChatBot tenantId={tenantId} disabled={false} />
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
