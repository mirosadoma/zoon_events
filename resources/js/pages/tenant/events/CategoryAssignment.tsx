import { FormEvent, useEffect, useMemo, useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { localizedPath } from '@/lib/localePath'
import { clsx } from 'clsx'

type VenueOption = {
  id: string
  name: { en: string; ar: string }
}

type DayRow = {
  date: string
  capacity: string
}

type VenueBlock = {
  key: string
  event_venue_id: string
  days: DayRow[]
}

type CategoryRow = {
  id: string
  name: string
  name_ar: string | null
  slug: string
  color: string | null
  enabled: boolean
  is_paid: boolean
  price: string
  currency: string
  venues: VenueBlock[]
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
    timezone: string
  }
  tenantId: string
  categories: Array<{
    id: string
    name: string
    name_ar: string | null
    slug: string
    color: string | null
    enabled: boolean
    is_paid: boolean
    price_minor?: number
    currency?: string
    venues: Array<{
      event_venue_id: string
      days: Array<{ date: string; capacity: string }>
    }>
  }>
  venues: VenueOption[]
  eventDates: string[]
  locked: boolean
  canManage: boolean
}

function formatDateLabel(date: string, locale: 'en' | 'ar'): string {
  const parsed = new Date(`${date}T00:00:00`)
  return new Intl.DateTimeFormat(locale === 'ar' ? 'ar' : 'en-GB', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(parsed)
}

function toRows(
  categories: Props['categories'],
  eventDates: string[],
): CategoryRow[] {
  return categories.map((category) => ({
    id: category.id,
    name: category.name,
    name_ar: category.name_ar,
    slug: category.slug,
    color: category.color,
    enabled: category.enabled,
    is_paid: category.is_paid,
    price: category.is_paid && (category.price_minor ?? 0) > 0
      ? String(((category.price_minor ?? 0) / 100).toFixed(2)).replace(/\.00$/, '')
      : '',
    currency: category.currency || 'SAR',
    venues: category.venues.map((venue, index) => ({
      key: `${category.id}-${venue.event_venue_id}-${index}`,
      event_venue_id: venue.event_venue_id,
      days: eventDates.map((date) => {
        const existing = venue.days.find((day) => day.date === date)

        return {
          date,
          capacity: existing?.capacity ?? '',
        }
      }),
    })),
  }))
}

function emptyVenueBlock(eventDates: string[]): VenueBlock {
  return {
    key: `venue-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`,
    event_venue_id: '',
    days: eventDates.map((date) => ({ date, capacity: '' })),
  }
}

export default function CategoryAssignment({
  event,
  tenantId,
  categories: initialCategories,
  venues,
  eventDates,
  locked,
  canManage,
}: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const eventName = locale === 'ar' ? event.name.ar || event.name.en : event.name.en
  const [rows, setRows] = useState<CategoryRow[]>(() => toRows(initialCategories, eventDates))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const readOnly = locked || !canManage

  useEffect(() => {
    setRows(toRows(initialCategories, eventDates))
  }, [initialCategories, eventDates])

  const venueOptions = useMemo(
    () => venues.map((venue) => ({
      value: venue.id,
      label: locale === 'ar' ? (venue.name.ar || venue.name.en) : venue.name.en,
    })),
    [locale, venues],
  )

  function toggleCategory(categoryId: string) {
    if (readOnly) {
      return
    }

    setRows((current) => current.map((row) => {
      if (row.id !== categoryId) {
        return row
      }

      if (row.enabled) {
        return { ...row, enabled: false, is_paid: false, venues: [] }
      }

      return {
        ...row,
        enabled: true,
        venues: row.venues.length > 0 ? row.venues : [emptyVenueBlock(eventDates)],
      }
    }))
  }

  function updateCategory(categoryId: string, patch: Partial<CategoryRow>) {
    setRows((current) => current.map((row) => (
      row.id === categoryId ? { ...row, ...patch } : row
    )))
  }

  function updateVenue(categoryId: string, venueKey: string, patch: Partial<VenueBlock>) {
    setRows((current) => current.map((row) => {
      if (row.id !== categoryId) {
        return row
      }

      return {
        ...row,
        venues: row.venues.map((venue) => (
          venue.key === venueKey ? { ...venue, ...patch } : venue
        )),
      }
    }))
  }

  function updateDayCapacity(categoryId: string, venueKey: string, date: string, capacity: string) {
    setRows((current) => current.map((row) => {
      if (row.id !== categoryId) {
        return row
      }

      return {
        ...row,
        venues: row.venues.map((venue) => {
          if (venue.key !== venueKey) {
            return venue
          }

          return {
            ...venue,
            days: venue.days.map((day) => (
              day.date === date ? { ...day, capacity } : day
            )),
          }
        }),
      }
    }))
  }

  function addVenue(categoryId: string) {
    setRows((current) => current.map((row) => (
      row.id === categoryId
        ? { ...row, venues: [...row.venues, emptyVenueBlock(eventDates)] }
        : row
    )))
  }

  function removeVenue(categoryId: string, venueKey: string) {
    setRows((current) => current.map((row) => {
      if (row.id !== categoryId) {
        return row
      }

      const next = row.venues.filter((venue) => venue.key !== venueKey)

      return {
        ...row,
        venues: next.length > 0 ? next : [emptyVenueBlock(eventDates)],
      }
    }))
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    if (readOnly) {
      return
    }

    setSubmitting(true)
    setError(null)

    const enabled = rows.filter((row) => row.enabled)
    for (const row of enabled) {
      if (row.is_paid) {
        const major = Number(row.price.trim())
        if (!Number.isFinite(major) || major <= 0) {
          setError(t('categoryAssignmentPriceRequired'))
          setSubmitting(false)
          return
        }
      }

      if (row.venues.length === 0) {
        setError(t('categoryAssignmentVenueRequired'))
        setSubmitting(false)
        return
      }

      for (const venue of row.venues) {
        if (!venue.event_venue_id) {
          setError(t('categoryAssignmentVenueRequired'))
          setSubmitting(false)
          return
        }

        for (const day of venue.days) {
          const capacity = day.capacity.trim()
          if (capacity !== '' && Number(capacity) < 1) {
            setError(t('categoryAssignmentCapacityRequired'))
            setSubmitting(false)
            return
          }
        }
      }
    }

    const payload = {
      categories: enabled.map((row) => {
        const major = row.is_paid ? Number(row.price.trim()) : 0
        const priceMinor = row.is_paid ? Math.round(major * 100) : 0

        return {
          category_template_id: Number(row.id),
          is_paid: row.is_paid,
          price_minor: priceMinor,
          currency: row.currency || 'SAR',
          venues: row.venues.map((venue) => ({
            event_venue_id: Number(venue.event_venue_id),
            days: venue.days.map((day) => ({
              date: day.date,
              capacity: day.capacity.trim() === '' ? null : Number(day.capacity),
            })),
          })),
        }
      }),
    }

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/categories/assignments`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: payload,
      })
      toast(t('categoryAssignmentSaved'), 'success')
    } catch (caught) {
      setError(caught instanceof ApiFetchError ? caught.message : t('categoryCouldNotSave'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={t('eventCategories')}>
      <PageHeader
        title={t('eventCategories')}
        description={t('categoryAssignmentDescription')}
        breadcrumbs={[
          { label: t('events'), href: '/tenant/events' },
          { label: eventName, href: `/tenant/events/${event.id}` },
          { label: t('eventCategories') },
        ]}
        actions={(
          <LocalizedLink
            href={localizedPath(locale, `/tenant/events/${event.id}`)}
            className="button-secondary"
          >
            {t('back')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        {venues.length === 0 || eventDates.length === 0 ? (
          <EmptyState
            title={t('categoryAssignmentNeedSchedule')}
            detail={t('categoryAssignmentNeedScheduleDetail')}
          />
        ) : rows.length === 0 ? (
          <EmptyState
            title={t('categoryNoCategories')}
            detail={t('categoryAssignmentNoTemplates')}
            action={(
              <LocalizedLink
                href={localizedPath(locale, '/tenant/categories/create')}
                className="button-primary"
              >
                {t('categoryAdd')}
              </LocalizedLink>
            )}
          />
        ) : (
          <form onSubmit={handleSubmit} className="space-y-4">
            {locked ? (
              <div className="rounded-[var(--radius-control)] border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                {t('categoryAssignmentLocked')}
              </div>
            ) : null}
            {error ? (
              <div className="rounded-[var(--radius-control)] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                {error}
              </div>
            ) : null}

            <div className="space-y-3">
              {rows.map((row) => {
                const name = locale === 'ar' ? (row.name_ar || row.name) : row.name
                const usedVenueIds = new Set(
                  row.venues.map((venue) => venue.event_venue_id).filter(Boolean),
                )

                return (
                  <section
                    key={row.id}
                    className={clsx(
                      'state-panel overflow-hidden transition',
                      row.enabled ? 'border-[var(--brand)]/40' : 'opacity-70',
                    )}
                  >
                    <button
                      type="button"
                      className="flex w-full items-center justify-between gap-3 px-4 py-3 text-start"
                      onClick={() => toggleCategory(row.id)}
                      disabled={readOnly}
                    >
                      <span className="flex items-center gap-3">
                        <span
                          className="h-3.5 w-3.5 rounded-full border border-black/10"
                          style={{ backgroundColor: row.color || '#94a3b8' }}
                          aria-hidden="true"
                        />
                        <span>
                          <span className="block font-semibold text-[var(--ink)]">{name}</span>
                          <span className="block text-xs text-[var(--muted)]">
                            {row.enabled ? t('categoryAssignmentEnabled') : t('categoryAssignmentDisabled')}
                          </span>
                        </span>
                      </span>
                      <span className={clsx(
                        'rounded-full px-2.5 py-1 text-xs font-medium',
                        row.enabled
                          ? 'bg-[var(--brand-soft)] text-[var(--brand)]'
                          : 'bg-[var(--surface)] text-[var(--muted)]',
                      )}
                      >
                        {row.enabled ? t('enabled') : t('disabled')}
                      </span>
                    </button>

                    {row.enabled ? (
                      <div className="space-y-4 border-t border-[var(--border)] px-4 py-4">
                        <div className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface)] p-3">
                          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <label className="flex cursor-pointer items-start gap-3 text-sm text-[var(--ink)]">
                              <input
                                type="checkbox"
                                className="mt-0.5 h-4 w-4 rounded border-[var(--border)] text-[var(--brand)] focus:ring-[var(--brand)]/30"
                                checked={row.is_paid}
                                disabled={readOnly}
                                onChange={(e) => updateCategory(row.id, {
                                  is_paid: e.target.checked,
                                  price: e.target.checked ? row.price : '',
                                })}
                              />
                              <span>
                                <span className="block font-medium">{t('categoryIsPaid')}</span>
                                <span className="mt-0.5 block text-xs text-[var(--muted)]">
                                  {row.is_paid
                                    ? t('categoryAssignmentPriceHint')
                                    : t('categoryAssignmentFreeHint')}
                                </span>
                              </span>
                            </label>

                            {row.is_paid ? (
                              <div className="sm:w-52">
                                <label className="grid gap-1.5 text-sm" htmlFor={`price-${row.id}`}>
                                  <span className="font-medium text-[var(--ink)]">
                                    {t('categoryAssignmentPrice')}
                                    <span className="ms-1 text-red-600">*</span>
                                  </span>
                                  <span className="relative">
                                    <span className="pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-xs font-semibold text-[var(--muted)]">
                                      {row.currency || 'SAR'}
                                    </span>
                                    <input
                                      id={`price-${row.id}`}
                                      type="number"
                                      min={0.01}
                                      step="0.01"
                                      inputMode="decimal"
                                      value={row.price}
                                      disabled={readOnly}
                                      required
                                      placeholder="0.00"
                                      className={clsx(
                                        'control w-full ps-12 focus:border-[var(--brand)] focus:outline-none focus:ring-2 focus:ring-[var(--brand)]/20',
                                        readOnly && 'opacity-70',
                                      )}
                                      onChange={(e) => updateCategory(row.id, { price: e.target.value })}
                                    />
                                  </span>
                                </label>
                              </div>
                            ) : (
                              <span className="inline-flex items-center rounded-full bg-[var(--surface-elevated)] px-3 py-1.5 text-xs font-medium text-[var(--muted)]">
                                {t('publicRegistrationFree')}
                              </span>
                            )}
                          </div>
                        </div>

                        <div className="space-y-3">
                          {row.venues.map((venue) => {
                            const availableOptions = venueOptions.filter((option) => (
                              option.value === venue.event_venue_id || !usedVenueIds.has(option.value)
                            ))

                            return (
                              <div
                                key={venue.key}
                                className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface)] p-3 space-y-3"
                              >
                                <div className="flex flex-wrap items-end gap-3">
                                  <div className="min-w-[14rem] flex-1">
                                    <SelectInput
                                      label={t('categoryAssignmentVenue')}
                                      name={`venue-${venue.key}`}
                                      value={venue.event_venue_id}
                                      disabled={readOnly}
                                      onChange={(e) => updateVenue(row.id, venue.key, {
                                        event_venue_id: e.target.value,
                                      })}
                                      options={[
                                        { value: '', label: t('categoryAssignmentSelectVenue') },
                                        ...availableOptions,
                                      ]}
                                      required
                                    />
                                  </div>
                                  {!readOnly && row.venues.length > 1 ? (
                                    <button
                                      type="button"
                                      className="button-secondary inline-flex items-center gap-2 text-red-600"
                                      onClick={() => removeVenue(row.id, venue.key)}
                                    >
                                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                                      {t('remove')}
                                    </button>
                                  ) : null}
                                </div>

                                {venue.event_venue_id ? (
                                  <div className="space-y-2">
                                    <p className="text-sm font-medium text-[var(--ink)]">
                                      {t('categoryAssignmentDays')}
                                    </p>
                                    {venue.days.map((day) => (
                                      <div
                                        key={day.date}
                                        className="grid gap-3 sm:grid-cols-[10rem_minmax(0,1fr)] sm:items-end"
                                      >
                                        <div className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface-elevated)] px-3 py-2 text-sm text-[var(--ink)]">
                                          {formatDateLabel(day.date, locale)}
                                        </div>
                                        <TextInput
                                          label={t('categoryAssignmentRegistrations')}
                                          type="number"
                                          min={1}
                                          value={day.capacity}
                                          disabled={readOnly}
                                          placeholder={t('categoryCapacityPlaceholder')}
                                          onChange={(e) => updateDayCapacity(
                                            row.id,
                                            venue.key,
                                            day.date,
                                            e.target.value,
                                          )}
                                        />
                                      </div>
                                    ))}
                                  </div>
                                ) : null}
                              </div>
                            )
                          })}
                        </div>

                        {!readOnly && row.venues.length < venues.length ? (
                          <button
                            type="button"
                            className="button-secondary inline-flex items-center gap-2"
                            onClick={() => addVenue(row.id)}
                          >
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            {t('categoryAssignmentAddVenue')}
                          </button>
                        ) : null}
                      </div>
                    ) : null}
                  </section>
                )
              })}
            </div>

            {!readOnly ? (
              <div className="flex justify-end">
                <SubmitButtonWithLoader
                  label={t('save')}
                  loading={submitting}
                />
              </div>
            ) : null}
          </form>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
