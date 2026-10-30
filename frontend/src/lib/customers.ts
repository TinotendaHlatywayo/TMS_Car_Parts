export type Customer = {
  id: string
  name: string
  phone: string | null
  email: string | null
  address: string | null
  notes: string | null
  is_active: boolean
  created_at: string | null
  updated_at: string | null
}

export type CustomerListResponse = {
  data: Customer[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

export type CustomerMutationResponse = {
  data: Customer
  duplicate_warning: boolean
  duplicate_candidates: Customer[]
}
