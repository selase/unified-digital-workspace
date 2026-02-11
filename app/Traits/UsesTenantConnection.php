<?php

declare(strict_types=1);

namespace App\Traits;

trait UsesTenantConnection
{
    public function initializeUsesTenantConnection(): void
    {
        if ($this->connection === null) {
            $this->setConnection(config('database.default_tenant_connection', 'tenant'));
        }
    }
}
