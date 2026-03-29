<?php

use App\Http\Controllers\SimulatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SimulatorController::class, 'index']);
Route::post('/simulate', [SimulatorController::class, 'simulate']);
Route::get('/simulate/flow/{flowId}', [SimulatorController::class, 'flowSchema']);
