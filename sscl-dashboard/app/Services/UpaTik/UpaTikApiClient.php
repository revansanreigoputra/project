<?php

namespace App\Services\UpaTik;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UpaTikApiClient
{
    public function isEnabled(): bool
    {
        return (bool) config('services.upa_tik.enabled', false);
    }

    public function fetchTraffic(): array
    {
        if (! $this->isEnabled()) {
            return [
                'ok' => false,
                'status' => 'disabled',
                'message' => 'Integrasi API UPA-TIK belum diaktifkan.',
            ];
        }

        $missing = $this->missingConfiguration();

        if ($missing !== []) {
            Log::warning('UPA-TIK API configuration is incomplete.', [
                'missing' => $missing,
            ]);

            return [
                'ok' => false,
                'status' => 'misconfigured',
                'message' => 'Konfigurasi API UPA-TIK belum lengkap: '.implode(', ', $missing).'.',
            ];
        }

        try {
            $response = Http::timeout((int) config('services.upa_tik.timeout', 30))
                ->acceptJson()
                ->withToken((string) config('services.upa_tik.token'))
                ->get($this->url());
        } catch (ConnectionException $exception) {
            Log::warning('UPA-TIK API connection failed.', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 'connection_failed',
                'message' => 'API UPA-TIK belum bisa dihubungi.',
            ];
        } catch (Throwable $exception) {
            Log::warning('UPA-TIK API request failed unexpectedly.', [
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'status' => 'request_failed',
                'message' => 'Request API UPA-TIK gagal diproses.',
            ];
        }

        if ($response->failed()) {
            Log::warning('UPA-TIK API returned an unsuccessful status.', [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);

            return [
                'ok' => false,
                'status' => 'http_failed',
                'message' => 'API UPA-TIK mengembalikan status HTTP '.$response->status().'.',
            ];
        }

        $contentType = strtolower((string) $response->header('Content-Type', ''));
        $payload = $response->json();

        if (str_contains($contentType, 'json')) {
            if (! is_array($payload)) {
                Log::warning('UPA-TIK API returned invalid JSON.');

                return [
                    'ok' => false,
                    'status' => 'invalid_response',
                    'message' => 'Response JSON API UPA-TIK tidak valid.',
                ];
            }

            return [
                'ok' => true,
                'type' => 'json',
                'payload' => $payload,
            ];
        }

        if (is_array($payload)) {
            return [
                'ok' => true,
                'type' => 'json',
                'payload' => $payload,
            ];
        }

        return [
            'ok' => true,
            'type' => 'file',
            'contents' => $response->body(),
            'file_name' => $this->fileNameFromResponse($response->header('Content-Disposition')),
        ];
    }

    private function missingConfiguration(): array
    {
        return collect([
            'UPA_TIK_API_BASE_URL' => config('services.upa_tik.base_url'),
            'UPA_TIK_API_ENDPOINT' => config('services.upa_tik.endpoint'),
            'UPA_TIK_API_TOKEN' => config('services.upa_tik.token'),
        ])
            ->filter(fn ($value) => blank($value))
            ->keys()
            ->values()
            ->all();
    }

    private function url(): string
    {
        return rtrim((string) config('services.upa_tik.base_url'), '/')
            .'/'
            .ltrim((string) config('services.upa_tik.endpoint'), '/');
    }

    private function fileNameFromResponse(?string $contentDisposition): string
    {
        if ($contentDisposition && preg_match('/filename="?([^";]+)"?/i', $contentDisposition, $matches)) {
            return basename($matches[1]);
        }

        return 'upa-tik-traffic.csv';
    }
}