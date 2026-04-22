<?php

namespace App\Console\Commands;

use Database\Seeders\MasterCompaniesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use PDO;
use PDOException;

/**
 * Creates the master + tenant MySQL schemas from config/database.php defaults,
 * then optionally runs migrations and seeds the master registry for API tests.
 */
class ProvisionFoundULocal extends Command
{
    protected $signature = 'foundu:provision-local
                            {--only-databases : Only run CREATE DATABASE statements}
                            {--skip-migrate : Skip php artisan migrate}
                            {--skip-seed : Skip seeding MasterCompaniesSeeder}';

    protected $description = 'CREATE DATABASE for master + three tenants, migrate, seed master companies';

    public function handle(): int
    {
        if (! extension_loaded('pdo_mysql')) {
            $this->error('The PHP pdo_mysql extension must be enabled to create MySQL databases.');

            return self::FAILURE;
        }

        try {
            $this->createMysqlDatabases();
        } catch (PDOException $e) {
            $this->error('Could not connect to MySQL or create databases: '.$e->getMessage());
            $this->line('');
            $this->line('Check .env (or uncomment variables in .env.example): DB_HOST, DB_USERNAME, DB_PASSWORD, MASTER_DB_* / TENANT_*.');
            $this->line('Ensure MySQL/MariaDB is running and the user may CREATE DATABASE.');

            return self::FAILURE;
        }

        if ($this->option('only-databases')) {
            return self::SUCCESS;
        }

        if (! $this->option('skip-migrate')) {
            $this->info('Running migrations…');
            $code = Artisan::call('migrate', ['--force' => true]);
            $this->output->write(Artisan::output());
            if ($code !== 0) {
                return self::FAILURE;
            }
        }

        if (! $this->option('skip-seed')) {
            $this->info('Seeding master companies registry…');
            $code = Artisan::call('db:seed', [
                '--class' => MasterCompaniesSeeder::class,
                '--force' => true,
            ]);
            $this->output->write(Artisan::output());
            if ($code !== 0) {
                return self::FAILURE;
            }
        }

        $this->info('Done. Test tenant API with header X-Company-Slug and a slug from companies.slug (e.g. bluegreen-facility-services).');

        return self::SUCCESS;
    }

    /**
     * Connect without a schema and CREATE DATABASE for each configured name.
     */
    private function createMysqlDatabases(): void
    {
        $cfg = config('database.connections.master');
        $host = $cfg['host'] ?? '127.0.0.1';
        $port = (string) ($cfg['port'] ?? '3306');
        $username = $cfg['username'] ?? 'root';
        $password = $cfg['password'] ?? '';
        $socket = $cfg['unix_socket'] ?? '';

        $charset = $cfg['charset'] ?? 'utf8mb4';
        $collation = $cfg['collation'] ?? 'utf8mb4_unicode_ci';

        $names = [
            config('database.connections.master.database'),
            config('database.connections.tenant_bluegreen.database'),
            config('database.connections.tenant_constructconcepts.database'),
            config('database.connections.tenant_aidandable.database'),
        ];

        foreach ($names as $name) {
            if (! is_string($name) || ! preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
                throw new PDOException('Invalid database name in configuration.');
            }
        }

        if ($socket !== '') {
            $dsn = sprintf('mysql:unix_socket=%s;charset=%s', $socket, $charset);
        } else {
            $dsn = sprintf('mysql:host=%s;port=%s;charset=%s', $host, $port, $charset);
        }

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        foreach ($names as $name) {
            $escaped = str_replace('`', '``', $name);
            $sql = sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
                $escaped,
                $charset,
                $collation
            );
            $pdo->exec($sql);
            $this->line("Database ready: {$name}");
        }
    }
}
