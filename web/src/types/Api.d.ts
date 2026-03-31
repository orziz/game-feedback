type ApiMethodKey = 'get' | 'post' | 'postForm' | 'getBlob'

/**
 * 前端 API 客户端的总入口类型。
 * 这里定义 `api.xxx.yyy` 的最终可用结构，便于统一查找各模块挂载位置。
 */
interface ApiSchema {
  /** 系统状态与初始化安装相关接口。 */
  system: {
    Status: API.System.Status.API
    Setup: API.System.Setup.API
  }
  /** 面向普通用户的反馈提交与查询接口。 */
  feedback: {
    Ticket: API.Feedback.Ticket.API
  }
  /** 后台管理端接口。 */
  admin: {
    Auth: API.Admin.Auth.API
    Ticket: API.Admin.Ticket.API
    User: API.Admin.User.API
    Game: API.Admin.Game.API
  }
}
