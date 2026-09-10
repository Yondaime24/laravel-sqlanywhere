<?php

namespace Yondaime\LaravelSqlAnywhere;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use PDO;
use Yondaime\LaravelSqlAnywhere\Connections\SqlAnywhereConnection;

class SqlAnywhereServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        DB::extend('sqlanywhere', function ($config, $name) {
            $config['name'] = $name;

            $pdo = new PDO(
                $config['dsn'],
                $config['username'] ?? null,
                $config['password'] ?? null,
                $config['options'] ?? []
            );

            $pdo->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );

            return new SqlAnywhereConnection(
                $pdo,
                $config['database'] ?? '',
                $config['prefix'] ?? '',
                $config
            );
        });
    }
}