declare namespace API.Admin.Ticket {
  interface API {
    get: {
      list: API.Meta.Get<HttpParams.List, AdminListResponse>
      assignees: API.Meta.Get<void, AdminAssigneeListResponse>
      detail: API.Meta.Get<HttpParams.Detail, AdminDetailResponse>
      getOperations: API.Meta.Get<HttpParams.GetOperations, AdminOperationsResponse>
      attachmentUrl: API.Meta.Get<HttpParams.AttachmentUrl, AttachmentUrlResponse>
      cleanupConfig: API.Meta.Get<void, CleanupConfigResponse>
    }
    getBlob: {
      attachmentDownload: API.Meta.GetBlob<HttpParams.AttachmentDownload>
    }
    post: {
      update: API.Meta.Post<HttpParams.Update, ApiResponseBase>
      assign: API.Meta.Post<HttpParams.Assign, ApiResponseBase>
      batchAssign: API.Meta.Post<HttpParams.BatchAssign, BatchAssignResponse>
      updateCleanupConfig: API.Meta.Post<HttpParams.UpdateCleanupConfig, CleanupConfigResponse>
      cleanupAttachments: API.Meta.Post<void, CleanupRunResponse>
    }
  }

  namespace HttpParams {
    interface List {
      page?: number
      pageSize?: number
      status?: TicketStatus
      type?: FeedbackType
      severity?: Severity
      keyword?: string
      assignedTo?: number
    }

    interface Detail {
      ticketNo: string
    }

    interface AttachmentDownload {
      ticketNo: string
    }

    interface Update {
      ticketNo: string
      status: TicketStatus
      severity: Severity | null
      adminNote: string
    }

    interface Assign {
      ticketNo: string
      assignedTo: number | null
    }

    interface BatchAssign {
      ticketNos: string[]
      assignedTo: number
    }

    interface GetOperations {
      ticketNo: string
    }

    interface AttachmentUrl {
      ticketNo: string
    }

    interface UpdateCleanupConfig {
      enabled: boolean
      retentionDays: number
      intervalSeconds: number
      batchLimit: number
    }
  }
}

interface TicketRecord {
  ticket_no: string
  type: FeedbackType
  severity: Severity | null
  title: string
  details: string
  contact: string
  status: TicketStatus
  admin_note: string
  assigned_to?: number | null
  assigned_username?: string | null
  attachment_name?: string | null
  attachment_storage?: string | null
  attachment_mime?: string | null
  attachment_size?: number | null
  created_at: string
  updated_at: string
}

interface AdminAssigneeUser {
  id: number
  username: string
}

interface TicketOperation {
  id: number
  operator_id: number
  operator_username: string
  operation_type: 'status_change' | 'assign'
  old_value: string | null
  new_value: string
  created_at: string
}

interface AdminUpdateForm {
  status: TicketStatus
  severity: Severity | null
  adminNote: string
  assignedTo?: number | null
}

interface AdminListResponse extends ApiResponseBase {
  tickets: TicketRecord[]
  pagination?: PaginationInfo
}

interface AdminAssigneeListResponse extends ApiResponseBase {
  users: AdminAssigneeUser[]
}

interface AdminDetailResponse extends ApiResponseBase {
  ticket: TicketRecord
  operations?: TicketOperation[]
}

interface AdminOperationsResponse extends ApiResponseBase {
  operations: TicketOperation[]
}

interface AttachmentUrlResponse extends ApiResponseBase {
  mode: 'direct' | 'proxy'
  url?: string
}

interface BatchAssignResponse extends ApiResponseBase {
  affected: number
}

interface CleanupConfigResponse extends ApiResponseBase {
  enabled: boolean
  retentionDays: number
  intervalSeconds: number
  batchLimit: number
}

interface CleanupRunResult {
  enabled: boolean
  retentionDays: number
  scanned: number
  deleted: number
  alreadyMissing: number
  errors: Array<{
    ticketNo: string
    message: string
  }>
}

interface CleanupRunResponse extends ApiResponseBase {
  result: CleanupRunResult
}
