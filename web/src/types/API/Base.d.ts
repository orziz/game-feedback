declare namespace API {
  namespace Meta {
    /** GET 查询参数统一转换为 query string，因此只允许基础可序列化类型。 */
    type QueryParams = Record<string, string | number | boolean | undefined>

    /** GET 请求类型；当参数为 `void` 时调用端无需传参。 */
    type Get<Params = void, Response = ApiResponseBase> =
      [Params] extends [void] ? () => Promise<Response> : (params: Params) => Promise<Response>

    /** POST JSON 请求类型；当请求体为 `void` 时调用端无需传参。 */
    type Post<Body = void, Response = ApiResponseBase> =
      [Body] extends [void] ? () => Promise<Response> : (body: Body) => Promise<Response>

    /** POST FormData 请求类型，用于文件上传等表单场景。 */
    type PostForm<Body = FormData, Response = ApiResponseBase> = (body: Body) => Promise<Response>

    /** GET 二进制下载接口类型。 */
    type GetBlob<Params = void> =
      [Params] extends [void] ? () => Promise<Blob> : (params: Params) => Promise<Blob>
  }
}

/** 分页响应的公共结构。 */
interface PaginationInfo {
  /** 数据总量。 */
  total: number
}

/** 通用接口响应基类。 */
interface ApiResponseBase {
  /** 请求是否成功。 */
  ok: boolean
  /** 服务端业务错误码。 */
  code?: string
  /** 服务端返回的说明信息。 */
  message?: string
}

/** 统一错误对象结构，供 API 客户端和错误工具函数使用。 */
interface ApiError extends Error {
  /** 服务端或客户端归一化后的错误码。 */
  code: string
  /** 原始响应体。 */
  payload?: ApiResponseBase & Record<string, unknown>
}
