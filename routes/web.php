<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\FamilyTreeStorageController;

// Redirect root to family tree if authenticated, otherwise to login
Route::get('/', function () {
    return auth()->check() 
        ? redirect('/family-tree')
        : redirect('/login');
});

// Authentication routes
Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');
    
    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

// Protected routes
Route::middleware('auth')->group(function () {
    Route::get('/family-tree', function () {
        return Inertia::render('FamilyTree');
    })->name('family-tree');
    
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/family-tree', function () {
        return Inertia::render('FamilyTree');
    })->name('family-tree');

    Route::post('/api/store-family-tree', [FamilyTreeStorageController::class, 'store'])
        ->name('family-tree.store');
    Route::get('/api/family-trees', [FamilyTreeStorageController::class, 'index'])
        ->name('family-tree.index');
});
