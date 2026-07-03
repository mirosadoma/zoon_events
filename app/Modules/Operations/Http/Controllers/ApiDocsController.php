<?php

namespace App\Modules\Operations\Http\Controllers;

use App\Http\Controllers\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ApiDocsController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        abort_unless(app()->environment(['local', 'testing']) || auth()->check(), 404);

        return response()->file(base_path('docs/api/openapi.yaml'), ['Content-Type' => 'application/yaml']);
    }
}
