/**
 * 从未知错误中提取可读消息，无法提取时返回 fallback
 *
 * @param error - 捕获到的异常
 * @param fallback - 兜底提示文案
 * @returns 错误消息字符串
 */
export function getErrorMessage(error: unknown, fallback: string): string {
  return error instanceof Error && error.message ? error.message : fallback
}

/**
 * 将未知错误窄化为 ApiError，不符合结构时返回 null
 *
 * @param error - 捕获到的异常
 * @returns ApiError 或 null
 */
export function getApiError(error: unknown): ApiError | null {
  return error instanceof Error && 'code' in error ? (error as ApiError) : null
}
