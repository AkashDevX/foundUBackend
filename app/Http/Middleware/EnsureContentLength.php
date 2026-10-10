<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Send Content-Length on buffered API responses.
 *
 * `php artisan serve` closes the socket without that header. Android then
 * drops the last byte, and the training detail JSON fails to parse.
 */
class EnsureContentLength
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
            return $response;
        }

        if ($response->headers->has('Content-Length') || $response->headers->has('Transfer-Encoding')) {
            return $response;
        }

        $content = $response->getContent();
        if (! is_string($content)) {
            return $response;
        }

        $response->headers->set('Content-Length', (string) strlen($content));

        return $response;
    }
}
