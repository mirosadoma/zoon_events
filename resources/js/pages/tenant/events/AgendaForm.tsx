import { FormEvent, useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import TimeInput from '@/components/forms/TimeInput'
import SelectInput from '@/components/forms/SelectInput'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { eachWallClockDate, toTimeLocalValue } from '@/lib/dateTimeLocal'
import type { AgendaItemPayload } from '@/pages/tenant/events/Agenda'

type LocalizedName = { en: string; ar: string }

type VenueOption = {
  id: string
  name: LocalizedName
  start_at: string | null
  end_at: string | null
  zones?: Array<{
    id: string
    venue_id: string
    name: LocalizedName
    type: string
    capacity: number | null
  }>
}

type FormState = {
  event_venue_id: string
  zone_id: string
  agenda_date: string
  title_en: string
  title_ar: string
  description_en: string
  description_ar: string
  speaker: string
  start_at: string
  end_at: string
}

type Props = {
  event: {
    id: string
    name: LocalizedName
    timezone?: string | null
  }
  tenantId: string
  venues: VenueOption[]
  item: AgendaItemPayload | null
  items: AgendaItemPayload[]
  mode: 'create' | 'edit'
}

function toLocalTime(value: string | null | undefined): string {
  return toTimeLocalValue(value)
}

function generateDates(startDate: string | null, endDate: string | null): string[] {
  return eachWallClockDate(startDate, endDate)
}

function emptyForm(venues: VenueOption[]): FormState {
  const venue = venues[0]
  const dates = generateDates(venue?.start_at ?? null, venue?.end_at ?? null)

  return {
    event_venue_id: venue?.id ?? '',
    zone_id: '',
    agenda_date: dates[0] ?? '',
    title_en: '',
    title_ar: '',
    description_en: '',
    description_ar: '',
    speaker: '',
    start_at: '',
    end_at: '',
  }
}

function formFromItem(item: AgendaItemPayload, venues: VenueOption[]): FormState {
  return {
    event_venue_id: item.event_venue_id || venues[0]?.id || '',
    zone_id: item.zone_id || '',
    agenda_date: item.agenda_date || item.start_at?.split('T')[0] || '',
    title_en: item.title_en,
    title_ar: item.title_ar,
    description_en: item.description_en || '',
    description_ar: item.description_ar || '',
    speaker: item.speaker || '',
    start_at: toLocalTime(item.start_at),
    end_at: toLocalTime(item.end_at),
  }
}

function toSyncPayload(item: AgendaItemPayload) {
  const date = item.agenda_date || item.start_at?.split('T')[0] || ''
  const startTime = toLocalTime(item.start_at)
  const endTime = toLocalTime(item.end_at)

  return {
    id: Number(item.id),
    event_venue_id: item.event_venue_id ? Number(item.event_venue_id) : null,
    zone_id: item.zone_id ? Number(item.zone_id) : null,
    agenda_date: date || null,
    title_en: item.title_en,
    title_ar: item.title_ar,
    description_en: item.description_en || null,
    description_ar: item.description_ar || null,
    speaker: item.speaker || null,
    start_at: date && startTime ? `${date}T${startTime}` : item.start_at,
    end_at: date && endTime ? `${date}T${endTime}` : item.end_at,
  }
}

export default function EventAgendaFormPage({
  event,
  tenantId,
  venues,
  item,
  items,
  mode,
}: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [form, setForm] = useState<FormState>(() => (
    mode === 'edit' && item ? formFromItem(item, venues) : emptyForm(venues)
  ))
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [saving, setSaving] = useState(false)

  const listHref = `/tenant/events/${event.id}/agenda`
  const pageTitle = mode === 'edit' ? t('eventAgendaEditTitle') : t('eventAgendaAddTitle')

  const venueOptions = useMemo(
    () => venues.map((venue) => ({
      value: venue.id,
      label: locale === 'ar' ? venue.name.ar : venue.name.en,
    })),
    [venues, locale],
  )

  const dateOptions = useMemo(() => {
    const venue = venues.find((row) => row.id === form.event_venue_id)
    return generateDates(venue?.start_at ?? null, venue?.end_at ?? null).map((date) => ({
      value: date,
      label: date,
    }))
  }, [venues, form.event_venue_id])

  const zoneOptions = useMemo(() => {
    const venue = venues.find((row) => row.id === form.event_venue_id)
    return [
      { value: '', label: t('eventAgendaZoneNone') },
      ...(venue?.zones ?? []).map((zone) => ({
        value: zone.id,
        label: locale === 'ar' ? zone.name.ar : zone.name.en,
      })),
    ]
  }, [venues, form.event_venue_id, locale, t])

  function updateForm(patch: Partial<FormState>) {
    setForm((current) => {
      const next = { ...current, ...patch }

      if (patch.event_venue_id && patch.event_venue_id !== current.event_venue_id) {
        const venue = venues.find((row) => row.id === patch.event_venue_id)
        const dates = generateDates(venue?.start_at ?? null, venue?.end_at ?? null)
        next.zone_id = ''
        next.agenda_date = dates.includes(current.agenda_date) ? current.agenda_date : (dates[0] ?? '')
      }

      return next
    })
  }

  async function save(submitEvent: FormEvent) {
    submitEvent.preventDefault()
    setSaving(true)
    setErrors({})

    if (!form.event_venue_id || !form.agenda_date || !form.title_en.trim() || !form.title_ar.trim() || !form.start_at) {
      const message = t('eventAgendaIncomplete')
      setErrors({ form: message })
      toast(message, 'error')
      setSaving(false)
      return
    }

    const draftPayload = {
      id: item?.id ? Number(item.id) : undefined,
      event_venue_id: Number(form.event_venue_id),
      zone_id: form.zone_id ? Number(form.zone_id) : null,
      agenda_date: form.agenda_date,
      title_en: form.title_en.trim(),
      title_ar: form.title_ar.trim(),
      description_en: form.description_en.trim() || null,
      description_ar: form.description_ar.trim() || null,
      speaker: form.speaker.trim() || null,
      start_at: `${form.agenda_date}T${form.start_at}`,
      end_at: form.end_at ? `${form.agenda_date}T${form.end_at}` : null,
    }

    const nextItems = mode === 'create'
      ? [...items.map((row) => toSyncPayload(row)), draftPayload]
      : items.map((row) => (row.id === item?.id ? draftPayload : toSyncPayload(row)))

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/agenda`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { items: nextItems },
      })

      toast(t('saved'), 'success')
      router.visit(localizedPath(listHref))
    } catch (caught) {
      if (caught instanceof ApiFetchError) {
        setErrors(caught.errors)
        toast(caught.message, 'error')
      } else {
        toast(t('requestFailed'), 'error')
      }
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardLayout title={pageTitle}>
      <PageHeader
        title={pageTitle}
        description={t('eventAgendaDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventAgendaTitle'), href: listHref },
          { label: pageTitle },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={listHref}>
            {t('cancel')}
          </LocalizedLink>
        )}
      />

      <PageContent>
        <form className="ta-card space-y-4 p-4" onSubmit={(eventForm) => void save(eventForm)}>
          {errors.form || errors.items ? (
            <p role="alert" className="rounded-lg border border-[var(--danger)]/30 bg-[color-mix(in_srgb,var(--danger)_10%,transparent)] px-3 py-2 text-sm text-[var(--danger)]">
              {errors.form || errors.items}
            </p>
          ) : null}

          <div className="grid gap-4 sm:grid-cols-2">
            <SelectInput
              label={t('eventAgendaPreviewVenue')}
              name="event_venue_id"
              value={form.event_venue_id}
              onChange={(e) => updateForm({ event_venue_id: e.target.value })}
              options={venueOptions}
              required
            />
            <SelectInput
              label={t('eventAgendaZone')}
              name="zone_id"
              value={form.zone_id}
              onChange={(e) => updateForm({ zone_id: e.target.value })}
              options={zoneOptions}
            />
            <SelectInput
              label={t('eventAgendaDate')}
              name="agenda_date"
              value={form.agenda_date}
              onChange={(e) => updateForm({ agenda_date: e.target.value })}
              options={dateOptions.length > 0 ? dateOptions : [{ value: form.agenda_date || '', label: form.agenda_date || t('notAvailable') }]}
              required
            />
            <TextInput
              label={t('eventAgendaSpeaker')}
              name="speaker"
              value={form.speaker}
              onChange={(e) => updateForm({ speaker: e.target.value })}
            />
            <TextInput
              label={t('eventAgendaTitleEn')}
              name="title_en"
              value={form.title_en}
              onChange={(e) => updateForm({ title_en: e.target.value })}
              required
            />
            <TextInput
              label={t('eventAgendaTitleAr')}
              name="title_ar"
              value={form.title_ar}
              onChange={(e) => updateForm({ title_ar: e.target.value })}
              required
            />
            <TimeInput
              label={t('eventAgendaStartsAt')}
              name="start_at"
              value={form.start_at}
              onChange={(e) => updateForm({ start_at: e.target.value })}
              required
            />
            <TimeInput
              label={t('eventAgendaEndsAt')}
              name="end_at"
              value={form.end_at}
              onChange={(e) => updateForm({ end_at: e.target.value })}
            />
          </div>

          <TextareaInput
            label={locale === 'ar' ? 'الوصف (إنجليزي)' : 'Description (English)'}
            name="description_en"
            value={form.description_en}
            onChange={(e) => updateForm({ description_en: e.target.value })}
            rows={3}
          />
          <TextareaInput
            label={locale === 'ar' ? 'الوصف (عربي)' : 'Description (Arabic)'}
            name="description_ar"
            value={form.description_ar}
            onChange={(e) => updateForm({ description_ar: e.target.value })}
            rows={3}
          />

          <div className="flex flex-wrap gap-2">
            <SubmitButtonWithLoader label={t('save')} loading={saving} />
            <LocalizedLink className="button-secondary" href={listHref}>
              {t('cancel')}
            </LocalizedLink>
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
