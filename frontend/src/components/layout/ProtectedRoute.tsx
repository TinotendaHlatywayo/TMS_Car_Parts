import { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'
import { LoadingState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const { data, isLoading, isError } = useMe()

  if (isLoading) {
    return <LoadingState text="Checking your access..." />
  }

  if (isError || !data) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
