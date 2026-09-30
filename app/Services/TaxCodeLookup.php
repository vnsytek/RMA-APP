<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Looks up a Vietnamese tax code (MST) in the public business registry.
 * Each configured provider is tried in turn (xinvoice.vn, then VietQR), so a rate limit on one
 * falls back to the other. Found companies are cached for a month to save both services' quotas.
 */
class TaxCodeLookup
{
    public const FOUND = 'found';

    public const NOT_FOUND = 'not_found';

    public const UNAVAILABLE = 'unavailable';

    /**
     * @return array{result: string, name?: string, address?: string, status?: string, active?: bool, org_type?: ?string, tax_department?: ?string, source?: string}
     */
    public function find(string $taxCode): array
    {
        $key = 'tax-code:'.$taxCode;

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        $outcome = ['result' => self::UNAVAILABLE];

        foreach (config('rma.tax_lookup.providers') as $provider) {
            $outcome = match ($provider) {
                'xinvoice' => $this->xinvoice($taxCode),
                'vietqr' => $this->vietqr($taxCode),
                default => ['result' => self::UNAVAILABLE],
            };

            if ($outcome['result'] !== self::UNAVAILABLE) {
                break;
            }
        }

        if ($outcome['result'] === self::FOUND) {
            Cache::put($key, $outcome, now()->addDays(30));
        }

        return $outcome;
    }

    /**
     * @return array<string, mixed>
     */
    private function xinvoice(string $taxCode): array
    {
        $settings = config('rma.tax_lookup.xinvoice');
        $headers = array_filter(['client-id' => $settings['client_id'], 'api-key' => $settings['api_key']]);
        $response = $this->get(rtrim($settings['url'], '/').'/'.rawurlencode($taxCode), $headers);

        if ($response?->successful() && $response->json('name')) {
            return $this->found($response->json('name'), $response->json('address'), $response->json('status'), 'xinvoice', [
                'org_type' => $response->json('orgType'),
                'tax_department' => $response->json('taxDepartment'),
            ]);
        }

        return ['result' => $response?->status() === 404 ? self::NOT_FOUND : self::UNAVAILABLE];
    }

    /**
     * @return array<string, mixed>
     */
    private function vietqr(string $taxCode): array
    {
        $response = $this->get(rtrim(config('rma.tax_lookup.vietqr.url'), '/').'/'.rawurlencode($taxCode));
        $code = (string) $response?->json('code');

        if ($response?->successful() && $code === '00' && $response->json('data.name')) {
            return $this->found($response->json('data.name'), $response->json('data.address'), $response->json('data.status'), 'vietqr');
        }

        // 51/52: the registry has no such tax code. Anything else (429, 5xx) means "try again later".
        return ['result' => in_array($code, ['51', '52'], true) ? self::NOT_FOUND : self::UNAVAILABLE];
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function get(string $url, array $headers = []): ?Response
    {
        try {
            return Http::acceptJson()->withHeaders($headers)->timeout(8)->get($url);
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function found(mixed $name, mixed $address, mixed $status, string $source, array $extra = []): array
    {
        $status = trim((string) $status);

        return [
            'result' => self::FOUND,
            'name' => preg_replace('/\s+/u', ' ', trim((string) $name)),
            'address' => preg_replace('/\s+/u', ' ', trim((string) $address)),
            'status' => $status,
            'active' => str_contains(mb_strtolower($status), 'đang hoạt động'),
            'org_type' => null,
            'tax_department' => null,
            ...$extra,
            'source' => $source,
        ];
    }

    /**
     * Tax codes are 10 digits, branches add "-" and 3 digits.
     */
    public static function normalize(?string $taxCode): ?string
    {
        $taxCode = preg_replace('/\s+/', '', (string) $taxCode);

        return $taxCode === '' ? null : $taxCode;
    }

    public static function pattern(): string
    {
        return '/^\d{10}(-\d{3})?$/';
    }
}
