import { useEffect, useState } from 'react'
import { Copy } from 'lucide-react'
import DataTable from '@/components/tables/DataTable'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { localizedPath } from '@/lib/localePath'
import type { Kiosk } from '@/types/phase3'

interface HealthTableProps {
  eventId: string
  tenantId: string
  pollIntervalMs?: number
}

export function HealthTable({ eventId, tenantId, pollIntervalMs = 15000 }: HealthTableProps) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
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

  function kioskModeUrl(deviceCode: string): string {
    const path = localizedPath(locale, `/kiosk/${deviceCode}/unlock`)
    if (typeof window === 'undefined') {
      return path
    }

    return `${window.location.origin}${path}`
  }

  async function copyKioskLink(deviceCode: string) {
    try {
      await navigator.clipboard.writeText(kioskModeUrl(deviceCode))
      toast(t('copied'), 'success')
    } catch {
      toast(t('eventDetailCouldNotCopyLink'), 'error')
    }
  }

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
        {
          key: 'device_code',
          header: t('kioskHealthDeviceCode'),
          render: (row) => {
            const code = String(row.device_code ?? '')
            if (!code) return '—'

            const href = kioskModeUrl(code)

            return (
              <div className="flex items-center gap-2">
                <a
                  href={href}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="font-mono text-sm font-medium text-[var(--brand)] hover:underline"
                >
                  {code}
                </a>
                <button
                  type="button"
                  className="ta-table-action inline-flex items-center gap-1"
                  title={t('kioskHealthCopyLink')}
                  onClick={() => void copyKioskLink(code)}
                >
                  <Copy className="h-3.5 w-3.5" aria-hidden />
                  <span className="sr-only">{t('kioskHealthCopyLink')}</span>
                </button>
              </div>
            )
          },
        },
      ]}
    />
  )
}
