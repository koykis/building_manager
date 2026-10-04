<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StatementController;
use App\Http\Middleware\PrivateApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['application' => 'Building Manager API']));
Route::prefix('api/v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::middleware(['auth:sanctum', 'active', PrivateApiResponse::class])->group(function () {
        Route::get('reports/coverage', [ReportController::class, 'coverage']);
        Route::get('reports/building', [ReportController::class, 'building']);
        Route::get('reports/recurring', [ReportController::class, 'recurring']);
        Route::get('reports/my-apartment', [ReportController::class, 'apartment']);
        Route::get('auth/me', fn (Request $r) => $r->user());
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::prefix('admin')->middleware('can:admin')->group(function () {
            Route::get('statements', [StatementController::class, 'index']);
            Route::post('statements', [StatementController::class, 'store']);
            Route::get('statements/{statement}', [StatementController::class, 'show']);
            Route::put('statements/{statement}', [StatementController::class, 'update']);
            Route::delete('statements/{statement}', [StatementController::class, 'destroy']);
            Route::get('statements/{statement}/review', [StatementController::class, 'review']);
            Route::post('statements/{statement}/publish', [StatementController::class, 'publish']);
            Route::post('statements/{statement}/revise', [StatementController::class, 'revise']);
            Route::get('imports', [ImportController::class, 'index']);
            Route::post('imports', [ImportController::class, 'store']);
            Route::post('imports/{batch}/resume', [ImportController::class, 'resume']);
            Route::get('statements/{statement}/export', [ImportController::class, 'export']);
            Route::get('projects', [ProjectController::class, 'index']);
            Route::get('project-candidates', [ProjectController::class, 'candidates']);
            Route::post('projects', [ProjectController::class, 'save']);
            Route::put('projects/{project}', [ProjectController::class, 'save']);
            Route::get('projects/{project}/savings', [ProjectController::class, 'savings']);
            Route::get('references', [ReferenceController::class, 'index']);
            Route::post('apartments', [ReferenceController::class, 'saveApartment']);
            Route::put('apartments/{apartment}', [ReferenceController::class, 'saveApartment']);
            Route::post('categories', [ReferenceController::class, 'saveCategory']);
            Route::put('categories/{category}', [ReferenceController::class, 'saveCategory']);
            Route::get('users', [ReferenceController::class, 'users']);
            Route::post('users', [ReferenceController::class, 'saveUser']);
            Route::put('users/{user}', [ReferenceController::class, 'saveUser']);
            Route::post('statements/{statement}/documents', [DocumentController::class, 'store']);
            Route::get('documents/{document}', [DocumentController::class, 'show']);
        });
    });
});
