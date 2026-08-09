import { useCallback, useEffect, useState } from 'react'
import { X, RefreshCw } from 'lucide-react'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { useToast } from '@/hooks/useToast'

type Version = {
  id: number
  version: number
  status: string
  published_at: string | null
  published_by: number | null
  block_count: number
}

type Props = {
  eventId: string
  tenantId: string
  currentVersionId: number | null
  onRestore: (versionId: number) => void
  onClose: () => void
}

export default function VersionHistory({
  eventId,
  tenantId,
  currentVersionId,
  onRestore,
  onClose,
}: Props) {
  const { toast } = useToast()
  const [versions, setVersions] = useState<Version[]>([])
  const [loading, setLoading] = useState(true)

  const fetchVersions = useCallback(async () => {
    setLoading(true)
    try {
      const response = await apiFetch<{ versions: Version[] }>(
        `/api/v1/tenant/events/${eventId}/site/versions`,
        { tenantId },
      )
      setVersions(response.versions)
    } catch (err) {
      toast(err instanceof ApiFetchError ? err.message : 'Failed to load versions.', 'error')
    } finally {
      setLoading(false)
    }
  }, [eventId, tenantId, toast])

  useEffect(() => {
    fetchVersions()
  }, [fetchVersions])

  const formatDate = (dateString: string | null) => {
    if (!dateString) return '—'
    return new Date(dateString).toLocaleString()
  }

  return (
    <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
      <div className="relative bg-background rounded-lg shadow-xl w-full max-w-lg max-h-[80vh] overflow-hidden flex flex-col">
        <div className="flex items-center justify-between p-4 border-b">
          <h2 className="text-lg font-semibold">Version History</h2>
          <button
            type="button"
            onClick={onClose}
            className="p-1 rounded hover:bg-muted"
          >
            <X className="w-6 h-6" />
          </button>
        </div>

        <div className="flex-1 overflow-auto p-4">
          {loading ? (
            <div className="flex items-center justify-center h-32">
              <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary" />
            </div>
          ) : versions.length === 0 ? (
            <div className="text-center text-muted-foreground py-8">
              No published versions yet.
            </div>
          ) : (
            <div className="space-y-2">
              {versions.map((version) => {
                const isCurrent = version.id === currentVersionId

                return (
                  <div
                    key={version.id}
                    className={`
                      flex items-center justify-between p-3 rounded-lg border
                      ${isCurrent ? 'border-primary bg-primary/5' : 'border-border'}
                    `}
                  >
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-medium">Version {version.version}</span>
                        {isCurrent && (
                          <span className="text-xs bg-primary text-primary-foreground px-2 py-0.5 rounded">
                            Current
                          </span>
                        )}
                        {version.status === 'superseded' && (
                          <span className="text-xs bg-muted text-muted-foreground px-2 py-0.5 rounded">
                            Superseded
                          </span>
                        )}
                      </div>
                      <p className="text-sm text-muted-foreground">
                        {formatDate(version.published_at)} • {version.block_count} blocks
                      </p>
                    </div>

                    {!isCurrent && (
                      <button
                        type="button"
                        onClick={() => onRestore(version.id)}
                        className="flex items-center gap-1 text-sm text-primary hover:underline"
                      >
                        <RefreshCw className="w-4 h-4" />
                        Restore
                      </button>
                    )}
                  </div>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}
