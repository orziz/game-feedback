type LocaleCode = 'zh-CN' | 'en'
type AppTab = 'submit' | 'query' | 'solution-search' | 'admin'

type FeedbackType = 0 | 1 | 2 | 3
type Severity = 0 | 1 | 2 | 3
type TicketStatus = 0 | 1 | 2 | 3
type UploadMode = 'off' | 'local' | 'qiniu'

interface TicketRecord {
  ticket_no: string
  type: FeedbackType
  severity: Severity | null
  title: string
  details: string
  contact: string
  status: TicketStatus
  admin_note: string
  attachment_name?: string | null
  attachment_storage?: string | null
  attachment_mime?: string | null
  attachment_size?: number | null
  created_at: string
  updated_at: string
}

interface SubmitForm {
  type: FeedbackType
  severity: Severity
  title: string
  reproduceSteps?: string
  description: string
  contact: string
  attachmentFile: File | null
}

interface InstallForm {
  host: string
  port: number
  database: string
  username: string
  password: string
  adminPassword: string
  uploadMode: UploadMode
  qiniuAccessKey: string
  qiniuSecretKey: string
  qiniuBucket: string
  qiniuDomain: string
}

interface AdminUpdateForm {
  status: TicketStatus
  severity: Severity | null
  adminNote: string
}

interface PaginationInfo {
  total: number
}

interface ApiResponseBase {
  ok: boolean
  code?: string
  message?: string
}

interface InstallStatusResponse extends ApiResponseBase {
  installed: boolean
  uploadMode?: UploadMode
}

interface EnumOption<T extends number = number> {
  label: string
  value: T
}

interface EnumOptionsResponse extends ApiResponseBase {
  types: EnumOption<FeedbackType>[]
  severities: EnumOption<Severity>[]
  statuses: EnumOption<TicketStatus>[]
}

interface SubmitResponse extends ApiResponseBase {
  ticketNo: string
}

interface TicketResponse extends ApiResponseBase {
  ticket: TicketRecord
}

interface TicketSearchResponse extends ApiResponseBase {
  tickets: TicketRecord[]
  pagination?: PaginationInfo
}

interface AdminLoginResponse extends ApiResponseBase {
  token: string
}

interface AdminListResponse extends ApiResponseBase {
  tickets: TicketRecord[]
  pagination?: PaginationInfo
}

interface AdminDetailResponse extends ApiResponseBase {
  ticket: TicketRecord
}

interface ApiError extends Error {
  code: string
  payload?: ApiResponseBase & Record<string, unknown>
}

interface ApiRequestOptions {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
  body?: Record<string, unknown> | FormData
  params?: Record<string, string | number | boolean | undefined>
  token?: string
}