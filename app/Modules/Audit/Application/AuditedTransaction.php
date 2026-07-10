<?php

namespace App\Modules\Audit\Application;

use Illuminate\Support\Facades\DB;

final class AuditedTransaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $mutation
     * @param  callable(T): void  $audit
     * @return T
     */
    public function run(callable $mutation, callable $audit): mixed
    {
        return DB::transaction(function () use ($mutation, $audit): mixed {
            $result = $mutation();
            $audit($result);

            return $result;
        }, 3);
    }
}
