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
use App\Http\Controllers\CommentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
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
    Route::patch('/columns/reorder', [ColumnController::class, 'reorder'])->name('columns.reorder');
    Route::get('/admin/columns', [ColumnManagementController::class, 'index'])->name('admin.columns.index');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
});

require __DIR__.'/auth.php';
