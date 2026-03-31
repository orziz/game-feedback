declare namespace API.Admin.Game {
  interface API {
    get: {
      list: API.Meta.Get<void, AdminGameListResponse>
      entrypoints: API.Meta.Get<void, AdminGameEntrypointsResponse>
    }
    post: {
      create: API.Meta.Post<HttpParams.Create, ApiResponseBase>
      updateStatus: API.Meta.Post<HttpParams.UpdateStatus, ApiResponseBase>
      updateEntry: API.Meta.Post<HttpParams.UpdateEntry, ApiResponseBase>
    }
  }

  namespace HttpParams {
    interface Create {
      gameKey: string
      gameName: string
      entryPath: string
    }

    interface UpdateStatus {
      gameKey: string
      enabled: boolean
    }

    interface UpdateEntry {
      gameKey: string
      entryPath: string
    }
  }
}

interface AdminGame {
  id: number
  game_key: string
  game_name: string
  entry_path: string
  is_enabled: 0 | 1
  created_at: string
  updated_at: string
}

interface AdminGameEntrypoint {
  gameKey: string
  entryPath: string
  playerUrlExample: string
}

interface AdminGameListResponse extends ApiResponseBase {
  games: AdminGame[]
}

interface AdminGameEntrypointsResponse extends ApiResponseBase {
  entrypoints: AdminGameEntrypoint[]
}
