<?php

use App\Http\Controllers\MemberController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| IronDesk API Routes
|--------------------------------------------------------------------------
|
| All routes are prefixed with /api (configured in bootstrap/app.php).
| Auth middleware will be layered on top of these groups once the Auth
| module is implemented. For now, routes are open (owner mode).
|
*/

Route::prefix('gyms/{gymId}')->group(function (): void {

    // ── Members ──────────────────────────────────────────────────────────────
    // Export MUST be declared before {id} to avoid Laravel treating "export"
    // as an integer member ID.
    Route::get('members/export', [MemberController::class, 'export']);

    Route::get('members',            [MemberController::class, 'index']);
    Route::post('members',           [MemberController::class, 'store']);
    Route::get('members/{id}',       [MemberController::class, 'show']);
    Route::patch('members/{id}',     [MemberController::class, 'update']);
    Route::post('members/{id}/deactivate', [MemberController::class, 'deactivate']);

});
