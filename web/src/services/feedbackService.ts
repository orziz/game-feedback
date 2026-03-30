import { apiRequest } from '../api/client'

const API_BASE = import.meta.env.VITE_API_BASE || '/api'

export function checkInstallStatus() {
  return apiRequest<InstallStatusResponse>('install_status', { method: 'GET' })
}

export function fetchEnumOptions(lang: LocaleCode) {
  return apiRequest<EnumOptionsResponse>('enum_options', {
    method: 'GET',
    params: { lang },
  })
}

export function installSystem(payload: InstallForm) {
  return apiRequest('install', {
    body: {
      host:          payload.host,
      port:          Number(payload.port),
      database:      payload.database.trim(),
      username:      payload.username.trim(),
      password:      payload.password,
      adminPassword: payload.adminPassword,
      uploadMode:    payload.uploadMode,
      qiniuAccessKey: payload.qiniuAccessKey.trim(),
      qiniuSecretKey: payload.qiniuSecretKey.trim(),
      qiniuBucket:    payload.qiniuBucket.trim(),
      qiniuDomain:    payload.qiniuDomain.trim(),
    },
  })
}

export function submitFeedback(payload: SubmitForm) {
  const formData = new FormData()
  const details = payload.description.trim()
  formData.append('type', String(payload.type))
  formData.append('severity', String(payload.severity))
  formData.append('title', payload.title.trim())
  formData.append('reproduceSteps', details)
  formData.append('description', details)
  formData.append('contact', payload.contact.trim())
  if (payload.attachmentFile) {
    formData.append('attachment', payload.attachmentFile)
  }

  return apiRequest<SubmitResponse>('submit', {
    body: formData,
  })
}

export function fetchTicket(ticketNo: string) {
  return apiRequest<TicketResponse>('ticket', {
    method: 'GET',
    params: { ticketNo: ticketNo.trim() },
  })
}

export function searchTickets(keyword: string, page = 1, pageSize = 10) {
  return apiRequest<TicketSearchResponse>('ticket_search', {
    method: 'GET',
    params: { keyword: keyword.trim(), page, pageSize },
  })
}

export function adminLogin(password: string) {
  return apiRequest<AdminLoginResponse>('admin_login', { body: { password } })
}

export function fetchAdminTickets(
  token: string,
  params: { page: number; pageSize: number; status?: TicketStatus; type?: FeedbackType; keyword?: string },
) {
  return apiRequest<AdminListResponse>('admin_list', {
    method: 'GET',
    token,
    params: params as Record<string, string | number | boolean | undefined>,
  })
}

export function fetchAdminTicketDetail(token: string, ticketNo: string) {
  return apiRequest<AdminDetailResponse>('admin_detail', {
    method: 'GET',
    token,
    params: { ticketNo },
  })
}

export function updateAdminTicket(
  token: string,
  payload: { ticketNo: string; status: TicketStatus; severity: Severity | null; adminNote: string },
) {
  return apiRequest('admin_update', { token, body: payload as Record<string, unknown> })
}

export async function downloadAdminAttachment(token: string, ticketNo: string, attachmentName: string): Promise<void> {
  const searchParams = new URLSearchParams({
    action: 'admin_attachment_download',
    ticketNo: ticketNo.trim(),
  })

  const response = await fetch(`${API_BASE}?${searchParams.toString()}`, {
    method: 'GET',
    headers: {
      Authorization: `Bearer ${token}`,
    },
  })

  if (!response.ok) {
    const data = await response.json().catch(() => ({
      ok: false,
      code: 'REQUEST_FAILED',
      message: 'Download failed',
    })) as ApiResponseBase

    const error = new Error(data.message || 'Download failed') as ApiError
    error.code = data.code || 'REQUEST_FAILED'
    error.payload = data as ApiResponseBase & Record<string, unknown>
    throw error
  }

  const blob = await response.blob()
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = attachmentName || `attachment-${ticketNo}`
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(objectUrl)
}
