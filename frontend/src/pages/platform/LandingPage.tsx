import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card"

export default function PlatformLandingPage() {
  return (
    <div className="min-h-screen bg-background p-8">
      <div className="max-w-4xl mx-auto space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">Platform Console</h1>
          <p className="text-muted-foreground">TorqueERP Platform Administration</p>
        </div>
        <Card>
          <CardHeader>
            <CardTitle>Overview</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-sm text-muted-foreground">Platform-level management. Branch isolation enforced.</p>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
