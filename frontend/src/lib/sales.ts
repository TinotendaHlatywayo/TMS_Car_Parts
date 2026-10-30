export type SalePayment = {
  id: string
  method: string
  amount_minor: number
  currency_code: string
  reference: string | null
  status: string
  created_at: string | null
}

export type SaleItem = {
  id: string
  product_id: string
  product_name: string
  sku: string
  unit_id: string
  unit_code: string
  quantity: string
  unit_price_minor: number
  discount_minor: number
  tax_minor: number
  total_minor: number
  currency_code: string
}

export type SaleDetail = {
  id: string
  sale_number: string
  status: string
  payment_status: string
  currency_code: string
  subtotal_minor: number
  discount_minor: number
  tax_minor: number
  total_minor: number
  paid_minor: number
  customer: { id: string; name: string } | null
  items: SaleItem[]
  payments: SalePayment[]
  receipt: { id: string; receipt_number: string; issued_at: string | null } | null
  created_at: string | null
}
