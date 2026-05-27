<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use App\Http\Controllers\BoardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\SubtaskController;
use App\Http\Controllers\BacklogController;
use App\Http\Controllers\CompletedController;
use App\Http\Controllers\ColumnController;
use App\Http\Controllers\Admin\ColumnManagementController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\BotConfigController;
use App\Http\Controllers\Admin\IcarusController;
use App\Http\Controllers\Admin\GoogleOAuthController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\RegistrationSettingsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ItemBlockController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        // canRegister respeita o toggle do admin (SystemSetting). Quando false,
        // a Welcome esconde os botões "Registrar" / "Começar Agora".
        'canRegister' => \App\Models\SystemSetting::getBool('registration_enabled', false),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});



Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/board', [BoardController::class, 'show'])->name('board');
    Route::patch('/board/reorder', [BoardController::class, 'reorder'])->name('board.reorder');
    Route::post('/items', [ItemController::class, 'store'])->name('items.store');
    Route::put('/items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::post('/subtasks', [SubtaskController::class, 'store'])->name('subtasks.store');
    Route::patch('/subtasks/{item}', [SubtaskController::class, 'update'])->name('subtasks.update');
    Route::get('/backlog', [BacklogController::class, 'index'])->name('backlog.index');
    Route::get('/completed', [CompletedController::class, 'index'])->name('completed.index');
    Route::post('/columns', [ColumnController::class, 'store'])->name('columns.store');
    Route::put('/columns/{column}', [ColumnController::class, 'update'])->name('columns.update');
    Route::delete('/columns/{column}', [ColumnController::class, 'destroy'])->name('columns.destroy');
    Route::patch('/columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');
    Route::get('/admin/columns', [ColumnManagementController::class, 'index'])->name('admin.columns.index');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::post('/items/{item}/block', [ItemBlockController::class, 'block'])->name('items.block');
    Route::post('/items/{item}/unblock', [ItemBlockController::class, 'unblock'])->name('items.unblock');
    Route::resource('projects', \App\Http\Controllers\ProjectController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('time-entries', \App\Http\Controllers\TimeEntryController::class)->only(['index', 'store', 'update', 'destroy']);
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/project/{project}', [ReportController::class, 'project'])->name('reports.project');
    Route::get('reports/collaborator/{user}', [ReportController::class, 'collaborator'])->name('reports.collaborator');
    Route::post('reports/collaborator/{user}/chat', [IcarusController::class, 'chat'])
        ->middleware('throttle:10,1')->name('icarus.chat');
    Route::get('bot-config', [BotConfigController::class, 'index'])->name('bot-config.index');
    Route::put('bot-config', [BotConfigController::class, 'update'])->name('bot-config.update');
    Route::post('bot-config/test', [BotConfigController::class, 'test'])->name('bot-config.test');

    Route::get('google/oauth/connect', [GoogleOAuthController::class, 'connect'])->name('google.connect');
    Route::get('google/oauth/callback', [GoogleOAuthController::class, 'callback'])->name('google.callback');
    Route::post('google/oauth/disconnect', [GoogleOAuthController::class, 'disconnect'])->name('google.disconnect');

    // CRUD de usuários + toggle de cadastro público.
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::post('users/{id}/restore', [AdminUserController::class, 'restore'])->name('users.restore');
    Route::post('users/{user}/reset-password', [AdminUserController::class, 'resetPassword'])->name('users.reset-password');
    Route::post('settings/registration-toggle', [RegistrationSettingsController::class, 'toggle'])->name('settings.registration-toggle');
});

require __DIR__.'/auth.php';
