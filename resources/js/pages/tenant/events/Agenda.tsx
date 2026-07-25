import { FormEvent, useState, useMemo } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import TimeInput from '@/components/forms/TimeInput'
import SelectInput from '@/components/forms/SelectInput'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useFormValidation } from '@/hooks/useFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { formFieldProps } from '@/lib/formatValidationErrors'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  start_at?: string | null
  timezone?: string | null
}

type VenueOption = {
  id: string
  name: { en: string; ar: string }
  start_at: string | null
  end_at: string | null
}

type AgendaItemRow = {
  key: string
  id?: string
  event_venue_id: string
  agenda_date: string
  title_en: string
  title_ar: string
  description_en: string
  description_ar: string
  start_at: string
  end_at: string
}

type VenueGroup = {
  key: string
  venue_id: string
  dates: DateGroup[]
}

type DateGroup = {
  key: string
  date: string
  items: AgendaItemRow[]
}

type Props = {
  event: EventRow
  tenantId: string
  venues: VenueOption[]
  items: Array<{
    id: string
    event_venue_id: string | null
    agenda_date: string | null
    title_en: string
    title_ar: string
    description_en: string
    description_ar: string
    start_at: string | null
    end_at: string | null
  }>
}

function generateDates(startDate: string | null, endDate: string | null): string[] {
  if (!startDate || !endDate) return []
  const dates: string[] = []
  const start = new Date(startDate)
  const end = new Date(endDate)
  const current = new Date(start)

  while (current <= end) {
    dates.push(current.toISOString().split('T')[0])
    current.setDate(current.getDate() + 1)
  }

  return dates
}

function toLocalTime(value: string | null | undefined): string {
  if (!value) return ''
  return value.split('T')[1]?.substring(0, 5) || ''
}

function emptyAgendaItem(venueId: string, date: string): AgendaItemRow {
  return {
    key: crypto.randomUUID(),
    event_venue_id: venueId,
    agenda_date: date,
    title_en: '',
    title_ar: '',
    description_en: '',
    description_ar: '',
    start_at: '',
    end_at: '',
  }
}

function mapInitialStructure(
  items: Props['items'],
  venues: VenueOption[],
): VenueGroup[] {
  if (venues.length === 0) return []

  const groups = new Map<string, Map<string, AgendaItemRow[]>>()

  for (const item of items) {
    const venueId = item.event_venue_id || venues[0]?.id
    const date = item.agenda_date || venues.find((v) => v.id === venueId)?.start_at?.split('T')[0] || ''

    if (!venueId || !date) continue

    if (!groups.has(venueId)) {
      groups.set(venueId, new Map())
    }

    const venueGroup = groups.get(venueId)!
    if (!venueGroup.has(date)) {
      venueGroup.set(date, [])
    }

    venueGroup.get(date)!.push({
      key: item.id,
      id: item.id,
      event_venue_id: venueId,
      agenda_date: date,
      title_en: item.title_en,
      title_ar: item.title_ar,
      description_en: item.description_en || '',
      description_ar: item.description_ar || '',
      start_at: toLocalTime(item.start_at),
      end_at: toLocalTime(item.end_at),
    })
  }

  const result: VenueGroup[] = []
  for (const venue of venues) {
    const venueGroup = groups.get(venue.id)
    const dates: DateGroup[] = []

    if (venueGroup) {
      for (const [date, items] of Array.from(venueGroup.entries())) {
        dates.push({
          key: `${venue.id}-${date}`,
          date,
          items,
        })
      }
    }

    if (dates.length > 0) {
      result.push({
        key: venue.id,
        venue_id: venue.id,
        dates,
      })
    }
  }

  if (result.length === 0 && venues.length > 0) {
    const firstVenue = venues[0]
    const firstDate = firstVenue.start_at?.split('T')[0] || new Date().toISOString().split('T')[0]
    result.push({
      key: firstVenue.id,
      venue_id: firstVenue.id,
      dates: [
        {
          key: `${firstVenue.id}-${firstDate}`,
          date: firstDate,
          items: [emptyAgendaItem(firstVenue.id, firstDate)],
        },
      ],
    })
  }

  return result
}

export default function EventAgenda({ event, tenantId, venues, items: initialItems }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const validation = useFormValidation({ titleKey: 'couldNotSaveAgenda' })
  const [venueGroups, setVenueGroups] = useState<VenueGroup[]>(() =>
    mapInitialStructure(initialItems, venues),
  )
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const venueOptions = useMemo(
    () =>
      venues.map((venue) => ({
        value: venue.id,
        label: locale === 'ar' ? venue.name.ar : venue.name.en,
      })),
    [venues, locale],
  )

  const usedVenueIds = useMemo(
    () => new Set(venueGroups.map((group) => group.venue_id)),
    [venueGroups],
  )

  const canAddVenue = venues.some((venue) => !usedVenueIds.has(venue.id))

  function venueOptionsForGroup(groupIndex: number) {
    const currentVenueId = venueGroups[groupIndex]?.venue_id

    return venueOptions.filter(
      (option) => option.value === currentVenueId || !usedVenueIds.has(option.value),
    )
  }

  function getAvailableDates(venueId: string): string[] {
    const venue = venues.find((v) => v.id === venueId)
    return generateDates(venue?.start_at || null, venue?.end_at || null)
  }

  function addVenueGroup() {
    const nextVenue = venues.find((venue) => !usedVenueIds.has(venue.id))

    if (!nextVenue) {
      toast(
        locale === 'ar'
          ? 'تمت إضافة كل المواقع المتاحة.'
          : 'All available venues have already been added.',
        'error',
      )
      return
    }

    const firstDate = nextVenue.start_at?.split('T')[0] || new Date().toISOString().split('T')[0]

    setVenueGroups((current) => [
      ...current,
      {
        key: crypto.randomUUID(),
        venue_id: nextVenue.id,
        dates: [
          {
            key: crypto.randomUUID(),
            date: firstDate,
            items: [emptyAgendaItem(nextVenue.id, firstDate)],
          },
        ],
      },
    ])
  }

  function removeVenueGroup(groupIndex: number) {
    setVenueGroups((current) => current.filter((_, idx) => idx !== groupIndex))
  }

  function updateVenueGroupVenue(groupIndex: number, newVenueId: string) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        const firstDate = venues.find((v) => v.id === newVenueId)?.start_at?.split('T')[0] || group.dates[0]?.date || ''
        return {
          ...group,
          venue_id: newVenueId,
          dates: [
            {
              key: crypto.randomUUID(),
              date: firstDate,
              items: [emptyAgendaItem(newVenueId, firstDate)],
            },
          ],
        }
      }),
    )
  }

  function addDateToVenue(groupIndex: number) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        const availableDates = getAvailableDates(group.venue_id)
        const usedDates = new Set(group.dates.map((d) => d.date))
        const nextDate = availableDates.find((d) => !usedDates.has(d)) || availableDates[0] || ''

        return {
          ...group,
          dates: [
            ...group.dates,
            {
              key: crypto.randomUUID(),
              date: nextDate,
              items: [emptyAgendaItem(group.venue_id, nextDate)],
            },
          ],
        }
      }),
    )
  }

  function removeDateFromVenue(groupIndex: number, dateIndex: number) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        const newDates = group.dates.filter((_, dIdx) => dIdx !== dateIndex)
        return { ...group, dates: newDates.length > 0 ? newDates : group.dates }
      }),
    )
  }

  function updateDateInVenue(groupIndex: number, dateIndex: number, newDate: string) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        return {
          ...group,
          dates: group.dates.map((dateGroup, dIdx) =>
            dIdx === dateIndex ? { ...dateGroup, date: newDate } : dateGroup,
          ),
        }
      }),
    )
  }

  function addItemToDate(groupIndex: number, dateIndex: number) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        return {
          ...group,
          dates: group.dates.map((dateGroup, dIdx) =>
            dIdx === dateIndex
              ? {
                  ...dateGroup,
                  items: [...dateGroup.items, emptyAgendaItem(group.venue_id, dateGroup.date)],
                }
              : dateGroup,
          ),
        }
      }),
    )
  }

  function removeItemFromDate(groupIndex: number, dateIndex: number, itemIndex: number) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        return {
          ...group,
          dates: group.dates.map((dateGroup, dIdx) =>
            dIdx === dateIndex
              ? {
                  ...dateGroup,
                  items: dateGroup.items.filter((_, iIdx) => iIdx !== itemIndex),
                }
              : dateGroup,
          ),
        }
      }),
    )
  }

  function updateItem(groupIndex: number, dateIndex: number, itemIndex: number, patch: Partial<AgendaItemRow>) {
    setVenueGroups((current) =>
      current.map((group, idx) => {
        if (idx !== groupIndex) return group

        return {
          ...group,
          dates: group.dates.map((dateGroup, dIdx) =>
            dIdx === dateIndex
              ? {
                  ...dateGroup,
                  items: dateGroup.items.map((item, iIdx) =>
                    iIdx === itemIndex ? { ...item, ...patch } : item,
                  ),
                }
              : dateGroup,
          ),
        }
      }),
    )
  }

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    setError(null)
    validation.clearValidation()
    setSubmitting(true)

    const flatItems: Array<{
      id?: string
      event_venue_id: number
      agenda_date: string
      title_en: string
      title_ar: string
      description_en: string
      description_ar: string
      start_at: string
      end_at: string
    }> = []

    for (const group of venueGroups) {
      for (const dateGroup of group.dates) {
        for (const item of dateGroup.items) {
          if (!item.title_en.trim() || !item.title_ar.trim()) continue

          flatItems.push({
            id: item.id,
            event_venue_id: Number(group.venue_id),
            agenda_date: dateGroup.date,
            title_en: item.title_en,
            title_ar: item.title_ar,
            description_en: item.description_en,
            description_ar: item.description_ar,
            start_at: `${dateGroup.date}T${item.start_at}`,
            end_at: `${dateGroup.date}T${item.end_at}`,
          })
        }
      }
    }

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/agenda`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { items: flatItems },
      })
      toast(t('eventAgendaSaved'), 'success')
      router.reload({ only: ['items'] })
    } catch (caught) {
      setError(caught instanceof ApiFetchError ? caught.message : t('eventAgendaSaveFailed'))
    } finally {
      setSubmitting(false)
    }
  }

  if (venues.length === 0) {
    return (
      <DashboardLayout title={event.name[locale]}>
        <PageHeader
          title={t('eventAgendaTitle')}
          description={t('eventAgendaDescription')}
          breadcrumbs={[
            { label: t('overview'), href: '/dashboard' },
            { label: t('overviewEvents'), href: '/tenant/events' },
            { label: event.name[locale], href: `/tenant/events/${event.id}` },
            { label: t('eventAgendaTitle') },
          ]}
          actions={(
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>
              {t('eventAgendaBack')}
            </LocalizedLink>
          )}
        />
        <PageContent>
          <div className="state-panel p-6 text-center">
            <p className="text-[var(--muted)]">
              {locale === 'ar'
                ? 'أضف موقعاً واحداً على الأقل للفعالية قبل إنشاء الأجندة.'
                : 'Add at least one venue to the event before creating the agenda.'}
            </p>
            <LocalizedLink className="button-primary mt-4 inline-block" href={`/tenant/events/${event.id}/venues`}>
              {locale === 'ar' ? 'إدارة مواقع الفعالية' : 'Manage event venues'}
            </LocalizedLink>
          </div>
        </PageContent>
      </DashboardLayout>
    )
  }

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={t('eventAgendaTitle')}
        description={t('eventAgendaDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('overviewEvents'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('eventAgendaTitle') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}`}>
            {t('eventAgendaBack')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <form className="ta-card relative space-y-6" onSubmit={(submitEvent) => void handleSubmit(submitEvent)}>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <p className="max-w-3xl text-sm text-[var(--muted)]">
              {locale === 'ar'
                ? 'قم بإنشاء الأجندة حسب الموقع والتاريخ. كل موقع يمكن أن يحتوي على عدة تواريخ وكل تاريخ يمكن أن يحتوي على عدة عناصر.'
                : 'Build your agenda by venue and date. Each venue can have multiple dates, and each date can have multiple items.'}
            </p>
            {canAddVenue ? (
              <button type="button" className="button-primary shrink-0" onClick={addVenueGroup}>
                {locale === 'ar' ? 'إضافة موقع' : 'Add venue'}
              </button>
            ) : null}
          </div>

          <div className="grid items-start gap-4 lg:grid-cols-2">
            {venueGroups.map((group, groupIndex) => {
              const availableDates = getAvailableDates(group.venue_id)

              return (
                <section
                  key={group.key}
                  className="space-y-4 rounded-xl border border-[var(--border)] bg-[color-mix(in_srgb,var(--brand-soft)_45%,var(--surface-elevated))] p-4"
                >
                  <div className="flex flex-col gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                      <h2 className="font-semibold text-[var(--ink)]">
                        {locale === 'ar' ? `موقع ${groupIndex + 1}` : `Venue ${groupIndex + 1}`}
                      </h2>
                      <div className="flex flex-wrap gap-2">
                        <button type="button" className="button-secondary" onClick={() => addDateToVenue(groupIndex)}>
                          {locale === 'ar' ? 'إضافة تاريخ' : 'Add date'}
                        </button>
                        <button
                          type="button"
                          className="button-secondary text-[var(--danger)]"
                          onClick={() => removeVenueGroup(groupIndex)}
                        >
                          {locale === 'ar' ? 'حذف الموقع' : 'Remove venue'}
                        </button>
                      </div>
                    </div>
                    <SelectInput
                      name={`venue_${groupIndex}`}
                      value={group.venue_id}
                      onChange={(e) => updateVenueGroupVenue(groupIndex, e.target.value)}
                      options={venueOptionsForGroup(groupIndex)}
                    />
                  </div>

                  {group.dates.map((dateGroup, dateIndex) => (
                    <article
                      key={dateGroup.key}
                      className="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] p-4"
                    >
                      <div className="flex flex-col gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <h3 className="font-medium text-[var(--ink)]">
                            {locale === 'ar' ? `تاريخ ${dateIndex + 1}` : `Date ${dateIndex + 1}`}
                          </h3>
                          <div className="flex flex-wrap gap-2">
                            <button
                              type="button"
                              className="button-secondary text-sm"
                              onClick={() => addItemToDate(groupIndex, dateIndex)}
                            >
                              {locale === 'ar' ? 'إضافة عنصر' : 'Add item'}
                            </button>
                            <button
                              type="button"
                              className="button-secondary text-sm text-[var(--danger)]"
                              onClick={() => removeDateFromVenue(groupIndex, dateIndex)}
                            >
                              {locale === 'ar' ? 'حذف التاريخ' : 'Remove date'}
                            </button>
                          </div>
                        </div>
                        <SelectInput
                          name={`date_${groupIndex}_${dateIndex}`}
                          value={dateGroup.date}
                          onChange={(e) => updateDateInVenue(groupIndex, dateIndex, e.target.value)}
                          options={availableDates.map((d) => ({ value: d, label: d }))}
                        />
                      </div>

                      <div className="grid items-start gap-3 sm:grid-cols-2">
                        {dateGroup.items.map((item, itemIndex) => (
                          <div
                            key={item.key}
                            className="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-3"
                          >
                            <div className="flex items-center justify-between gap-2">
                              <h4 className="text-sm font-medium text-[var(--ink)]">
                                {locale === 'ar' ? `عنصر ${itemIndex + 1}` : `Item ${itemIndex + 1}`}
                              </h4>
                              <button
                                type="button"
                                className="text-sm text-[var(--danger)] hover:underline"
                                onClick={() => removeItemFromDate(groupIndex, dateIndex, itemIndex)}
                              >
                                {locale === 'ar' ? 'حذف' : 'Remove'}
                              </button>
                            </div>

                            <div className="grid gap-3">
                              <TextInput
                                label={t('eventAgendaTitleEn')}
                                name={`title_en_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.title_en}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { title_en: e.target.value })}
                                required
                              />
                              <TextInput
                                label={t('eventAgendaTitleAr')}
                                name={`title_ar_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.title_ar}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { title_ar: e.target.value })}
                                required
                              />
                              <TextareaInput
                                label={locale === 'ar' ? 'الوصف (إنجليزي)' : 'Description (English)'}
                                name={`description_en_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.description_en}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { description_en: e.target.value })}
                                rows={2}
                              />
                              <TextareaInput
                                label={locale === 'ar' ? 'الوصف (عربي)' : 'Description (Arabic)'}
                                name={`description_ar_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.description_ar}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { description_ar: e.target.value })}
                                rows={2}
                              />
                              <TimeInput
                                label={t('eventAgendaStartsAt')}
                                name={`start_at_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.start_at}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { start_at: e.target.value })}
                                required
                              />
                              <TimeInput
                                label={t('eventAgendaEndsAt')}
                                name={`end_at_${groupIndex}_${dateIndex}_${itemIndex}`}
                                value={item.end_at}
                                onChange={(e) => updateItem(groupIndex, dateIndex, itemIndex, { end_at: e.target.value })}
                              />
                            </div>
                          </div>
                        ))}
                      </div>
                    </article>
                  ))}
                </section>
              )
            })}
          </div>

          {error ? <p role="alert" className="text-sm text-[var(--danger)]">{error}</p> : null}

          <SubmitButtonWithLoader
            loading={submitting}
            label={t('eventAgendaSave')}
          />
        </form>

        <ValidationHintPopover {...validation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
