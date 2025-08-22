<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TestController extends Controller
{
    public function client()
    {
        return Inertia::render('OAuth/TestClient');
    }

    public function callback(Request $request)
    {
        $params = $request->all();

        return Inertia::render('OAuth/TestCallback', [
            'params' => $params,
        ]);
    }
}
