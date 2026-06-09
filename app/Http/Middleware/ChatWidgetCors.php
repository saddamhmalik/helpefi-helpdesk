<?php

namespace App\Http\Middleware;

use App\Domains\Channels\Models\Channel;
use App\Domains\Channels\Repositories\ChannelRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ChatWidgetCors
{
    public function __construct(
        private ChannelRepository $channels,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        $channel = $this->channels->findActiveBySlug('chat');
        $allowed = $channel?->settings['allowed_origins'] ?? ['*'];

        if ($request->isMethod('OPTIONS')) {
            return $this->applyCors(response('', 204), $origin, $allowed);
        }

        $response = $next($request);

        return $this->applyCors($response, $origin, $allowed);
    }

    private function applyCors(Response $response, ?string $origin, array $allowed): Response
    {
        if ($origin && $this->originAllowed($origin, $allowed)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Vary', 'Origin');
        } elseif (in_array('*', $allowed, true)) {
            $response->headers->set('Access-Control-Allow-Origin', '*');
        }

        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-Widget-Key, X-Session-Token, Accept');

        return $response;
    }

    private function originAllowed(string $origin, array $allowed): bool
    {
        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($origin, $allowed, true);
    }
}
