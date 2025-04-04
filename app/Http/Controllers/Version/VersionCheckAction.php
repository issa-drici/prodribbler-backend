<?php

namespace App\Http\Controllers\Version;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VersionCheckAction extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'ios_version' => '2.32.19',
            'android_version' => '2.32.20',
            'force_update' => true,
            'update_message' => 'A new version is available with important features. Please update your application.'
        ]);
    }
}
