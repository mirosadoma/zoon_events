<?php

namespace App\Modules\Authorization\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authorization\Domain\PermissionCatalog;
use App\Modules\Shared\Http\Responses\RespondsWithApi;
use Illuminate\Http\Request;

class PermissionCatalogController extends Controller
{
    use RespondsWithApi;

    public function __invoke(Request $request)
    {
        $scope = $request->query('scope', 'tenant');

        if (! in_array($scope, ['tenant', 'platform'], true)) {
            abort(422, 'Scope must be tenant or platform.');
        }

        return $this->success(PermissionCatalog::groupedByModule($scope));
    }
}
