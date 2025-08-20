<?php

namespace App\Providers;

use App\Models\Column;
use App\Models\Item;
use App\Policies\ColumnPolicy;
use App\Policies\ItemPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Column::class => ColumnPolicy::class,
        Item::class => ItemPolicy::class, // Adicione a sua policy aqui
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        //
    }
}
