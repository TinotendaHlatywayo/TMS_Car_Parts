import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { AppShell } from '@/components/layout/AppShell'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { EmptyState, ErrorState, LoadingState, PermissionDeniedState } from '@/components/feedback/StateComponents'
import { useMe } from '@/hooks/useMe'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { PaginatedProducts, Product, ProductUnit } from '@/lib/catalogue'
import { calculateCart, type CartLineInput, type PosProductOption } from '@/lib/pricing'

type CartLine = CartLineInput & { key: string }

const emptyLine = (): CartLine => ({
  key: crypto.randomUUID(),
  productId: '',
  unitId: '',
  quantity: '1',
  discountPercent: 0,
  floorPriceMinor: 0,
  taxRatePercent: 0,
})

export default function PosPage() {
  const meQuery = useMe()
  const branch = meQuery.data?.branches[0]
  const branchId = branch?.id
  const canView = branch?.permissions.includes('branch.inventory.view') ?? false
  const canSell = branch?.permissions.includes('branch.sales.create') ?? false

  const [lines, setLines] = useState<CartLine[]>([emptyLine()])
  const [taxRate, setTaxRate] = useState('0')

  const productsQuery = useQuery({
    queryKey: ['pos-products', branchId],
    queryFn: async () => {
      const response = await api.get<PaginatedProducts>(`/api/branches/${branchId}/products`, {
        params: { status: 'active', per_page: 100 },
      })
      return response.data.data
    },
    enabled: Boolean(branchId && canView),
  })

  const productOptions: PosProductOption[] = (productsQuery.data ?? []).map((product: Product) => ({
    id: product.id,
    sku: product.sku,
    name: product.name,
    currency_code: product.currency_code,
    units: [
      ...(product.base_unit
        ? [{ id: product.base_unit.id, code: product.base_unit.code, name: product.base_unit.name, priceMinor: product.selling_price_minor }]
        : []),
      ...(product.alternate_units ?? [])
        .filter((unit: ProductUnit) => unit.is_active && unit.unit)
        .map((unit: ProductUnit) => ({
          id: unit.unit!.id,
          code: unit.unit!.code,
          name: unit.unit!.name,
          priceMinor: unit.selling_price_minor,
        })),
    ],
  }))

  const normalizedLines: CartLineInput[] = lines.map((line) => ({
    ...line,
    taxRatePercent: Number(taxRate || 0),
  }))

  const cart = calculateCart(normalizedLines, productOptions)

  const updateLine = (key: string, patch: Partial<CartLine>) => {
    setLines((current) => current.map((line) => (line.key === key ? { ...line, ...patch } : line)))
  }

  const addLine = () => setLines((current) => [...current, emptyLine()])
  const removeLine = (key: string) => setLines((current) => current.filter((line) => line.key !== key))

  const handleUnitChange = (line: CartLine, unitId: string) => {
    updateLine(line.key, { unitId })
  }

  if (!meQuery.data) {
    return (
      <AppShell>
        <LoadingState text="Loading branch access..." />
      </AppShell>
    )
  }

  if (!branch) {
    return (
      <AppShell>
        <PermissionDeniedState
          title="No branch membership"
          description="Your account is active but is not assigned to a branch. Ask a platform administrator for access."
        />
      </AppShell>
    )
  }

  if (!canView) {
    return (
      <AppShell>
        <PermissionDeniedState description="Your role does not include permission to view inventory." />
      </AppShell>
    )
  }

  return (
    <AppShell>
      <div className="space-y-6">
        <div>
          <h1 className="text-3xl font-bold tracking-tight">POS cart</h1>
          <p className="text-muted-foreground">
            {branch.name} ({branch.code}) — price calculation preview
          </p>
        </div>

        {productsQuery.isError && (
          <ErrorState
            title="Products could not be loaded"
            description={apiErrorMessage(productsQuery.error)}
            retry={() => void productsQuery.refetch()}
          />
        )}

        <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
          <Card>
            <CardHeader>
              <CardTitle className="text-base">Cart lines</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {lines.map((line) => {
                const product = productOptions.find((item) => item.id === line.productId)
                return (
                  <div key={line.key} className="grid gap-3 rounded-md border p-3 md:grid-cols-6">
                    <div className="md:col-span-2 space-y-2">
                      <Label>Product</Label>
                      <select
                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={line.productId}
                        onChange={(event) => updateLine(line.key, { productId: event.target.value, unitId: '' })}
                      >
                        <option value="">Select product</option>
                        {productOptions.map((option) => (
                          <option key={option.id} value={option.id}>
                            {option.sku} — {option.name}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="space-y-2">
                      <Label>Unit</Label>
                      <select
                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={line.unitId}
                        onChange={(event) => handleUnitChange(line, event.target.value)}
                        disabled={!product}
                      >
                        <option value="">Unit</option>
                        {product?.units.map((unit) => (
                          <option key={unit.id} value={unit.id}>
                            {unit.code}
                          </option>
                        ))}
                      </select>
                    </div>
                    <div className="space-y-2">
                      <Label>Qty</Label>
                      <Input
                        value={line.quantity}
                        onChange={(event) => updateLine(line.key, { quantity: event.target.value })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label>Discount %</Label>
                      <Input
                        type="number"
                        value={line.discountPercent}
                        onChange={(event) => updateLine(line.key, { discountPercent: Number(event.target.value || 0) })}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label>Floor</Label>
                      <Input
                        type="number"
                        value={line.floorPriceMinor}
                        onChange={(event) => updateLine(line.key, { floorPriceMinor: Number(event.target.value || 0) })}
                      />
                    </div>
                    <div className="flex items-end justify-end">
                      <Button type="button" variant="ghost" size="sm" onClick={() => removeLine(line.key)}>
                        Remove
                      </Button>
                    </div>
                  </div>
                )
              })}
              <div className="flex gap-2">
                <Button type="button" variant="outline" onClick={addLine}>
                  Add line
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Totals</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
              <div className="flex justify-between">
                <span>Subtotal</span>
                <span>{cart.subtotalMinor}</span>
              </div>
              <div className="flex justify-between">
                <span>Discount</span>
                <span>{cart.discountMinor}</span>
              </div>
              <div className="flex justify-between">
                <span>Net</span>
                <span>{cart.netMinor}</span>
              </div>
              <div className="space-y-2">
                <Label htmlFor="tax-rate">Tax rate %</Label>
                <Input id="tax-rate" type="number" value={taxRate} onChange={(event) => setTaxRate(event.target.value)} />
              </div>
              <div className="flex justify-between">
                <span>Tax</span>
                <span>{cart.taxMinor}</span>
              </div>
              <div className="flex justify-between font-semibold">
                <span>Total</span>
                <span>{cart.totalMinor}</span>
              </div>
              {cart.requiresApproval && (
                <div className="rounded-md border border-destructive/30 bg-destructive/10 p-3 text-destructive">
                  Approval required: {cart.lines.flatMap((line) => line.approvalReasons).join(', ')}
                </div>
              )}
              {!canSell && (
                <p className="text-muted-foreground">Your role can preview pricing but cannot create sales.</p>
              )}
            </CardContent>
          </Card>
        </div>

        {productsQuery.data && productsQuery.data.length === 0 && (
          <EmptyState title="No products found" description="Create products before using the POS cart." />
        )}
      </div>
    </AppShell>
  )
}
