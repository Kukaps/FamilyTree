<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FamilyTreeController;

Route::post('/family-tree', [FamilyTreeController::class, 'getFamilyTree']);
