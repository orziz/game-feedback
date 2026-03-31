declare namespace API.Admin.Ticket {
  interface API {
    get: {
      list: API.Meta.Get<HttpParams.List, AdminListResponse>
      detail: API.Meta.Get<HttpParams.Detail, AdminDetailResponse>
    }
    getBlob: {
      attachmentDownload: API.Meta.GetBlob<HttpParams.AttachmentDownload>
    }
    post: {
      update: API.Meta.Post<HttpParams.Update, ApiResponseBase>
    }
  }

  namespace HttpParams {
    interface List {
      page?: number
      pageSize?: number
      status?: TicketStatus
      type?: FeedbackType
      keyword?: string
      gameKey?: string
    }

    interface Detail {
      ticketNo: string
      gameKey?: string
    }

    interface AttachmentDownload {
      ticketNo: string
      gameKey?: string
    }

    interface Update {
      ticketNo: string
      status: TicketStatus
      severity: Severity | null
      adminNote: string
      gameKey?: string
    }
  }
}

interface TicketRecord {
  game_key?: string
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

interface AdminUpdateForm {
  status: TicketStatus
  severity: Severity | null
  adminNote: string
}

interface AdminListResponse extends ApiResponseBase {
  tickets: TicketRecord[]
  pagination?: PaginationInfo
}

interface AdminDetailResponse extends ApiResponseBase {
  ticket: TicketRecord
}
