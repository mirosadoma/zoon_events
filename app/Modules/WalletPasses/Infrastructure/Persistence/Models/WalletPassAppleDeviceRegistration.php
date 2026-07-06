<?php

namespace App\Modules\WalletPasses\Infrastructure\Persistence\Models;

use Database\Factories\WalletPassAppleDeviceRegistrationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WalletPassAppleDeviceRegistration extends Model
{
    /** @use HasFactory<WalletPassAppleDeviceRegistrationFactory> */
    use HasFactory;

    use HasUlids;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'wallet_pass_id',
        'device_library_identifier',
        'push_token',
        'registered_at',
        'unregistered_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'immutable_datetime',
            'unregistered_at' => 'immutable_datetime',
        ];
    }

    public function walletPass(): BelongsTo
    {
        return $this->belongsTo(WalletPass::class);
    }
}
