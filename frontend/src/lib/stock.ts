export type StockLevel = {
  product: {
    id: string
    sku: string
    name: string
    track_stock: boolean
    is_active: boolean
    has_opening: boolean
    base_unit: Pick<UnitLike, 'id' | 'code' | 'name' | 'symbol'> | null
  } | null
  quantity_on_hand: string
  quantity_reserved: string
  reorder_level: string | null
  is_low: boolean
  updated_at: string | null
}

type UnitLike = {
  id: string
  code: string
  name: string
  symbol: string | null
}

export type StockLevelListResponse = {
  data: StockLevel[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type StockMovement = {
  id: string
  movement_type: 'opening' | 'sale' | 'correction' | 'reversal'
  source: string
  source_id: string | null
  quantity: string
  unit_quantity: string
  unit_conversion_factor: string
  balance_after: string
  reason: string
  product: {
    id: string
    sku: string
    name: string
    track_stock: boolean
    is_active: boolean
  } | null
  unit: {
    id: string
    code: string
    name: string
    symbol: string | null
    allow_decimal: boolean
  } | null
  actor: { id: number; name: string } | null
  reverses_movement_id: string | null
  is_reversed: boolean
  created_at: string | null
}

export type StockMovementListResponse = {
  data: StockMovement[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}
