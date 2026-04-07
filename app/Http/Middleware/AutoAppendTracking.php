<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoAppendTracking
{
    protected int $uidLength = 32;
    protected string $appVersion = '2.2.0';
    protected string $defaultTimezone = 'Asia/Jakarta';

    protected array $excludedPaths = [
        'api/',
        'login',
        'logout',
        'register',
        'password',
    ];

    public function handle(Request $request, Closure $next)
    {
        $currentPath = ltrim($request->path(), '/');

        foreach ($this->excludedPaths as $excluded) {
            if (Str::startsWith($currentPath, $excluded)) {
                return $next($request);
            }
        }

        if (!$request->has('uid')) {
            $userAgent  = $request->header('User-Agent', 'Unknown-UA');
            $acceptLang = $request->header('Accept-Language', 'en');
            $ipAddress  = $request->ip();
            $host       = $request->getHost();
            $scheme     = $request->getScheme();
            $method     = $request->method();
            $referer    = $request->header('Referer', 'direct');

            // IDs & Fingerprints
            $uid       = bin2hex(random_bytes($this->uidLength / 2));
            $rid       = Str::random(32);
            $sess      = substr(sha1(session()->getId()), 0, 12);
            $fp        = substr(sha1($userAgent.$ipAddress), 0, 16);
            $visitorId = substr(hash('sha1', $ipAddress.$userAgent), 0, 20);
            $deviceId  = substr(hash('sha256', $userAgent.$ipAddress.$sess), 0, 32);

            // Enterprise Trace
            $traceId   = 'trace-' . Str::uuid() . '-' . Str::random(6);
            $spanId    = 'span-' . bin2hex(random_bytes(4));
            $txnId     = strtoupper(Str::random(20));

            // Security
            $entropy   = bin2hex(random_bytes(32));
            $nonce     = Str::random(40);
            $sig       = hash_hmac('sha256', $uid.$rid.$entropy, 'secret-key-hash');
            $hashFinal = substr(hash('sha256', $uid.$rid.$entropy.$sess), 0, 40);

            // Tracking Data
            $trackingData = [
                // 1. Identitas Request
                'uid'        => $uid,
                'rid'        => $rid,
                'trace_id'   => $traceId,
                'span_id'    => $spanId,
                'txn_id'     => $txnId,
                'sess'       => $sess,
                'device_id'  => $deviceId,
                'visitor_id' => $visitorId,
                'fp'         => $fp,

                // 2. Informasi Client
                'browser'    => $this->detectBrowser($userAgent),
                'engine'     => $this->detectEngine($userAgent),
                'os'         => $this->detectOS($userAgent),
                'arch'       => php_uname('m') ?: 'unknown',
                'src'        => $this->isMobile($userAgent) ? 'mobile' : 'desktop',
                'ua'         => base64_encode($userAgent),
                'lang'       => substr($acceptLang, 0, 2) ?: 'en',
                'country'    => strtoupper(substr($acceptLang, 3, 2) ?: 'ID'),
                'client'     => str_contains(strtolower($userAgent), 'bot') ? 'bot' : 'browser',

                // 3. Informasi Koneksi
                'ip'         => $ipAddress,
                'host'       => $host,
                'proto'      => $scheme,
                'method'     => $method,
                'referer'    => $referer,
                'net'        => $request->header('Save-Data') ? 'low' : 'high',

                // 4. Informasi Aplikasi
                'v'          => $this->appVersion,
                'tz'         => $this->defaultTimezone,
                'platform'   => $request->header('X-Platform', 'web'),
                'build'      => 'build-20250916',
                'flow'       => 'entry',
                'rand'       => random_int(10000, 999999),

                // 5. Keamanan
                'nonce'      => $nonce,
                'signature'  => $sig,
                'hash'       => $hashFinal,

                // 6. Waktu
                'ts'         => time(),
            ];

            return redirect()->to($request->url() . '?' . http_build_query($trackingData));
        }

        return $next($request);
    }

    protected function detectBrowser(string $agent): string
    {
        if (preg_match('/Chrome\/([\d.]+)/', $agent, $matches)) return 'Chrome_' . $matches[1];
        if (preg_match('/Firefox\/([\d.]+)/', $agent, $matches)) return 'Firefox_' . $matches[1];
        if (preg_match('/Edg\/([\d.]+)/', $agent, $matches)) return 'Edge_' . $matches[1];
        if (preg_match('/Safari\/([\d.]+)/', $agent, $matches) && !str_contains($agent, 'Chrome')) {
            return 'Safari_' . ($matches[1] ?? 'unknown');
        }
        return 'Unknown';
    }

    protected function detectEngine(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'AppleWebKit') => 'WebKit/Blink',
            str_contains($agent, 'Gecko') && !str_contains($agent, 'like Gecko') => 'Gecko',
            str_contains($agent, 'Trident') => 'Trident',
            default => 'Unknown',
        };
    }

    protected function detectOS(string $agent): string
    {
        return match (true) {
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Windows')   => 'Windows',
            str_contains($agent, 'Linux')     => 'Linux',
            str_contains($agent, 'Android')   => 'Android',
            str_contains($agent, 'iPhone')    => 'iOS',
            default                           => 'Other',
        };
    }

    protected function isMobile(string $agent): bool
    {
        return (bool) preg_match('/(iPhone|Android|webOS|BlackBerry|IEMobile|Opera Mini)/i', $agent);
    }
}
