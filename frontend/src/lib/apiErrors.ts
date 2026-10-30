import axios from 'axios'

export function apiErrorMessage(error: unknown, fallback = 'Something went wrong.'): string {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data
    if (typeof data?.message === 'string' && data.message.length > 0) {
      return data.message
    }
    if (data?.errors && typeof data.errors === 'object') {
      const first = Object.values(data.errors as Record<string, unknown>)[0]
      if (Array.isArray(first) && typeof first[0] === 'string') {
        return first[0]
      }
      if (typeof first === 'string') {
        return first
      }
    }
  }

  if (error instanceof Error && error.message.length > 0) {
    return error.message
  }

  return fallback
}
