import { router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type Level =
  | 'not_required'
  | 'optional'
  | 'required_before_credential'
  | 'required_before_gate'
  | 'required_vip'
  | 'required_vvip'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type TicketTypeRow = {
  id: string
  code: string
  name: { en: string; ar: string }
}

type RequirementRow = {
  id: string
  event_id: string
  ticket_type_id: string | null
  level: Level
  face_fallback_enabled: boolean
}

type Props = {
  tenantId: string
  event: EventRow
  ticketTypes: TicketTypeRow[]
  requirements: RequirementRow[]
  canManage: boolean
}

const LEVELS: Array<{ value: Level; labelKey: string }> = [
  { value: 'not_required', labelKey: 'identityLevelNotRequired' },
  { value: 'optional', labelKey: 'identityLevelOptional' },
  { value: 'required_before_credential', labelKey: 'identityLevelRequiredBeforeCredential' },
  { value: 'required_before_gate', labelKey: 'identityLevelRequiredBeforeGate' },
  { value: 'required_vip', labelKey: 'identityLevelRequiredVip' },
  { value: 'required_vvip', labelKey: 'identityLevelRequiredVvip' },
]

type RequirementForm = {
  ticket_type_id: string | null
  level: Level
  face_fallback_enabled: boolean
}

function toMap(rows: RequirementRow[]): Record<string, RequirementRow> {
  const mapped: Record<string, RequirementRow> = {}
  rows.forEach((row) => {
    mapped[row.ticket_type_id ?? '__event_default__'] = row
  })
  return mapped
}

export default function IdentityRequirementsPage({
  tenantId,
  event,
  ticketTypes,
  requirements,
  canManage,
}: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [records, setRecords] = useState<Record<string, RequirementRow>>(() => toMap(requirements))
  const [savingKey, setSavingKey] = useState<string | null>(null)
  const [errorState, setErrorState] = useState<'none' | 'forbidden' | 'error'>('none')

  const apiHeaders = useMemo(
    () => ({
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Tenant-ID': tenantId,
    }),
    [tenantId],
  )

  function levelLabel(value: Level): string {
    const option = LEVELS.find((row) => row.value === value)
    return option ? t(option.labelKey) : value
  }

  function toForm(ticketTypeId: string | null): RequirementForm {
    const current = records[ticketTypeId ?? '__event_default__']
    return {
      ticket_type_id: ticketTypeId,
      level: current?.level ?? 'not_required',
      face_fallback_enabled: current?.face_fallback_enabled ?? false,
    }
  }

  function extractError(body: unknown, fallback: string): string {
    if (typeof body !== 'object' || body === null) return fallback
    const maybe = body as { detail?: string; message?: string; title?: string; code?: string }
    return maybe.detail ?? maybe.message ?? maybe.title ?? maybe.code ?? fallback
  }

  async function save(form: RequirementForm) {
    const scopeKey = form.ticket_type_id ?? '__event_default__'
    setSavingKey(scopeKey)
    setErrorState('none')

    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/identity/requirements`, {
        method: 'PUT',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify(form),
      })
      const body = await response.json()

      if (response.status === 403) {
        setErrorState('forbidden')
        toast(t('forbiddenDetail'), 'error')
        setSavingKey(null)
        return
      }

      if (!response.ok) {
        setErrorState('error')
        toast(extractError(body, t('identityRequirementsSaveFailed')), 'error')
        setSavingKey(null)
        return
      }

      const row = body.data as RequirementRow
      setRecords((current) => ({ ...current, [scopeKey]: row }))
      toast(t('identityRequirementsSaved'), 'success')
      setSavingKey(null)
      router.reload()
    } catch {
      setErrorState('error')
      toast(t('identityRequirementsSaveFailed'), 'error')
      setSavingKey(null)
    }
  }

  function renderForm(title: string, ticketTypeId: string | null) {
    const form = toForm(ticketTypeId)
    const key = ticketTypeId ?? '__event_default__'

    return (
      <form
        className="state-panel grid gap-4 md:grid-cols-2"
        onSubmit={(event) => {
          event.preventDefault()
          void save(form)
        }}
      >
        <h2 className="text-lg font-semibold md:col-span-2">{title}</h2>
        <SelectInput
          label={t('identityRequirementLevel')}
          name={`level_${key}`}
          value={form.level}
          onChange={(e) => {
            const value = e.target.value as Level
            setRecords((current) => ({
              ...current,
              [key]: {
                id: current[key]?.id ?? `draft-${key}`,
                event_id: event.id,
                ticket_type_id: ticketTypeId,
                level: value,
                face_fallback_enabled: current[key]?.face_fallback_enabled ?? false,
              },
            }))
          }}
          options={LEVELS.map((item) => ({ value: item.value, label: levelLabel(item.value) }))}
        />
        <div className="flex items-end">
          <CheckboxInput
            label={t('identityFaceFallbackEnabled')}
            name={`fallback_${key}`}
            checked={form.face_fallback_enabled}
            onChange={(e) =>
              setRecords((current) => ({
                ...current,
                [key]: {
                  id: current[key]?.id ?? `draft-${key}`,
                  event_id: event.id,
                  ticket_type_id: ticketTypeId,
                  level: current[key]?.level ?? 'not_required',
                  face_fallback_enabled: e.target.checked,
                },
              }))
            }
          />
        </div>
        <div className="md:col-span-2">
          <PermissionGate permission="identity.configure">
            <SubmitButtonWithLoader loading={savingKey === key} label={t('save')} />
          </PermissionGate>
        </div>
      </form>
    )
  }

  return (
    <DashboardLayout title={t('identityRequirements')}>
      <PageHeader
        title={t('identityRequirements')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('identityRequirements') },
        ]}
      />
      <PageContent>
        {!canManage && (
          <section className="state-panel">
            <h2 className="text-lg font-semibold">{t('forbiddenTitle')}</h2>
            <p className="mt-2 text-sm text-slate-600">{t('forbiddenDetail')}</p>
          </section>
        )}

        {errorState === 'forbidden' && canManage && (
          <section className="state-panel">
            <h2 className="text-lg font-semibold">{t('forbiddenTitle')}</h2>
            <p className="mt-2 text-sm text-slate-600">{t('forbiddenDetail')}</p>
          </section>
        )}

        {errorState === 'error' && (
          <section className="state-panel">
            <h2 className="text-lg font-semibold">{t('errorState')}</h2>
            <p className="mt-2 text-sm text-slate-600">{t('identityRequirementsSaveFailed')}</p>
          </section>
        )}

        {canManage && (
          <>
            {renderForm(t('identityEventDefaultRule'), null)}
            {ticketTypes.length === 0 ? (
              <section className="state-panel">
                <h2 className="text-lg font-semibold">{t('emptyState')}</h2>
                <p className="mt-2 text-sm text-slate-600">{t('identityNoTicketTypes')}</p>
              </section>
            ) : (
              <section className="space-y-4">
                <h2 className="text-lg font-semibold">{t('identityTierOverrides')}</h2>
                {ticketTypes.map((ticketType) => (
                  <div key={ticketType.id}>
                    {renderForm(
                      `${ticketType.name[locale]} (${ticketType.code})`,
                      ticketType.id,
                    )}
                  </div>
                ))}
              </section>
            )}
          </>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
