import { i18n } from '../i18n'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export async function apiRequest<T extends ApiResponseBase>(action: string, options: ApiRequestOptions = {}): Promise<T> {
  const { method = 'POST', body, params = {}, token = '' } = options
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData

  const searchParams = new URLSearchParams({ action })
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined) {
      searchParams.set(key, String(value))
    }
  })

  const response = await fetch(`${API_BASE}?${searchParams.toString()}`, {
    method,
    headers: {
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
  })

  let responseText = ''
  let data: T
  try {
    responseText = await response.text()
    data = JSON.parse(responseText) as T
  } catch (parseError) {
    console.error('Response parse error:', { status: response.status, responseText: responseText.slice(0, 500) })
    const error = new Error(`${i18n.global.t('messages.invalidResponse')} (HTTP ${response.status})`) as ApiError
    error.code = 'INVALID_RESPONSE'
    error.payload = {
      ok: false,
      code: 'INVALID_RESPONSE',
      message: responseText.slice(0, 200),
    } as ApiResponseBase & Record<string, unknown>
    throw error
  }

  if (!response.ok || !data.ok) {
    const error = new Error(data.message || i18n.global.t('messages.requestFailed')) as ApiError
    error.code = data.code || 'REQUEST_FAILED'
    error.payload = data as ApiResponseBase & Record<string, unknown>
    throw error
  }

  return data
}