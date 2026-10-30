import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import type { MeResponse } from '@/lib/access'

async function fetchMe(): Promise<MeResponse> {
  const response = await api.get<MeResponse>('/api/user')
  return response.data
}

export function useMe() {
  return useQuery({
    queryKey: ['me'],
    queryFn: fetchMe,
    retry: false,
    staleTime: 1000 * 30,
  })
}
