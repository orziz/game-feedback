declare namespace API.System.Setup {
  /** 系统初始化安装相关接口。 */
  interface API {
    get: {
      /** 获取安装页所需的枚举选项。 */
      enumOptions: API.Meta.Get<HttpParams.EnumOptions, EnumOptionsResponse>
    }
    post: {
      /** 提交系统安装配置。 */
      install: API.Meta.Post<HttpParams.Install, ApiResponseBase>
    }
  }

  namespace HttpParams {
    /** 枚举选项查询参数。 */
    interface EnumOptions {
      /** 指定返回文案的语言。 */
      lang?: LocaleCode
    }

    /** 安装接口请求体，直接复用安装表单结构。 */
    type Install = InstallForm
  }
}

/** 安装流程表单结构。 */
interface InstallForm {
  /** 数据库主机。 */
  host: string
  /** 数据库端口。 */
  port: number
  /** 数据库名。 */
  database: string
  /** 数据库用户名。 */
  username: string
  /** 数据库密码。 */
  password: string
  /** 初始管理员用户名。 */
  adminUsername: string
  /** 初始管理员密码。 */
  adminPassword: string
  /** 上传方式。 */
  uploadMode: UploadMode
  /** 七牛 AccessKey。 */
  qiniuAccessKey: string
  /** 七牛 SecretKey。 */
  qiniuSecretKey: string
  /** 七牛 Bucket。 */
  qiniuBucket: string
  /** 七牛访问域名。 */
  qiniuDomain: string
}

/** 枚举选项结构。 */
interface EnumOption<T extends number = number> {
  /** 展示文案。 */
  label: string
  /** 枚举值。 */
  value: T
}

/** 系统安装页所需枚举响应。 */
interface EnumOptionsResponse extends ApiResponseBase {
  /** 反馈类型选项。 */
  types: EnumOption<FeedbackType>[]
  /** 严重级别选项。 */
  severities: EnumOption<Severity>[]
  /** 工单状态选项。 */
  statuses: EnumOption<TicketStatus>[]
}
