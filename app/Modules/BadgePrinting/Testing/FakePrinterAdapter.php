<?php

namespace App\Modules\BadgePrinting\Testing;

use App\Modules\BadgePrinting\Contracts\PrinterAdapter;
use App\Modules\BadgePrinting\Domain\Results\PrinterHealthResult;
use App\Modules\BadgePrinting\Domain\Results\PrintResult;
use App\Modules\BadgePrinting\Domain\ValueObjects\PrintPayload;

final class FakePrinterAdapter implements PrinterAdapter
{
    /** @var list<array{operation:string,payload:mixed}> */
    private array $calls = [];

    private ?PrinterHealthResult $forcedHealth = null;

    public function print(PrintPayload $payload): PrintResult
    {
        $this->calls[] = ['operation' => 'print', 'payload' => $payload];

        $forced = $payload->fields['__force_failure'] ?? null;

        if ($forced !== null) {
            $reasonCode = $forced === 'payload_rejected' ? 'payload_rejected' : 'printer_'.$forced;

            return new PrintResult('failed', $reasonCode, null);
        }

        return new PrintResult(
            'printed',
            null,
            'fake-ref-'.substr(md5($payload->idempotencyKey), 0, 16),
        );
    }

    public function health(): PrinterHealthResult
    {
        $this->calls[] = ['operation' => 'health', 'payload' => null];

        return $this->forcedHealth ?? new PrinterHealthResult('ready', null);
    }

    public function forceHealth(string $status, ?string $reasonCode = null): void
    {
        $this->forcedHealth = new PrinterHealthResult($status, $reasonCode);
    }

    /** @return list<array{operation:string,payload:mixed}> */
    public function calls(): array
    {
        return $this->calls;
    }
}
