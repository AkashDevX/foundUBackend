<?php

namespace App\Support;

use App\Models\Company;
use Illuminate\Http\Request;

class CurrentTenant
{
    public static function company(?Request $request = null): ?Company
    {
        $request ??= request();

        return $request->attributes->get('tenant_company');
    }
}
