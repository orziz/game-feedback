export function getErrorMessage(error: unknown, fallback: string): string {
  return error instanceof Error && error.message ? error.message : fallback
}

export function getApiError(error: unknown): ApiError | null {
  return error instanceof Error && 'code' in error ? (error as ApiError) : null
}
