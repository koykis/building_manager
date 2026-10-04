import { afterEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'

vi.mock('axios', () => ({ default: { create: vi.fn(() => ({ interceptors: { request: { use: vi.fn() } } })), get: vi.fn() } }))
afterEach(() => { vi.unstubAllEnvs(); vi.clearAllMocks(); vi.resetModules() })

describe('Application base path', () => {
  it.each(['/', '/building_manager/'])('keeps API, downloads and CSRF requests under %s', async base => {
    vi.stubEnv('BASE_URL', base)
    const { appBase, apiUrl, csrf } = await import('./api')
    expect(appBase).toBe(base)
    expect(axios.create).toHaveBeenCalledWith(expect.objectContaining({ baseURL: `${base}api/v1`, withCredentials: true, withXSRFToken: true }))
    expect(apiUrl('/admin/documents/42')).toBe(`${base}api/v1/admin/documents/42`)
    expect(apiUrl('/admin/statements/42/export')).toBe(`${base}api/v1/admin/statements/42/export`)
    await csrf()
    expect(axios.get).toHaveBeenCalledWith(`${base}sanctum/csrf-cookie`, { withCredentials: true })
  })
})
