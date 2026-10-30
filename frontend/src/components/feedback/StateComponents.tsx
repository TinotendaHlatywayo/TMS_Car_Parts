import { Button } from "@/components/ui/button"

export function LoadingState({ text = "Loading..." }: { text?: string }) {
  return (
    <div className="flex flex-col items-center justify-center p-8 space-y-4" role="status" aria-live="polite">
      <div className="animate-spin h-8 w-8 border-4 border-primary border-t-transparent rounded-full" aria-hidden="true" />
      <p className="text-sm text-muted-foreground">{text}</p>
    </div>
  )
}

export function Skeleton({ className = "" }: { className?: string }) {
  return <div className={`skeleton h-4 w-full ${className}`} aria-hidden="true" />
}

export function EmptyState({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center space-y-4">
      <h3 className="text-lg font-semibold">{title}</h3>
      {description && <p className="text-sm text-muted-foreground max-w-md">{description}</p>}
      {action}
    </div>
  )
}

export function ErrorState({ title = "Something went wrong", description, retry }: { title?: string; description?: string; retry?: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center space-y-4" role="alert">
      <h3 className="text-lg font-semibold text-destructive">{title}</h3>
      {description && <p className="text-sm text-muted-foreground max-w-md">{description}</p>}
      {retry && <Button onClick={retry}>Try again</Button>}
    </div>
  )
}

export function SuccessState({ title, description, action }: { title: string; description?: string; action?: React.ReactNode }) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center space-y-4">
      <h3 className="text-lg font-semibold text-green-600">{title}</h3>
      {description && <p className="text-sm text-muted-foreground max-w-md">{description}</p>}
      {action}
    </div>
  )
}

export function PermissionDeniedState({ title = "Access denied", description = "You don't have permission to access this resource." }: { title?: string; description?: string }) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center space-y-4" role="alert">
      <h3 className="text-lg font-semibold">{title}</h3>
      <p className="text-sm text-muted-foreground max-w-md">{description}</p>
    </div>
  )
}
