import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ZoneLaneEditor } from '@/components/acs/ZoneLaneEditor'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import ConfirmModal from '@/components/modals/ConfirmModal'
import StatusBadge from '@/components/status/StatusBadge'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import type { AcsZone, EmergencyEgressMode, UnavailabilityMode } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type ZoneEditDraft = {
  name: string
  anti_passback_enabled: boolean
  unavailability_mode: UnavailabilityMode
  emergency_egress_mode: EmergencyEgressMode
  status: AcsZone['status']
}

type Props = {
  event: EventRow
  tenantId: string
  zones: AcsZone[]
}

function draftFromZone(zone: AcsZone): ZoneEditDraft {
  return {
    name: zone.name,
    anti_passback_enabled: zone.anti_passback_enabled,
    unavailability_mode: zone.unavailability_mode,
    emergency_egress_mode: zone.emergency_egress_mode,
    status: zone.status,
  }
}

export default function AcsZones({ event, tenantId, zones: initialZones }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [zones, setZones] = useState(initialZones)
  const [name, setName] = useState('')
  const [externalId, setExternalId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState<ZoneEditDraft | null>(null)
  const [deleteOpen, setDeleteOpen] = useState(false)
  const [deleting, setDeleting] = useState(false)

  useEffect(() => {
    setZones(initialZones)
  }, [initialZones])

  const selected = zones.find((zone) => zone.id === selectedId) ?? null

  function openPane(zoneId: string) {
    setSelectedId(zoneId)
    setEditing(false)
    setDraft(null)
    setDeleteOpen(false)
  }

  function closePane() {
    setSelectedId(null)
    setEditing(false)
    setDraft(null)
    setDeleteOpen(false)
  }

  function startEdit() {
    if (!selected) {
      return
    }

    setDraft(draftFromZone(selected))
    setEditing(true)
  }

  async function handleCreate(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      const created = await apiFetch<AcsZone>(`/api/v1/tenant/events/${event.id}/acs/zones`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { name, external_acs_zone_id: externalId },
      })

      setZones((prev) => [...prev, created])
      setName('')
      setExternalId('')
      toast(t('saved'), 'success')
      openPane(created.id)
    } catch (caught) {
      setError(caught instanceof ApiFetchError
        ? (caught.code ?? caught.message)
        : 'create_failed')
    } finally {
      setSubmitting(false)
    }
  }

  async function handleSave(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    if (!selected || !draft) {
      return
    }

    setSaving(true)

    try {
      const updated = await apiFetch<AcsZone>(
        `/api/v1/tenant/events/${event.id}/acs/zones/${selected.id}`,
        {
          method: 'PATCH',
          tenantId,
          idempotency: true,
          body: {
            name: draft.name.trim(),
            anti_passback_enabled: draft.anti_passback_enabled,
            unavailability_mode: draft.unavailability_mode,
            emergency_egress_mode: draft.emergency_egress_mode,
            status: draft.status,
          },
        },
      )

      setZones((prev) => prev.map((zone) => (zone.id === updated.id ? updated : zone)))
      setEditing(false)
      setDraft(null)
      toast(t('saved'), 'success')
    } catch (caught) {
      toast(
        caught instanceof ApiFetchError ? caught.message : t('requestFailed'),
        'error',
      )
    } finally {
      setSaving(false)
    }
  }

  async function handleDelete() {
    if (!selected) {
      return
    }

    setDeleting(true)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/acs/zones/${selected.id}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })

      setZones((prev) => prev.filter((zone) => zone.id !== selected.id))
      closePane()
      toast(t('deleted'), 'success')
    } catch (caught) {
      toast(
        caught instanceof ApiFetchError ? caught.message : t('requestFailed'),
        'error',
      )
    } finally {
      setDeleting(false)
      setDeleteOpen(false)
    }
  }

  return (
    <DashboardLayout title={t('acsPageZones')}>
      <PageHeader
        title={t('acsPageZones')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: t('acsPageZones') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/lanes`}>
            {t('acsPageLanes')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <ZoneLaneEditor
          zones={zones}
          lanes={[]}
          showLanes={false}
          selectedZoneId={selectedId}
          onZoneSelect={openPane}
        />

        <form className="ta-card mt-6 space-y-4" onSubmit={handleCreate}>
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsPageCreateZone')}</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">
              {t('acsPageCreateZoneDescription')}
            </p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <TextInput label={t('name')} name="name" value={name} onChange={(e) => setName(e.target.value)} required />
            <TextInput
              label={t('acsPageExternalZoneId')}
              name="external_acs_zone_id"
              value={externalId}
              onChange={(e) => setExternalId(e.target.value)}
              required
            />
          </div>
          {error && (
            <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" role="alert">
              {error}
            </p>
          )}
          <SubmitButtonWithLoader loading={submitting} label={t('acsPageCreateZone')} />
        </form>
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? selected.name : ''}
        subtitle={selected ? selected.external_acs_zone_id : null}
        onClose={closePane}
        onEdit={selected && !editing ? startEdit : null}
        onDelete={selected && !editing ? () => setDeleteOpen(true) : null}
        footer={selected && editing && draft ? (
          <SideDetailActions>
            <button
              type="submit"
              form="acs-zone-edit-form"
              className={sideDetailActionClassName('primary')}
              disabled={saving}
            >
              {saving ? t('saving') : t('save')}
            </button>
            <button
              type="button"
              className={sideDetailActionClassName()}
              onClick={() => {
                setEditing(false)
                setDraft(null)
              }}
            >
              {t('cancel')}
            </button>
          </SideDetailActions>
        ) : selected ? (
          <SideDetailActions>
            <button type="button" className={sideDetailActionClassName('primary')} onClick={startEdit}>
              {t('edit')}
            </button>
            <button
              type="button"
              className={sideDetailActionClassName('danger')}
              onClick={() => setDeleteOpen(true)}
            >
              {t('delete')}
            </button>
          </SideDetailActions>
        ) : null}
      >
        {selected && editing && draft ? (
          <form id="acs-zone-edit-form" className="space-y-4" onSubmit={(e) => void handleSave(e)}>
            <TextInput
              label={t('name')}
              name="edit_name"
              value={draft.name}
              required
              onChange={(e) => setDraft((current) => current ? { ...current, name: e.target.value } : current)}
            />
            <TextInput
              label={t('acsPageExternalZoneId')}
              name="edit_external_id"
              value={selected.external_acs_zone_id}
              disabled
              hint={t('acsPageExternalZoneIdReadonly')}
            />
            <CheckboxInput
              label={t('acsPageAntiPassback')}
              name="anti_passback_enabled"
              checked={draft.anti_passback_enabled}
              onChange={(e) => setDraft((current) => current
                ? { ...current, anti_passback_enabled: e.target.checked }
                : current)}
            />
            <SelectInput
              label={t('acsPageUnavailabilityMode')}
              name="unavailability_mode"
              value={draft.unavailability_mode}
              onChange={(e) => setDraft((current) => current
                ? { ...current, unavailability_mode: e.target.value as UnavailabilityMode }
                : current)}
              options={[
                { value: 'fail_closed', label: t('acsPageFailClosed') },
                { value: 'fail_open', label: t('acsPageFailOpen') },
              ]}
            />
            <SelectInput
              label={t('acsPageEmergencyEgressMode')}
              name="emergency_egress_mode"
              value={draft.emergency_egress_mode}
              onChange={(e) => setDraft((current) => current
                ? { ...current, emergency_egress_mode: e.target.value as EmergencyEgressMode }
                : current)}
              options={[
                { value: 'fail_open', label: t('acsPageFailOpen') },
                { value: 'fail_closed', label: t('acsPageFailClosed') },
              ]}
            />
            <SelectInput
              label={t('status')}
              name="status"
              value={draft.status}
              onChange={(e) => setDraft((current) => current
                ? { ...current, status: e.target.value as AcsZone['status'] }
                : current)}
              options={[
                { value: 'active', label: t('acsPageStatusActive') },
                { value: 'inactive', label: t('acsPageStatusInactive') },
              ]}
            />
          </form>
        ) : selected ? (
          <SideDetailInfoGrid
            title={t('acsPageZoneDetails')}
            items={[
              { label: t('name'), value: selected.name },
              { label: t('acsPageExternalZoneId'), value: selected.external_acs_zone_id },
              { label: t('status'), value: <StatusBadge status={selected.status} /> },
              {
                label: t('acsPageAntiPassback'),
                value: selected.anti_passback_enabled ? t('yes') : t('no'),
              },
              {
                label: t('acsPageUnavailabilityMode'),
                value: selected.unavailability_mode === 'fail_open'
                  ? t('acsPageFailOpen')
                  : t('acsPageFailClosed'),
              },
              {
                label: t('acsPageEmergencyEgressMode'),
                value: selected.emergency_egress_mode === 'fail_open'
                  ? t('acsPageFailOpen')
                  : t('acsPageFailClosed'),
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

      <ConfirmModal
        open={deleteOpen}
        title={t('acsPageDeleteZone')}
        message={t('acsPageDeleteZoneConfirm').replace(':name', selected?.name ?? '')}
        confirmLabel={t('delete')}
        cancelLabel={t('cancel')}
        loading={deleting}
        onConfirm={() => void handleDelete()}
        onCancel={() => setDeleteOpen(false)}
      />
    </DashboardLayout>
  )
}
