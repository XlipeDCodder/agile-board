<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Column;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;



class ColumnManagementController extends Controller
{
    public function index(): Response
    {
        // Protege a página inteira. Apenas admins podem aceder.
        $this->authorize('create', Column::class);

        return Inertia::render('Admin/Columns/Index', [
            'columns' => Column::orderBy('order')->get(),
        ]);
    }
}
