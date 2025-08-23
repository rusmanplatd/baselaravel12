<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ErrorController extends Controller
{
    public function show(Request $request, $status = 404)
    {
        $status = (int) $status;

        // Log error page access
        ActivityLogService::logSystem('error_page_accessed', "Error page accessed: {$status}", [
            'status_code' => $status,
            'url' => $request->url(),
            'referer' => $request->header('referer'),
            'user_agent' => $request->userAgent(),
        ]);

        $messages = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Page Not Found',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            500 => 'Server Error',
            503 => 'Service Unavailable',
        ];

        $descriptions = [
            400 => 'The request could not be understood by the server.',
            401 => 'You must be authenticated to access this resource.',
            403 => 'You do not have permission to access this resource.',
            404 => 'The page you are looking for could not be found.',
            419 => 'Your session has expired. Please try again.',
            429 => 'Too many requests. Please try again later.',
            500 => 'Something went wrong on our end. Please try again later.',
            503 => 'The service is temporarily unavailable. Please try again later.',
        ];

        return Inertia::render('Error', [
            'status' => $status,
            'message' => $messages[$status] ?? 'Something went wrong',
            'description' => $descriptions[$status] ?? 'An unexpected error occurred.',
        ])->toResponse($request)->setStatusCode($status);
    }
}
