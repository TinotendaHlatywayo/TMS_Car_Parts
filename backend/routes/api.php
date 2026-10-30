<?php

use App\Http\Controllers\Api\BranchRoleController;
use App\Http\Controllers\Api\BranchUserController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PlatformAccessController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductConversionController;
use App\Http\Controllers\Api\ProductUnitController;
use App\Http\Controllers\Api\StockLevelController;
use App\Http\Controllers\Api\StockMovementController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [HealthController::class, 'index']);

Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::get('/user', MeController::class);

    Route::prefix('branches/{branch}')
        ->middleware('branch.context')
        ->scopeBindings()
        ->group(function () {
            Route::get('/users', [BranchUserController::class, 'index'])
                ->middleware('branch.permission:branch.users.view');
            Route::post('/users', [BranchUserController::class, 'store'])
                ->middleware('branch.permission:branch.users.manage');
            Route::patch('/users/{membership}', [BranchUserController::class, 'update'])
                ->middleware('branch.permission:branch.users.manage');
            Route::delete('/users/{membership}', [BranchUserController::class, 'destroy'])
                ->middleware('branch.permission:branch.users.manage');
            Route::get('/roles', [BranchRoleController::class, 'index'])
                ->middleware('branch.permission:branch.roles.view');

            Route::get('/categories', [CategoryController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/categories', [CategoryController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/categories/{category}', [CategoryController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::get('/units', [UnitController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/units', [UnitController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/units/{unit}', [UnitController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/units/{unit}', [UnitController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::get('/products', [ProductController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/products', [ProductController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::get('/products/{product}', [ProductController::class, 'show'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::patch('/products/{product}', [ProductController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::post('/products/{product}/units', [ProductUnitController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::patch('/products/{product}/units/{productUnit}', [ProductUnitController::class, 'update'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::delete('/products/{product}/units/{productUnit}', [ProductUnitController::class, 'destroy'])
                ->middleware('branch.permission:branch.inventory.manage');

            Route::post('/products/{product}/convert', ProductConversionController::class)
                ->middleware('branch.permission:branch.inventory.view');

            Route::get('/customers', [CustomerController::class, 'index'])
                ->middleware('branch.permission:branch.customers.view');
            Route::post('/customers', [CustomerController::class, 'store'])
                ->middleware('branch.permission:branch.customers.manage');
            Route::get('/customers/{customer}', [CustomerController::class, 'show'])
                ->middleware('branch.permission:branch.customers.view');
            Route::patch('/customers/{customer}', [CustomerController::class, 'update'])
                ->middleware('branch.permission:branch.customers.manage');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
                ->middleware('branch.permission:branch.customers.manage');

            Route::get('/stock/levels', StockLevelController::class)
                ->middleware('branch.permission:branch.inventory.view');
            Route::get('/stock/movements', [StockMovementController::class, 'index'])
                ->middleware('branch.permission:branch.inventory.view');
            Route::post('/products/{product}/stock/movements', [StockMovementController::class, 'store'])
                ->middleware('branch.permission:branch.inventory.manage');
            Route::post('/stock/movements/{stockMovement}/reverse', [StockMovementController::class, 'reverse'])
                ->middleware('branch.permission:branch.inventory.manage');
        });

    Route::get('/platform/access', PlatformAccessController::class)
        ->middleware('platform.permission:platform.users.view');
});
