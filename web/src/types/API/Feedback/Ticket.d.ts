declare namespace API.Feedback.Ticket {
  interface API {
    get: {
      search: API.Meta.Get<HttpParams.Search, TicketSearchResponse>
    }
    postForm: {
      submit: API.Meta.PostForm<HttpParams.Submit, SubmitResponse>
    }
  }

  namespace HttpParams {
    interface Search {
      gameKey: string
      keyword: string
      page?: number
      pageSize?: number
    }

    type Submit = FormData
  }
}

interface SubmitForm {
  gameKey?: string
  type: FeedbackType
  severity: Severity
  reproduceSteps?: string
  title: string
  description: string
  contact: string
  attachmentFile: File | null
}

interface PublicTicketRecord {
  game_key?: string
  ticket_no: string
  type: FeedbackType
  severity: Severity | null
  title: string
  details: string
  status: TicketStatus
  admin_note: string
  created_at: string
  updated_at: string
}

interface SubmitResponse extends ApiResponseBase {
  ticketNo: string
}

interface TicketSearchResponse extends ApiResponseBase {
  tickets: PublicTicketRecord[]
  pagination?: PaginationInfo
}
