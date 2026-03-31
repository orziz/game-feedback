declare namespace API.System.Status {
  /** 系统运行状态相关接口。 */
  interface API {
    get: {
      /** 获取服务健康状态与服务端时间。 */
      health: API.Meta.Get<void, HealthResponse>
      /** 获取系统安装状态。 */
      installStatus: API.Meta.Get<void, InstallStatusResponse>
    }
  }
}

/** 系统健康检查响应。 */
interface HealthResponse extends ApiResponseBase {
  /** 当前系统是否已完成安装。 */
  installed: boolean
  /** 服务端返回的当前时间。 */
  time: string
}

/** 系统安装状态响应。 */
interface InstallStatusResponse extends ApiResponseBase {
  /** 是否已完成安装。 */
  installed: boolean
  /** 当前启用的上传模式。 */
  uploadMode?: UploadMode
  /** 当前上传大小上限（字节）。 */
  uploadMaxBytes?: number
  /** 当前系统版本（语义化版本号 x.y.z）。 */
  systemVersion?: string
}
