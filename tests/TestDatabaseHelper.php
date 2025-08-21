<?php

namespace Tests;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class TestDatabaseHelper
{
    public static function configurePgSQLTesting(): void
    {
        // For performance tests, force PostgreSQL connection using existing .env.testing values
        Config::set('database.default', 'pgsql');
        
        // Verify we're using the correct database connection
        if (env('APP_ENV') === 'testing') {
            // Use the database configuration from .env.testing
            Config::set('database.connections.pgsql.database', env('DB_DATABASE', 'testing_database'));
            Config::set('database.connections.pgsql.host', env('DB_HOST', 'localhost'));
            Config::set('database.connections.pgsql.port', env('DB_PORT', '5432'));
            Config::set('database.connections.pgsql.username', env('DB_USERNAME'));
            Config::set('database.connections.pgsql.password', env('DB_PASSWORD'));
        }
        
        // Purge existing connections and reconnect
        DB::purge();
        DB::reconnect();
        
        // Verify we're connected to PostgreSQL
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            throw new \RuntimeException("Expected PostgreSQL connection, got: {$driver}. Make sure to run tests with --env=testing");
        }
    }
    
    public static function ensurePostgreSQLExtensions(): void
    {
        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm;');
            DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent;');
        } catch (\Exception $e) {
            // Extensions might already exist or user doesn't have permissions
            // This is acceptable for testing
        }
    }
    
    public static function resetDatabase(): void
    {
        // Clear all tables in the testing database safely
        try {
            $tables = DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'");
            foreach ($tables as $table) {
                DB::statement("DROP TABLE IF EXISTS {$table->tablename} CASCADE");
            }
        } catch (\Exception $e) {
            // If we can't drop tables individually, try schema reset
            DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
            DB::statement('CREATE SCHEMA public');
        }
    }
}