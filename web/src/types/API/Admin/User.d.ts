declare namespace API.Admin.User {
  /** 后台管理员用户管理接口。 */
  interface API {
    get: {
      /** 获取管理员用户列表。 */
      list: API.Meta.Get<void, AdminUserListResponse>
    }
    post: {
      /** 创建管理员用户。 */
      create: API.Meta.Post<HttpParams.Create, ApiResponseBase>
      /** 删除管理员用户。 */
      delete: API.Meta.Post<HttpParams.Delete, ApiResponseBase>
      /** 重置管理员密码。 */
      resetPassword: API.Meta.Post<HttpParams.ResetPassword, ApiResponseBase>
    }
  }

  namespace HttpParams {
    /** 创建管理员请求体。 */
    interface Create {
      /** 登录用户名。 */
      username: string
      /** 初始密码。 */
      password: string
      /** 用户角色。 */
      role: string
    }

    /** 删除管理员请求体。 */
    interface Delete {
      /** 管理员 ID。 */
      id: number
    }

    /** 重置密码请求体。 */
    interface ResetPassword {
      /** 管理员 ID。 */
      id: number
      /** 新密码。 */
      password: string
    }
  }
}

/** 管理员用户结构。 */
interface AdminUser {
  /** 用户 ID。 */
  id: number
  /** 用户名。 */
  username: string
  /** 用户角色。 */
  role: AdminUserRole
  /** 创建时间。 */
  created_at: string
  /** 更新时间。 */
  updated_at: string
}

/** 管理员用户列表响应。 */
interface AdminUserListResponse extends ApiResponseBase {
  /** 用户列表。 */
  users: AdminUser[]
}
