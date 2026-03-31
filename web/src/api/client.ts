import { i18n } from '@/i18n'

const DEFAULT_API_BASE = import.meta.env.VITE_API_BASE || '/api'

type InternalRequestOptions = {
  params?: API.Meta.QueryParams
  body?: Record<string, unknown> | FormData
  responseType?: 'json' | 'blob'
}

type PostFormPayloadWithParams = {
  formData: FormData
  params?: API.Meta.QueryParams
}

function isPostFormPayloadWithParams(payload: unknown): payload is PostFormPayloadWithParams {
  if (!payload || typeof payload !== 'object') {
    return false
  }

  const candidate = payload as { formData?: unknown }
  return typeof FormData !== 'undefined' && candidate.formData instanceof FormData
}

let tokenGetter: (() => string) | null = null

const METHOD_CONFIG: Record<ApiMethodKey, { httpMethod: 'GET' | 'POST'; responseType: 'json' | 'blob' }> = {
  get: {
    httpMethod: 'GET',
    responseType: 'json',
  },
  post: {
    httpMethod: 'POST',
    responseType: 'json',
  },
  postForm: {
    httpMethod: 'POST',
    responseType: 'json',
  },
  getBlob: {
    httpMethod: 'GET',
    responseType: 'blob',
  },
}

function isApiMethodKey(value: string): value is ApiMethodKey {
  return Object.prototype.hasOwnProperty.call(METHOD_CONFIG, value)
}

export function setApiTokenGetter(getter: (() => string) | null): void {
  tokenGetter = getter
}

async function buildRequestError(response: Response): Promise<ApiError> {
  const responseText = await response.text()

  let payload: ApiResponseBase & Record<string, unknown>
  try {
    payload = JSON.parse(responseText) as ApiResponseBase & Record<string, unknown>
  } catch {
    payload = {
      ok: false,
      code: 'REQUEST_FAILED',
      message: responseText.slice(0, 200) || `HTTP ${response.status}`,
    }
  }

  const error = new Error(payload.message || i18n.global.t('messages.requestFailed')) as ApiError
  error.code = payload.code || 'REQUEST_FAILED'
  error.payload = payload

  return error
}

function normalizeRequestOptions(methodKey: ApiMethodKey, payload: unknown): InternalRequestOptions {
  if (methodKey === 'get' || methodKey === 'getBlob') {
    return {
      params: (payload ?? {}) as API.Meta.QueryParams,
      responseType: METHOD_CONFIG[methodKey].responseType,
    }
  }

  if (methodKey === 'postForm') {
    if (isPostFormPayloadWithParams(payload)) {
      return {
        params: payload.params ?? {},
        body: payload.formData,
        responseType: METHOD_CONFIG[methodKey].responseType,
      }
    }

    return {
      body: payload instanceof FormData ? payload : new FormData(),
      responseType: METHOD_CONFIG[methodKey].responseType,
    }
  }

  return {
    body: (payload ?? {}) as Record<string, unknown>,
    responseType: METHOD_CONFIG[methodKey].responseType,
  }
}

async function request<T>(baseUrl: string, segments: string[], payload?: unknown): Promise<T> {
  if (segments.length !== 4) {
    throw new Error(`Incomplete API action path for baseUrl "${baseUrl}"`)
  }

  const [moduleName, subModuleName, methodKey, functionName] = segments
  if (!isApiMethodKey(methodKey)) {
    throw new Error(`Unsupported API method "${methodKey}"`)
  }

  const { httpMethod } = METHOD_CONFIG[methodKey]
  const { params = {}, body, responseType = 'json' } = normalizeRequestOptions(methodKey, payload)
  const isFormData = typeof FormData !== 'undefined' && body instanceof FormData
  const route = `${moduleName}/${subModuleName}/${functionName}`
  const token = tokenGetter ? tokenGetter() : ''

  const searchParams = new URLSearchParams({ s: route })
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined) {
      searchParams.set(key, String(value))
    }
  })

  const response = await fetch(`${baseUrl}?${searchParams.toString()}`, {
    method: httpMethod,
    headers: {
      ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body ? (isFormData ? body : JSON.stringify(body)) : undefined,
  })

  if (!response.ok) {
    throw await buildRequestError(response)
  }

  if (responseType === 'blob') {
    return await response.blob() as T
  }

  const responseText = await response.text()

  let data: ApiResponseBase & Record<string, unknown>
  try {
    data = JSON.parse(responseText) as ApiResponseBase & Record<string, unknown>
  } catch {
    const error = new Error(`${i18n.global.t('messages.invalidResponse')} (HTTP ${response.status})`) as ApiError
    error.code = 'INVALID_RESPONSE'
    error.payload = {
      ok: false,
      code: 'INVALID_RESPONSE',
      message: responseText.slice(0, 200),
    }
    throw error
  }

  if (!data.ok) {
    const error = new Error(data.message || i18n.global.t('messages.requestFailed')) as ApiError
    error.code = data.code || 'REQUEST_FAILED'
    error.payload = data
    throw error
  }

  return data as T
}

function createProxy<T>(baseUrl = DEFAULT_API_BASE, segments: string[] = []): T {
  const callable = (() => undefined) as unknown as (...args: unknown[]) => unknown

  return new Proxy(callable, {
    get(_target, prop) {
      if (typeof prop !== 'string') {
        return undefined
      }

      return createProxy(baseUrl, [...segments, prop])
    },
    apply(_target, _thisArg, argArray) {
      return request(baseUrl, segments, argArray[0])
    },
  }) as T
}

export const api = createProxy<ApiSchema>()
