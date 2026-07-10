<?php

return [
    'tenant_header' => env('TENANCY_HEADER', 'X-Tenant-ID'),
    'shared_schema' => true,
    'required' => env('TENANCY_REQUIRED', true),
    'private_storage_root' => env('TENANCY_PRIVATE_STORAGE_ROOT', 'tenants'),
];
