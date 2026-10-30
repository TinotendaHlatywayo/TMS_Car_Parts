<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaleReturnService
{
    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Branch $branch, Sale $sale, User $actor, array $payload, string $idempotencyKey): SaleReturn
    {
        if ($sale->branch_id !== $branch->id) {
            abort(404);
        }

        if ($sale->status !== 'posted') {
            throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('Returns can only be requested for posted sales.');
        }

        $idempotencyKey = trim($idempotencyKey);

        if ($idempotencyKey === '' || strlen($idempotencyKey) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'A non-empty Idempotency-Key header of at most 100 characters is required.',
            ]);
        }

        $requestHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));

        $existing = SaleReturn::where('branch_id', $branch->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            if (! hash_equals($existing->request_hash, $requestHash)) {
                throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('The Idempotency-Key has already been used with a different return payload.');
            }

            return $existing->load('items');
        }

        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A return reason is required.',
            ]);
        }

        $items = $payload['items'] ?? [];

        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'items' => 'At least one return item is required.',
            ]);
        }

        return DB::transaction(function () use ($branch, $sale, $actor, $items, $reason, $idempotencyKey, $requestHash): SaleReturn {
            $existing = SaleReturn::where('branch_id', $branch->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if (! hash_equals($existing->request_hash, $requestHash)) {
                    throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException('The Idempotency-Key has already been used with a different return payload.');
                }

                return $existing->load('items');
            }

            $returnedQuantities = [];

            foreach ($sale->returns as $return) {
                foreach ($return->items as $returnItem) {
                    $returnedQuantities[$returnItem->sale_item_id] = bcadd(
                        (string) ($returnedQuantities[$returnItem->sale_item_id] ?? '0'),
                        (string) $returnItem->quantity,
                        4,
                    );
                }
            }

            $totalMinor = 0;
            $preparedItems = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $saleItem = SaleItem::where('branch_id', $branch->id)
                    ->where('sale_id', $sale->id)
                    ->whereKey($item['sale_item_id'] ?? null)
                    ->first();

                if (! $saleItem) {
                    throw ValidationException::withMessages([
                        'items' => 'A return item must refer to a sale item in this sale.',
                    ]);
                }

                $quantity = (string) ($item['quantity'] ?? '0');

                if (bccomp($quantity, '0', 4) <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity must be greater than zero.',
                    ]);
                }

                $alreadyReturned = (string) ($returnedQuantities[$saleItem->id] ?? '0');

                if (bccomp(bcadd($alreadyReturned, $quantity, 4), (string) $saleItem->quantity, 4) > 0) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity exceeds the quantity sold.',
                    ]);
                }

                $amountMinor = (int) round(((float) $saleItem->total_minor / (float) $saleItem->quantity) * (float) $quantity);

                $preparedItems[] = [
                    'sale_item' => $saleItem,
                    'quantity' => $quantity,
                    'amount_minor' => $amountMinor,
                ];

                $totalMinor += $amountMinor;
            }

            if ($preparedItems === []) {
                throw ValidationException::withMessages([
                    'items' => 'At least one valid return item is required.',
                ]);
            }

            $returnNumber = $this->documentNumbers->allocate($branch, 'return');

            $saleReturn = SaleReturn::create([
                'branch_id' => $branch->id,
                'sale_id' => $sale->id,
                'return_number' => $returnNumber,
                'status' => 'requested',
                'restock_decision' => 'pending',
                'refund_status' => 'none',
                'reason' => $reason,
                'currency_code' => $sale->currency_code,
                'total_minor' => $totalMinor,
                'idempotency_key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'authorized_by' => $actor->id,
            ]);

            foreach ($preparedItems as $prepared) {
                SaleReturnItem::create([
                    'branch_id' => $branch->id,
                    'sale_return_id' => $saleReturn->id,
                    'sale_item_id' => $prepared['sale_item']->id,
                    'product_id' => $prepared['sale_item']->product_id,
                    'quantity' => $prepared['quantity'],
                    'amount_minor' => $prepared['amount_minor'],
                    'currency_code' => $sale->currency_code,
                ]);
            }

            $this->audit->log(
                'sale.return.requested',
                $saleReturn,
                null,
                [
                    'sale_number' => $sale->sale_number,
                    'return_number' => $returnNumber,
                    'total_minor' => $totalMinor,
                    'reason' => $reason,
                ],
                $branch->id,
                $actor,
            );

            return $saleReturn->load('items');
        }, 3);
    }
}
