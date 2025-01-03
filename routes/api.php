<?php

use App\Http\Controllers\FamilyTreeStorageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'web'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/family-trees', [FamilyTreeStorageController::class, 'index']);
    Route::post('/store-family-tree', [FamilyTreeStorageController::class, 'store']);
});
