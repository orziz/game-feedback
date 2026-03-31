declare namespace API.Admin.Ticket {
  interface API {
    get: {
      list: API.Meta.Get<HttpParams.List, AdminListResponse>
      detail: API.Meta.Get<HttpParams.Detail, AdminDetailResponse>
      getOperations: API.Meta.Get<HttpParams.GetOperations, AdminOperationsResponse>
    }
    getBlob: {
      attachmentDownload: API.Meta.GetBlob<HttpParams.AttachmentDownload>
    }
    post: {
      update: API.Meta.Post<HttpParams.Update, ApiResponseBase>
      assign: API.Meta.Post<HttpParams.Assign, ApiResponseBase>
    }
  }

  namespace HttpParams {
    interface List {
      page?: number
      pageSize?: number
      status?: TicketStatus
      type?: FeedbackType
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

    interface GetOperations {
      ticketNo: string
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
  attachment_name?: string | null
  attachment_storage?: string | null
  attachment_mime?: string | null
  attachment_size?: number | null
  created_at: string
  updated_at: string
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

interface AdminDetailResponse extends ApiResponseBase {
  ticket: TicketRecord
  operations?: TicketOperation[]
}

interface AdminOperationsResponse extends ApiResponseBase {
  operations: TicketOperation[]
}
