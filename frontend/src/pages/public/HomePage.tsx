import { PublicShell } from "@/components/layout/PublicShell"
import { Button } from "@/components/ui/button"

export default function HomePage() {
  return (
    <PublicShell>
      <section className="space-y-6 max-w-2xl">
        <h1 className="text-4xl font-bold tracking-tight">TorqueERP</h1>
        <p className="text-lg text-muted-foreground">
          Multi-branch ERP, POS, inventory, finance, and more for Taziva Motor Spares (TMS), Rusape, Zimbabwe.
        </p>
        <div className="flex gap-4">
          <Button onClick={() => window.location.href = '/login'}>Sign in</Button>
        </div>
      </section>
    </PublicShell>
  )
}
