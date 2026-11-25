<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;
use App\Models\TokenAccesoPersonal;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(TokenAccesoPersonal::class);

        try {
            $admin = \App\Models\Usuario::where('correo', 'nourish.app.services@gmail.com')->first();
            if ($admin && $admin->rol !== 'admin') {
                $admin->rol = 'admin';
                $admin->tipo_paciente = null;
                $admin->save();
            }
        } catch (\Throwable $e) {
            //
        }
    }
}
