import { FormEvent, useState } from 'react'
import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useFormValidation } from '@/hooks/useFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import {
  agendaFieldSelector,
  buildAgendaPayload,
  formatAgendaValidationMessage,
  formFieldProps,
  remapAgendaApiErrors,
} from '@/lib/formatValidationErrors'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type AgendaRow = {
  key: string
  id?: string
  title_en: string
  title_ar: string
  start_at: string
  end_at: string
}

type Props = {
  event: EventRow
  tenantId: string
  items: Array<{
    id: string
    title_en: string
    title_ar: string
    start_at: string | null
    end_at: string | null
  }>
}

function toLocalDateTime(value: string | null | undefined): string {
  if (!value) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return ''
  const pad = (n: number) => n.toString().padStart(2, '0')

  return `${parsed.getFullYear()}-${pad(parsed.getMonth() + 1)}-${pad(parsed.getDate())}T${pad(parsed.getHours())}:${pad(parsed.getMinutes())}`
}

function fromLocalDateTime(value: string): string | null {
  if (!value.trim()) return null
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return null

  return parsed.toISOString()
}

function mapInitialItems(items: Props['items']): AgendaRow[] {
  if (items.length === 0) {
    return [emptyAgendaRow()]
  }

  return items.map((item) => ({
    key: item.id,
    id: item.id,
    title_en: item.title_en,
    title_ar: item.title_ar,
    start_at: toLocalDateTime(item.start_at),
    end_at: toLocalDateTime(item.end_at),
  }))
}

function emptyAgendaRow(): AgendaRow {
  return {
    key: crypto.randomUUID(),
    title_en: '',
    title_ar: '',
    start_at: '',
    end_at: '',
  }
}

export default function EventAgenda({ event, tenantId, items: initialItems }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const validation = useFormValidation({ titleKey: 'couldNotSaveAgenda' })
  const [items, setItems] = useState<AgendaRow[]>(() => mapInitialItems(initialItems))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  function updateItem(index: number, patch: Partial<AgendaRow>) {
    setItems((current) => current.map((row, rowIndex) => (rowIndex === index ? { ...row, ...patch } : row)))
    validation.clearField(`items.${index}`)
  }

  function addItem() {
    setItems((current) => [...current, emptyAgendaRow()])
  }

  function removeItem(index: number) {
    setItems((current) => (current.length <= 1 ? [emptyAgendaRow()] : current.filter((_, rowIndex) => rowIndex !== index)))
  }

  function moveItem(index: number, direction: -1 | 1) {
    setItems((current) => {
      const target = index + direction
      if (target < 0 || target >= current.length) return current
      const next = [...current]
      const [row] = next.splice(index, 1)
      next.splice(target, 0, row)

      return next
    })
  }

  async function handleSubmit(submitEvent: FormEvent<HTMLFormElement>) {
    submitEvent.preventDefault()
    setError(null)
    validation.clearValidation()
    setSubmitting(true)

    const { payload, formIndexByPayloadIndex: payloadIndexMap } = buildAgendaPayload(items, fromLocalDateTime)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/agenda`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { items: payload },
      })
      toast(t('eventAgendaSaved'), 'success')
      router.reload({ only: ['items'] })
    } catch (caught) {
      if (validation.applyApiError(caught, {
        remapErrors: (errors) => remapAgendaApiErrors(errors, payloadIndexMap),
        selectorForKey: (key) => agendaFieldSelector(key, payloadIndexMap),
        formatMessage: (key, message, currentLocale) => formatAgendaValidationMessage(key, message, currentLocale, payloadIndexMap),
      })) {
        setError(null)
      } else {
        setError(caught instanceof ApiFetchError ? caught.message : t('eventAgendaSaveFailed'))
      }
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={t('eventAgendaTitle')}
        description={t('eventAgendaDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/' },
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
        <form className="state-panel relative space-y-6" onSubmit={(submitEvent) => void handleSubmit(submitEvent)}>
          <p className="text-sm text-muted">
            {t('eventAgendaIntro')}
          </p>

          {items.map((row, index) => (
            <section key={row.key} className="rounded-xl border border-border p-4 space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">
                  {`${t('eventAgendaItem')} ${index + 1}`}
                </h2>
                <div className="flex flex-wrap gap-2">
                  <button type="button" className="button-secondary" onClick={() => moveItem(index, -1)} disabled={index === 0}>
                    {t('eventAgendaMoveUp')}
                  </button>
                  <button type="button" className="button-secondary" onClick={() => moveItem(index, 1)} disabled={index === items.length - 1}>
                    {t('eventAgendaMoveDown')}
                  </button>
                  <button type="button" className="button-secondary" onClick={() => removeItem(index)}>
                    {t('eventAgendaRemove')}
                  </button>
                </div>
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <TextInput
                  label={t('eventAgendaTitleEn')}
                  name={`title_en_${index}`}
                  value={row.title_en}
                  onChange={(changeEvent) => updateItem(index, { title_en: changeEvent.target.value })}
                  error={validation.fieldError(`items.${index}.title_en`)}
                  {...formFieldProps(`items.${index}.title_en`)}
                  required
                />
                <TextInput
                  label={t('eventAgendaTitleAr')}
                  name={`title_ar_${index}`}
                  value={row.title_ar}
                  onChange={(changeEvent) => updateItem(index, { title_ar: changeEvent.target.value })}
                  error={validation.fieldError(`items.${index}.title_ar`)}
                  {...formFieldProps(`items.${index}.title_ar`)}
                  required
                />
                <DateTimeInput
                  label={t('eventAgendaStartsAt')}
                  name={`start_at_${index}`}
                  value={row.start_at}
                  onChange={(changeEvent) => updateItem(index, { start_at: changeEvent.target.value })}
                  error={validation.fieldError(`items.${index}.start_at`)}
                  {...formFieldProps(`items.${index}.start_at`)}
                  required
                />
                <DateTimeInput
                  label={t('eventAgendaEndsAt')}
                  name={`end_at_${index}`}
                  value={row.end_at}
                  onChange={(changeEvent) => updateItem(index, { end_at: changeEvent.target.value })}
                  error={validation.fieldError(`items.${index}.end_at`)}
                  {...formFieldProps(`items.${index}.end_at`)}
                />
              </div>
            </section>
          ))}

          <div className="flex flex-wrap gap-2">
            <button type="button" className="button-secondary" onClick={addItem}>
              {t('eventAgendaAddItem')}
            </button>
          </div>

          {error ? <p role="alert" className="text-sm text-danger">{error}</p> : null}

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
