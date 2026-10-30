export type PosUnitOption = {
  id: string
  code: string
  name: string
  priceMinor: number
}

export type PosProductOption = {
  id: string
  sku: string
  name: string
  currency_code: string
  units: PosUnitOption[]
}

export type CartLineInput = {
  productId: string
  unitId: string
  quantity: string
  discountPercent: number
  floorPriceMinor: number
  taxRatePercent: number
}

export type CartLineResult = {
  productId: string
  unitId: string
  quantity: string
  unitPriceMinor: number
  subtotalMinor: number
  discountMinor: number
  netMinor: number
  taxMinor: number
  totalMinor: number
  requiresApproval: boolean
  approvalReasons: string[]
}

export type CartResult = {
  lines: CartLineResult[]
  subtotalMinor: number
  discountMinor: number
  netMinor: number
  taxMinor: number
  totalMinor: number
  requiresApproval: boolean
}

function roundHalfUp(value: number): number {
  return Math.round(value)
}

export function calculateCart(lines: CartLineInput[], products: PosProductOption[]): CartResult {
  const results: CartLineResult[] = []
  let subtotalMinor = 0
  let discountMinor = 0
  let netMinor = 0
  let taxMinor = 0
  let totalMinor = 0
  let requiresApproval = false

  for (const line of lines) {
    const product = products.find((item) => item.id === line.productId)
    const unit = product?.units.find((item) => item.id === line.unitId)
    const unitPriceMinor = unit?.priceMinor ?? 0
    const quantity = Number(line.quantity || 0)

    const lineSubtotal = roundHalfUp(unitPriceMinor * quantity)
    const lineDiscount = roundHalfUp(lineSubtotal * (line.discountPercent / 100))
    const floorSubtotal = roundHalfUp(line.floorPriceMinor * quantity)
    let lineNet = lineSubtotal - lineDiscount
    const reasons: string[] = []

    if (line.discountPercent > 10 && line.discountPercent > 0) {
      reasons.push('discount_exceeds_allowed_threshold')
    }

    if (lineNet < floorSubtotal) {
      lineNet = floorSubtotal
      reasons.push('below_floor_price')
    }

    const lineTax = roundHalfUp(lineNet * (line.taxRatePercent / 100))
    const lineTotal = lineNet + lineTax

    const result: CartLineResult = {
      productId: line.productId,
      unitId: line.unitId,
      quantity: line.quantity,
      unitPriceMinor,
      subtotalMinor: lineSubtotal,
      discountMinor: lineDiscount,
      netMinor: lineNet,
      taxMinor: lineTax,
      totalMinor: lineTotal,
      requiresApproval: reasons.length > 0,
      approvalReasons: reasons,
    }

    results.push(result)
    subtotalMinor += lineSubtotal
    discountMinor += lineDiscount
    netMinor += lineNet
    taxMinor += lineTax
    totalMinor += lineTotal
    if (reasons.length > 0) requiresApproval = true
  }

  return {
    lines: results,
    subtotalMinor,
    discountMinor,
    netMinor,
    taxMinor,
    totalMinor,
    requiresApproval,
  }
}
