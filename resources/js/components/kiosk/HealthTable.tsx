import { useEffect, useState } from 'react'
import DataTable from '@/components/tables/DataTable'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { Kiosk } from '@/types/phase3'

interface HealthTableProps {
  eventId: string
  tenantId: string
  pollIntervalMs?: number
}

export function HealthTable({ eventId, tenantId, pollIntervalMs = 15000 }: HealthTableProps) {
  const { locale, t } = useLocale()
  const [kiosks, setKiosks] = useState<Kiosk[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let cancelled = false

    const poll = async () => {
      try {
        const res = await fetch(`/api/v1/tenant/events/${eventId}/kiosks`, {
          credentials: 'include',
          headers: { Accept: 'application/json', 'X-Tenant-ID': tenantId },
        })
        if (!res.ok) throw new Error('Failed to fetch kiosk health')
        const data = await res.json()
        if (cancelled) return
        setKiosks(data.data ?? [])
        setError(null)
      } catch (e) {
        if (cancelled) return
        setError(e instanceof Error ? e.message : 'Unknown error')
      } finally {
        if (!cancelled) {
          setLoading(false)
        }
      }
    }

    void poll()
    const interval = setInterval(() => {
      void poll()
    }, pollIntervalMs)

    return () => {
      cancelled = true
      clearInterval(interval)
    }
  }, [eventId, tenantId, pollIntervalMs])

  if (loading) {
    return <p className="text-sm text-[var(--muted)]">{t('kioskHealthLoading')}</p>
  }

  if (error) {
    return <p role="alert" className="text-sm text-rose-600">{t('kioskHealthError')}</p>
  }

  if (kiosks.length === 0) {
    return <p className="text-sm text-[var(--muted)]">{t('kioskHealthNoKiosks')}</p>
  }

  return (
    <DataTable
      rows={kiosks as unknown as Record<string, unknown>[]}
      getRowKey={(row) => String(row.id)}
      columns={[
        { key: 'device_name', header: t('kioskHealthDevice') },
        {
          key: 'status',
          header: t('status'),
          render: (row) => <StatusBadge status={String(row.status)} />,
        },
        {
          key: 'printer_status',
          header: t('kioskHealthPrinter'),
          render: (row) => (row.printer_status ? <StatusBadge status={String(row.printer_status)} /> : '—'),
        },
        {
          key: 'last_heartbeat_at',
          header: t('kioskHealthLastHeartbeat'),
          render: (row) => {
            const value = row.last_heartbeat_at
            return value ? new Date(String(value)).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US') : '—'
          },
        },
        { key: 'device_code', header: t('kioskHealthDeviceCode') },
      ]}
    />
  )
}
