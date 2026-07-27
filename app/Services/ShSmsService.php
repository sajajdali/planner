<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShSmsService
{
    /**
     * Send a template SMS via shsms.ir.
     *
     * @param array<int, string|int|float> $params
     *
     * @return array{ok: bool, sandbox?: bool, status?: int, response?: mixed, error?: string}
     */
    public function send(string $receptor, string $template, array $params): array
    {
        if (config('services.shsms.sandbox')) {
            return [
                'ok' => true,
                'sandbox' => true,
                'response' => compact('receptor', 'template', 'params'),
            ];
        }

        $token = config('services.shsms.token');

        if (blank($token) || blank($receptor) || blank($template) || count($params) === 0) {
            return [
                'ok' => false,
                'error' => 'Token, receptor, template, and at least one param are required.',
            ];
        }

        $query = [
            'receptor' => $receptor,
            'template' => $template,
        ];

        foreach ($params as $param) {
            $query['param'][] = (string) $param;
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout(15)
            ->get('https://shsms.ir/api/v1/sendms', $query);

        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
            'response' => $response->json() ?? $response->body(),
        ];
    }
}
