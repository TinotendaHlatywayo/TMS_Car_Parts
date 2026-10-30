import { FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import api from '@/lib/api'
import { apiErrorMessage } from '@/lib/apiErrors'
import type { Category, Product, Unit } from '@/lib/catalogue'

type Draft = {
  name: string
  sku: string
  barcode: string
  description: string
  category_id: string
  base_unit_id: string
  selling_price: string
  track_stock: boolean
  is_active: boolean
  reorder_level: string
}

function draftFrom(product: Product | null): Draft {
  return {
    name: product?.name ?? '',
    sku: product?.sku ?? '',
    barcode: product?.barcode ?? '',
    description: product?.description ?? '',
    category_id: product?.category?.id ?? '',
    base_unit_id: product?.base_unit?.id ?? '',
    selling_price: product?.selling_price ?? '0.00',
    track_stock: product?.track_stock ?? true,
    is_active: product?.is_active ?? true,
    reorder_level: product?.stock.reorder_level ?? '',
  }
}

export function ProductForm({
  branchId,
  product,
  categories,
  units,
  canManage,
  onSaved,
  onCancel,
}: {
  branchId: string
  product: Product | null
  categories: Category[]
  units: Unit[]
  canManage: boolean
  onSaved: (product: Product) => Promise<void>
  onCancel: () => void
}) {
  const [draft, setDraft] = useState<Draft>(() => draftFrom(product))
  const [feedback, setFeedback] = useState<string | null>(null)

  const categoryOptions = categories.filter((category) => category.is_active || category.id === draft.category_id)
  const unitOptions = units.filter((unit) => unit.is_active || unit.id === draft.base_unit_id)

  const saveMutation = useMutation({
    mutationFn: async () => {
      const payload = {
        name: draft.name.trim(),
        sku: draft.sku.trim(),
        barcode: draft.barcode.trim() || null,
        description: draft.description.trim() || null,
        category_id: draft.category_id || null,
        base_unit_id: draft.base_unit_id,
        selling_price: draft.selling_price.trim(),
        track_stock: draft.track_stock,
        is_active: draft.is_active,
        reorder_level: draft.reorder_level.trim() || null,
      }

      if (product) {
        const response = await api.patch(`/api/branches/${branchId}/products/${product.id}`, payload)
        return response.data.data as Product
      }

      const response = await api.post(`/api/branches/${branchId}/products`, payload)
      return response.data.data as Product
    },
    onSuccess: async (saved) => {
      setFeedback(product ? 'Product updated.' : 'Product created.')
      await onSaved(saved)
    },
    onError: (error) => setFeedback(apiErrorMessage(error)),
  })

  const submit = (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault()
    setFeedback(null)
    saveMutation.mutate()
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">
          {canManage ? (product ? `Edit ${product.name}` : 'Add product') : product?.name ?? 'Product details'}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {feedback && (
          <div role="status" className="mb-4 rounded-md border bg-muted p-3 text-sm">{feedback}</div>
        )}
        <form onSubmit={submit}>
          <fieldset disabled={!canManage} className="grid gap-4 md:grid-cols-2">
            <div className="space-y-2">
              <Label htmlFor="product-name">Name</Label>
              <Input
                id="product-name"
                value={draft.name}
                onChange={(event) => setDraft({ ...draft, name: event.target.value })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-sku">SKU</Label>
              <Input
                id="product-sku"
                value={draft.sku}
                onChange={(event) => setDraft({ ...draft, sku: event.target.value })}
                placeholder="OF-100"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-barcode">Barcode</Label>
              <Input
                id="product-barcode"
                value={draft.barcode}
                onChange={(event) => setDraft({ ...draft, barcode: event.target.value })}
                placeholder="Optional, unique in this branch"
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-category">Category</Label>
              <select
                id="product-category"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={draft.category_id}
                onChange={(event) => setDraft({ ...draft, category_id: event.target.value })}
              >
                <option value="">Uncategorised</option>
                {categoryOptions.map((category) => (
                  <option key={category.id} value={category.id}>
                    {category.name}{category.is_active ? '' : ' (archived)'}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-base-unit">Base unit</Label>
              <select
                id="product-base-unit"
                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                value={draft.base_unit_id}
                onChange={(event) => setDraft({ ...draft, base_unit_id: event.target.value })}
                required
              >
                <option value="">Select a unit</option>
                {unitOptions.map((unit) => (
                  <option key={unit.id} value={unit.id}>
                    {unit.code} — {unit.name}{unit.is_active ? '' : ' (archived)'}
                  </option>
                ))}
              </select>
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-price">Selling price</Label>
              <Input
                id="product-price"
                type="text"
                inputMode="decimal"
                value={draft.selling_price}
                onChange={(event) => setDraft({ ...draft, selling_price: event.target.value })}
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="product-reorder">Reorder level</Label>
              <Input
                id="product-reorder"
                type="text"
                inputMode="decimal"
                value={draft.reorder_level}
                onChange={(event) => setDraft({ ...draft, reorder_level: event.target.value })}
                placeholder="Optional, in base units"
              />
            </div>
            <div className="space-y-2 md:col-span-2">
              <Label htmlFor="product-description">Description</Label>
              <textarea
                id="product-description"
                className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                value={draft.description}
                onChange={(event) => setDraft({ ...draft, description: event.target.value })}
                placeholder="Optional"
              />
            </div>
            <div className="flex flex-wrap gap-4 text-sm">
              <label className="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={draft.track_stock}
                  onChange={(event) => setDraft({ ...draft, track_stock: event.target.checked })}
                />
                Track stock
              </label>
              <label className="inline-flex items-center gap-2">
                <input
                  type="checkbox"
                  checked={draft.is_active}
                  onChange={(event) => setDraft({ ...draft, is_active: event.target.checked })}
                />
                Active
              </label>
            </div>
            {canManage && unitOptions.length === 0 && (
              <p className="text-sm text-destructive md:col-span-2">
                Create an active unit of measure before adding products.
              </p>
            )}
          </fieldset>
          <div className="mt-4 flex items-center justify-end gap-2">
            {!canManage && <p className="mr-auto text-sm text-muted-foreground">Read-only catalogue view.</p>}
            <Button type="button" variant="outline" onClick={onCancel}>
              {canManage ? 'Cancel' : 'Close'}
            </Button>
            {canManage && (
              <Button type="submit" disabled={saveMutation.isPending || unitOptions.length === 0}>
                {saveMutation.isPending ? 'Saving...' : product ? 'Save product' : 'Create product'}
              </Button>
            )}
          </div>
        </form>
      </CardContent>
    </Card>
  )
}
