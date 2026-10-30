# Schema conventions

## IDs
- Use ULID for primary keys on domain entities where time-sortable IDs are useful (platforms, branches, branch_users, audit_logs, document_sequences). UUIDv4 acceptable where not needed.
- Foreign keys use matching types (foreignUlid/foreignId as appropriate).

## Timestamps
- All timestamps stored in UTC.
- Use `timestamp('created_at')->useCurrent()` and `timestamp('updated_at')->useCurrent()->useCurrentOnUpdate()`.
- Business dates (shifts/periods) must be explicit (date + timezone context).

## Soft deletes / archive
- Use `softDeletes()` for entities that must not be physically deleted due to history (branches, users membership context). Never delete employees with payroll/leave/disciplinary/audit history - deactivate/archive.
- Posted financial/stock records are never deleted; use reversals/adjustments/voids/credit notes.

## Constraints
- Foreign keys with appropriate cascade/restrict: prefer `cascadeOnUpdate()` and `restrictOnDelete()` or `nullOnDelete()` based on business rules.
- Unique constraints enforced at DB level.
- Composite uniques where needed (e.g., branch_users unique branch+user, document_sequences unique branch+type).

## Transactions & idempotency
- Multi-step operations must use DB transactions.
- Server-side idempotency keys required for retried create/payment/sync operations.
- Concurrency control via row locks where allocating sequences/counters.

## Document numbering
- Per (branch_id, document_type) sequences. Allocate atomically under lock/transaction. Never reuse posted numbers.

## Money
- Store amounts in integer minor units. Store `currency_code` (ISO 4217). FX fields when converting.

## Branch-owned catalogue (Stage 1.2)
- Catalogue tables (`categories`, `units`, `products`, `product_units`, `stock_levels`) carry `branch_id` and composite FKs such as `(product_id, branch_id)` to prevent cross-branch references.
- Add a `unique(['id', 'branch_id'])` key on parent tables used by composite child FKs.
- Scope business uniques to the branch, e.g. `(branch_id, sku)`, `(branch_id, barcode)`, `(branch_id, code)` and `(product_id, unit_id)`.
- Archive catalogue rows with `is_active=false`; do not hard-delete referenced rows.
- Conversion factors use `decimal(18,6)`; stock quantities/reorder levels use `decimal(18,4)`.
- Product prices use `unsignedBigInteger` minor units plus a 3-character `currency_code`.
