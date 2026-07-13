import { describe, expect, it } from 'vitest'
import { normalizeScanPayload } from '@/lib/normalizeScanPayload'

const token = 'zt1.phase1-test.eyJjaWQiOiIyNCIsImVpZCI6IjEiLCJleHAiOjE3ODM4ODU5ODAsImlhdCI6MTc4Mzg2MDQ4Miwibm9uY2UiOiJ5ZUxKRkdXQkxqZjNaZzRWQ0VmMnh3IiwidGlkIjoiMSJ9.O01UKZRI2AcoNCrxST_62WytCXY4K-9NTP4dsDedtgaKxtvUJ1t1x56lha-CMXEIP4VgNiKpyylU_L6NBTSSDg'

describe('normalizeScanPayload', () => {
  it('returns trimmed raw token payloads', () => {
    expect(normalizeScanPayload(`  ${token}  `)).toBe(token)
  })

  it('extracts token from confirmation URLs', () => {
    expect(normalizeScanPayload(`https://zoon.test/en/orders/ord_123?token=${token}`)).toBe(token)
  })
})
