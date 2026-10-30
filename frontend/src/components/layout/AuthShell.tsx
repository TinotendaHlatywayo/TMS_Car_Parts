import { ReactNode } from "react"

export function AuthShell({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-background flex items-center justify-center px-4">
      <div className="w-full max-w-md">
        <div className="text-center mb-8">
          <h1 className="text-2xl font-semibold">TorqueERP</h1>
        </div>
        {children}
      </div>
    </div>
  )
}
