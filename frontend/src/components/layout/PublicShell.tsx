import { ReactNode } from "react"

export function PublicShell({ children }: { children: ReactNode }) {
  return (
    <div className="min-h-screen bg-background">
      <header className="border-b">
        <nav className="container mx-auto px-4 py-4 flex items-center justify-between" aria-label="Public navigation">
          <div className="text-lg font-semibold">TorqueERP</div>
          <div className="flex gap-4 text-sm">
            <a href="/" className="hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Home</a>
            <a href="/login" className="hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">Login</a>
          </div>
        </nav>
      </header>
      <main id="main-content" className="container mx-auto px-4 py-8" tabIndex={-1}>
        {children}
      </main>
      <footer className="border-t mt-auto">
        <div className="container mx-auto px-4 py-6 text-sm text-muted-foreground">
          © {new Date().getFullYear()} TorqueERP
        </div>
      </footer>
    </div>
  )
}
