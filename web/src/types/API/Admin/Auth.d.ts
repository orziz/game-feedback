declare namespace API.Admin.Auth {
  /** 后台认证相关接口。 */
  interface API {
    get: {
      /** 获取当前登录管理员信息。 */
      currentUser: API.Meta.Get<void, AdminCurrentUserResponse>
    }
    post: {
      /** 管理员登录。 */
      login: API.Meta.Post<HttpParams.Login, AdminLoginResponse>
    }
  }

  namespace HttpParams {
    /** 登录请求体。 */
    interface Login {
      /** 管理员用户名。 */
      username: string
      /** 管理员密码。 */
      password: string
    }
  }
}

/** 管理员登录响应。 */
interface AdminLoginResponse extends ApiResponseBase {
  /** 登录后的 token。 */
  token: string
  /** 当前登录用户。 */
  user: AdminUser
}

/** 当前管理员信息响应。 */
interface AdminCurrentUserResponse extends ApiResponseBase {
  /** 当前登录用户。 */
  user: AdminUser
}
