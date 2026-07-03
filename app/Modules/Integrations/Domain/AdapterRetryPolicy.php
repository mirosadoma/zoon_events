<?php

namespace App\Modules\Integrations\Domain;

enum AdapterRetryPolicy: string
{
    case Never = 'never';
    case Safe = 'safe';
    case ReconcileFirst = 'reconcile_first';
}
