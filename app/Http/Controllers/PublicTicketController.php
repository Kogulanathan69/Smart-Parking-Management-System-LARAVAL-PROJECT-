<?php

namespace App\Http\Controllers;

use App\Models\PublicTicketToken;
use App\Services\PublicTicketService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class PublicTicketController extends Controller
{
    public function __invoke(string $token, PublicTicketService $tickets): Response
    {
        if (! Schema::hasTable('public_ticket_tokens')) {
            return $this->respond('public-ticket-unavailable', ['reason' => 'invalid'], 404);
        }

        $public = $tickets->find($token);
        if (! $public) {
            return $this->respond('public-ticket-unavailable', ['reason' => 'invalid'], 404);
        }
        if ($public->status !== PublicTicketToken::ACTIVE) {
            return $this->respond('public-ticket-unavailable', ['reason' => 'revoked'], 410);
        }

        return $this->respond('public-ticket', ['ticket' => $tickets->present($public)]);
    }

    private function respond(string $view, array $data, int $status = 200): Response
    {
        return response()->view($view, $data, $status)
            ->header('Cache-Control', 'no-store, private, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }
}
