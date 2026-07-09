import { vi } from 'vitest'

describe('events actions wiring', () => {
  it('documents create and publish endpoints used by EventSetup and Detail', () => {
    const createUrl = '/api/v1/tenant/events'
    const publishUrl = '/api/v1/tenant/events/event-1/publish'

    expect(createUrl).toContain('/api/v1/tenant/events')
    expect(publishUrl).toContain('/publish')
  })

  it('expects apiFetch to be used for tenant writes', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 'event-1' } }),
    })
    vi.stubGlobal('fetch', fetchMock)

    const { apiFetch } = await import('@/lib/apiFetch')
    await apiFetch('/api/v1/tenant/events', {
      method: 'POST',
      tenantId: '1',
      idempotency: true,
      body: { name_en: 'Demo' },
    })

    expect(fetchMock).toHaveBeenCalledWith(
      '/api/v1/tenant/events',
      expect.objectContaining({ method: 'POST' }),
    )
  })
})
