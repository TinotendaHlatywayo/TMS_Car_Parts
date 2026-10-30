<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Models\Branch;
use App\Models\Unit;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UnitController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request, Branch $branch): JsonResponse
    {
        $status = in_array($request->query('status'), ['active', 'inactive', 'all'], true)
            ? $request->query('status')
            : 'active';

        $units = $branch->units()
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $units->map(fn (Unit $unit): array => $this->payload($unit)),
        ]);
    }

    public function store(StoreUnitRequest $request, Branch $branch): JsonResponse
    {
        $data = $request->validated();

        $unit = DB::transaction(function () use ($data, $branch, $request): Unit {
            $unit = Unit::create([
                'branch_id' => $branch->id,
                'code' => $data['code'],
                'name' => $data['name'],
                'symbol' => $data['symbol'] ?? null,
                'allow_decimal' => $data['allow_decimal'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->audit->log(
                'unit.created',
                $unit,
                null,
                $this->snapshot($unit),
                $branch->id,
                $request->user(),
            );

            return $unit;
        });

        return response()->json(['data' => $this->payload($unit)], 201);
    }

    public function update(UpdateUnitRequest $request, Branch $branch, Unit $unit): JsonResponse
    {
        $data = $request->validated();

        $unit = DB::transaction(function () use ($data, $branch, $unit, $request): Unit {
            $before = $this->snapshot($unit);

            $unit->update($data);
            $unit->refresh();

            $this->audit->log(
                'unit.updated',
                $unit,
                $before,
                $this->snapshot($unit),
                $branch->id,
                $request->user(),
            );

            return $unit;
        });

        return response()->json(['data' => $this->payload($unit)]);
    }

    public function destroy(Request $request, Branch $branch, Unit $unit): Response
    {
        DB::transaction(function () use ($branch, $unit, $request): void {
            $before = $this->snapshot($unit);

            $unit->update(['is_active' => false]);

            $this->audit->log(
                'unit.archived',
                $unit,
                $before,
                $this->snapshot($unit->refresh()),
                $branch->id,
                $request->user(),
            );
        });

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Unit $unit): array
    {
        return [
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'allow_decimal' => $unit->allow_decimal,
            'is_active' => $unit->is_active,
            'created_at' => $unit->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Unit $unit): array
    {
        return [
            'branch_id' => $unit->branch_id,
            'code' => $unit->code,
            'name' => $unit->name,
            'symbol' => $unit->symbol,
            'allow_decimal' => $unit->allow_decimal,
            'is_active' => $unit->is_active,
        ];
    }
}
