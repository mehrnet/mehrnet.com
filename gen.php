<?php
declare(strict_types=1);

/**
 * Static data generator for a FOSSBilling-powered marketing site.
 *
 * Usage:
 *   php gen.php --out=data.json --pretty=1
 */

final class ApiException extends RuntimeException
{
    public string $scope;
    public string $method;
    public int $statusCode;

    public function __construct(
        string $message,
        string $scope,
        string $method,
        int $statusCode = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->scope = $scope;
        $this->method = $method;
        $this->statusCode = $statusCode;
    }
}

final class FossBillingApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeoutSeconds;
    private bool $strictTls;
    private array $callLog = [];

    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeoutSeconds = 25,
        bool $strictTls = true
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->timeoutSeconds = max(5, $timeoutSeconds);
        $this->strictTls = $strictTls;
    }

    public function getCallLog(): array
    {
        return $this->callLog;
    }

    public function call(string $scope, string $method, array $payload = []): mixed
    {
        $endpoint = $this->endpoint($scope, $method);
        $effectivePayload = $this->withAuthPayload($scope, $payload);

        $jsonError = null;
        try {
            $response = $this->request(
                $endpoint,
                $scope,
                $method,
                $effectivePayload,
                'json'
            );
            return $this->unwrapResult($response, $scope, $method);
        } catch (Throwable $error) {
            $jsonError = $error;
        }

        try {
            $response = $this->request(
                $endpoint,
                $scope,
                $method,
                $effectivePayload,
                'form'
            );
            return $this->unwrapResult($response, $scope, $method);
        } catch (Throwable $formError) {
            $message = sprintf(
                '%s/%s failed in both JSON and form mode. json_error=%s; form_error=%s',
                $scope,
                $method,
                $jsonError ? $jsonError->getMessage() : 'n/a',
                $formError->getMessage()
            );
            throw new ApiException(
                $message,
                $scope,
                $method,
                ($formError instanceof ApiException) ? $formError->statusCode : 0,
                $formError
            );
        }
    }

    public function callWithFallback(string $scope, array $methods, array $payload = []): mixed
    {
        $lastError = null;
        foreach ($methods as $method) {
            try {
                return $this->call($scope, (string) $method, $payload);
            } catch (Throwable $error) {
                $lastError = $error;
            }
        }

        if ($lastError instanceof Throwable) {
            throw $lastError;
        }

        throw new ApiException('No API methods were provided', $scope, 'n/a');
    }

    private function endpoint(string $scope, string $method): string
    {
        return sprintf('%s/api/%s/%s', $this->baseUrl, trim($scope), trim($method, '/'));
    }

    private function withAuthPayload(string $scope, array $payload): array
    {
        if ($scope !== 'admin' || $this->apiKey === '') {
            return $payload;
        }

        $payload['api_key'] = $payload['api_key'] ?? $this->apiKey;
        $payload['api_token'] = $payload['api_token'] ?? $this->apiKey;
        $payload['token'] = $payload['token'] ?? $this->apiKey;
        return $payload;
    }

    private function authHeader(string $scope): string
    {
        if ($scope === 'guest' || $this->apiKey === '') {
            return '';
        }

        $username = ($scope === 'admin') ? 'admin' : 'client';
        return 'Authorization: Basic ' . base64_encode($username . ':' . $this->apiKey);
    }

    private function request(
        string $endpoint,
        string $scope,
        string $method,
        array $payload,
        string $mode
    ): array {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('PHP cURL extension is required.');
        }

        $headers = ['Accept: application/json'];
        $authHeader = $this->authHeader($scope);
        if ($authHeader !== '') {
            $headers[] = $authHeader;
        }

        if ($mode === 'json') {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                throw new RuntimeException('Failed to encode payload as JSON');
            }
            $headers[] = 'Content-Type: application/json';
        } else {
            $body = http_build_query($payload);
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize cURL');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $this->timeoutSeconds));
        if (!$this->strictTls) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $raw = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlError = curl_error($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->callLog[] = [
            'scope' => $scope,
            'method' => $method,
            'mode' => $mode,
            'status_code' => $statusCode,
            'curl_errno' => $curlErrNo,
            'success' => ($curlErrNo === 0 && $statusCode < 500),
        ];

        if ($curlErrNo !== 0) {
            throw new ApiException(
                sprintf('cURL error (%d): %s', $curlErrNo, $curlError),
                $scope,
                $method,
                $statusCode
            );
        }

        $rawText = is_string($raw) ? $raw : '';
        $decoded = json_decode($rawText, true);
        if (!is_array($decoded)) {
            throw new ApiException(
                sprintf(
                    'Non-JSON response (%d): %s',
                    $statusCode,
                    substr(trim($rawText), 0, 200)
                ),
                $scope,
                $method,
                $statusCode
            );
        }

        if ($statusCode >= 400) {
            $errorMessage = $this->readErrorMessage($decoded);
            throw new ApiException(
                sprintf('HTTP %d: %s', $statusCode, $errorMessage),
                $scope,
                $method,
                $statusCode
            );
        }

        return $decoded;
    }

    private function unwrapResult(array $decoded, string $scope, string $method): mixed
    {
        $error = $decoded['error'] ?? null;
        if ($error !== null && $error !== '' && $error !== false) {
            throw new ApiException(
                sprintf('API error: %s', $this->readErrorMessage($decoded)),
                $scope,
                $method
            );
        }

        if (array_key_exists('result', $decoded)) {
            return $decoded['result'];
        }

        return $decoded;
    }

    private function readErrorMessage(array $decoded): string
    {
        $error = $decoded['error'] ?? null;
        if (is_string($error) && $error !== '') {
            return $error;
        }
        if (is_array($error)) {
            foreach (['message', 'msg', 'code', 'error'] as $key) {
                if (isset($error[$key]) && $error[$key] !== '') {
                    return (string) $error[$key];
                }
            }
            return json_encode($error, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'unknown error';
        }
        if (isset($decoded['message']) && $decoded['message'] !== '') {
            return (string) $decoded['message'];
        }
        return 'unknown error';
    }
}

function parseDotEnv(string $content): array
{
    $values = [];
    $lines = preg_split('/\R/u', $content) ?: [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (str_starts_with($trimmed, 'export ')) {
            $trimmed = trim(substr($trimmed, 7));
        }

        $equalsPos = strpos($trimmed, '=');
        if ($equalsPos === false || $equalsPos <= 0) {
            continue;
        }

        $key = trim(substr($trimmed, 0, $equalsPos));
        $value = trim(substr($trimmed, $equalsPos + 1));
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }

    return $values;
}

function loadDotEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    $raw = file_get_contents($path);
    if (!is_string($raw)) {
        return;
    }

    $pairs = parseDotEnv($raw);
    foreach ($pairs as $key => $value) {
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

function envString(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }

    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return $default;
    }
    return $trimmed;
}

function envBool(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false) {
        return $default;
    }
    $normalized = strtolower(trim((string) $value));
    if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }
    return $default;
}

function envCsv(string $name, string $default = ''): array
{
    $raw = envString($name, $default);
    if ($raw === null || $raw === '') {
        return [];
    }

    $items = array_map('trim', explode(',', $raw));
    $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));
    return $items;
}

function cliOption(array $options, string $key, mixed $default = null): mixed
{
    if (!array_key_exists($key, $options)) {
        return $default;
    }
    $value = $options[$key];
    if ($value === false || $value === null || $value === '') {
        return true;
    }
    return $value;
}

function asList(mixed $value): array
{
    if (is_array($value) && array_is_list($value)) {
        return $value;
    }
    if (!is_array($value)) {
        return [];
    }
    if (isset($value['list']) && is_array($value['list'])) {
        return array_values($value['list']);
    }
    if (isset($value['data']) && is_array($value['data'])) {
        return array_values($value['data']);
    }
    if (isset($value['items']) && is_array($value['items'])) {
        return array_values($value['items']);
    }
    return [];
}

function readPaginationMeta(mixed $result): array
{
    if (!is_array($result)) {
        return [0, 0];
    }

    $pages = 0;
    $total = 0;
    foreach (['pages', 'total_pages'] as $key) {
        if (isset($result[$key]) && is_numeric($result[$key])) {
            $pages = (int) $result[$key];
            break;
        }
    }
    foreach (['total', 'total_results', 'count'] as $key) {
        if (isset($result[$key]) && is_numeric($result[$key])) {
            $total = (int) $result[$key];
            break;
        }
    }
    return [$pages, $total];
}

function fetchPaginated(
    FossBillingApiClient $api,
    string $scope,
    array $methods,
    array $basePayload,
    int $perPage,
    int $maxPages
): array {
    $lastError = null;

    foreach ($methods as $method) {
        try {
            $collected = [];
            for ($page = 1; $page <= $maxPages; $page++) {
                $payload = $basePayload;
                if (!isset($payload['page'])) {
                    $payload['page'] = $page;
                }
                if (!isset($payload['per_page'])) {
                    $payload['per_page'] = $perPage;
                }

                $result = $api->call($scope, (string) $method, $payload);
                $items = asList($result);

                if ($page === 1 && $items === [] && is_array($result) && !array_is_list($result)) {
                    // Not a paginated payload for this method.
                    throw new RuntimeException('Method did not return paginated list payload');
                }

                if ($items === []) {
                    break;
                }

                $collected = array_merge($collected, $items);

                [$pages, $total] = readPaginationMeta($result);
                if ($pages > 0 && $page >= $pages) {
                    break;
                }
                if ($total > 0 && count($collected) >= $total) {
                    break;
                }
                if (count($items) < $perPage) {
                    break;
                }
            }
            return $collected;
        } catch (Throwable $error) {
            $lastError = $error;
        }
    }

    if ($lastError instanceof Throwable) {
        throw $lastError;
    }
    return [];
}

function flattenPairs(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $pairs = [];
    foreach ($value as $key => $title) {
        if (is_array($title)) {
            continue;
        }
        if ($key === '') {
            continue;
        }
        $pairs[(string) $key] = (string) $title;
    }
    return $pairs;
}

function boolLike(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value > 0;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled', 'active'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', 'inactive'], true)) {
            return false;
        }
    }
    return $default;
}

function normalizeText(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_scalar($value)) {
        return trim((string) $value);
    }
    return '';
}

function decimalToFloat(mixed $value): ?float
{
    if (is_int($value) || is_float($value)) {
        return (float) $value;
    }
    if (!is_string($value)) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    $normalized = str_replace(',', '', $trimmed);
    if (!is_numeric($normalized)) {
        return null;
    }

    return (float) $normalized;
}

function formatDecimal(float $value, int $scale = 8): string
{
    $text = number_format($value, $scale, '.', '');
    $text = rtrim(rtrim($text, '0'), '.');
    return ($text === '') ? '0' : $text;
}

function lowerText(mixed $value): string
{
    return strtolower(normalizeText($value));
}

function statusIsPublic(mixed $status, bool $default = true): bool
{
    $value = lowerText($status);
    if ($value === '') {
        return $default;
    }

    $nonPublicTokens = [
        'disabled', 'disable', 'hidden', 'inactive', 'draft', 'private', 'internal',
        'archived', 'deleted', 'closed', 'off',
    ];
    foreach ($nonPublicTokens as $token) {
        if (str_contains($value, $token)) {
            return false;
        }
    }

    $publicTokens = ['active', 'enabled', 'public', 'visible', 'live', 'on'];
    foreach ($publicTokens as $token) {
        if (str_contains($value, $token)) {
            return true;
        }
    }

    return $default;
}

function textContainsAny(array $values, array $patterns): bool
{
    if ($patterns === []) {
        return false;
    }

    $haystack = strtolower(implode(' ', array_map('normalizeText', $values)));
    if ($haystack === '') {
        return false;
    }

    foreach ($patterns as $pattern) {
        $needle = strtolower(trim((string) $pattern));
        if ($needle === '') {
            continue;
        }
        if (str_contains($haystack, $needle)) {
            return true;
        }
    }
    return false;
}

function isPublicProduct(array $product, array $excludePatterns): bool
{
    if (boolLike($product['hidden'] ?? false, false)) {
        return false;
    }
    if (!statusIsPublic($product['status'] ?? '', true)) {
        return false;
    }
    if (normalizeText($product['title'] ?? '') === '') {
        return false;
    }

    $type = lowerText($product['type'] ?? '');
    if ($type === 'domain' || $type === 'tld') {
        return false;
    }

    return !textContainsAny(
        [
            $product['type'] ?? '',
            $product['slug'] ?? '',
            $product['title'] ?? '',
            $product['description'] ?? '',
            $product['category_title'] ?? '',
        ],
        $excludePatterns
    );
}

function isPublicAddon(array $addon, array $excludePatterns): bool
{
    if (!statusIsPublic($addon['status'] ?? '', true)) {
        return false;
    }
    if (normalizeText($addon['title'] ?? '') === '') {
        return false;
    }
    return !textContainsAny(
        [
            $addon['type'] ?? '',
            $addon['slug'] ?? '',
            $addon['title'] ?? '',
            $addon['description'] ?? '',
        ],
        $excludePatterns
    );
}

function sanitizeLimitationsForPublic(array $limits): array
{
    $map = [
        'addons' => ['addon'],
        'databases' => ['database', 'db'],
        'domains' => ['domain'],
        'subdomains' => ['subdomain'],
        'email_accounts' => ['email', 'mailbox'],
        'ftp_accounts' => ['ftp'],
        'cron_jobs' => ['cron'],
        'disk' => ['disk'],
        'inodes' => ['inode'],
        'bandwidth' => ['bandwidth', 'traffic'],
        'ram' => ['ram', 'memory'],
        'cpu' => ['cpu'],
        'websites' => ['site', 'website'],
        'accounts' => ['account'],
    ];

    $public = [];
    foreach ($limits as $key => $value) {
        $k = strtolower((string) $key);
        foreach ($map as $publicKey => $patterns) {
            if (isset($public[$publicKey])) {
                continue;
            }
            foreach ($patterns as $pattern) {
                if (!str_contains($k, $pattern)) {
                    continue;
                }
                if (is_bool($value)) {
                    $public[$publicKey] = $value;
                    break 2;
                }
                if (is_int($value) || is_float($value)) {
                    $public[$publicKey] = (string) $value;
                    break 2;
                }
                if (!is_string($value)) {
                    break;
                }
                $trimmed = trim($value);
                if ($trimmed === '') {
                    break;
                }
                $normalized = strtolower($trimmed);
                if (
                    preg_match('/^-?\d+(\.\d+)?$/', $trimmed) ||
                    in_array($normalized, ['unlimited', 'unmetered', 'infinite'], true)
                ) {
                    $public[$publicKey] = $trimmed;
                }
                break 2;
            }
        }
    }

    return $public;
}

function sanitizePricingForPublic(array $pricing, array $enabledCurrencySet): array
{
    $public = [];

    $normalizeEntry = static function (mixed $entry, bool $defaultEnabled = true): ?array {
        if (is_numeric($entry)) {
            return [
                'price' => (string) $entry,
                'setup' => '0',
                'enabled' => $defaultEnabled,
            ];
        }
        if (!is_array($entry)) {
            return null;
        }
        $price = normalizeText($entry['price'] ?? $entry['value'] ?? $entry['amount'] ?? '');
        $setup = normalizeText($entry['setup'] ?? $entry['setup_price'] ?? $entry['setup_fee'] ?? '0');
        $enabled = boolLike($entry['enabled'] ?? true, $defaultEnabled);
        if ($price === '' && $enabled) {
            $price = '0';
        }
        if ($price === '' && !$enabled) {
            return null;
        }
        return [
            'price' => $price,
            'setup' => ($setup === '' ? '0' : $setup),
            'enabled' => $enabled,
        ];
    };

    $sanitizeOneCurrency = static function (array $model) use ($normalizeEntry): array {
        $out = [
            'type' => normalizeText($model['type'] ?? ''),
            'free' => null,
            'once' => null,
            'recurrent' => [],
        ];

        $free = $normalizeEntry($model['free'] ?? null, true);
        if ($free !== null) {
            $free['enabled'] = true;
            $out['free'] = $free;
        }

        $once = $normalizeEntry($model['once'] ?? null, true);
        if ($once !== null) {
            $out['once'] = $once;
        }

        $recurrentRaw = $model['recurrent'] ?? [];
        if (is_array($recurrentRaw)) {
            foreach ($recurrentRaw as $period => $entry) {
                $normalized = $normalizeEntry($entry, true);
                if ($normalized === null || !$normalized['enabled']) {
                    continue;
                }
                $out['recurrent'][strtoupper((string) $period)] = $normalized;
            }
        }
        ksort($out['recurrent']);

        if ($out['type'] === '' && $out['recurrent'] !== []) {
            $out['type'] = 'recurrent';
        }
        if ($out['type'] === '' && $out['once'] !== null) {
            $out['type'] = 'once';
        }

        if ($out['free'] === null && $out['once'] === null && $out['recurrent'] === []) {
            return [];
        }
        return $out;
    };

    $defaultModel = null;
    foreach ($pricing as $currency => $model) {
        if (!is_array($model)) {
            continue;
        }
        $currencyCode = strtoupper((string) $currency);
        if ($currencyCode === '__DEFAULT') {
            $defaultModel = $sanitizeOneCurrency($model);
            continue;
        }
        if (!isset($enabledCurrencySet[$currencyCode])) {
            continue;
        }
        $sanitized = $sanitizeOneCurrency($model);
        if ($sanitized === []) {
            continue;
        }
        $public[$currencyCode] = $sanitized;
    }

    if ($public === [] && is_array($defaultModel) && $defaultModel !== []) {
        foreach (array_keys($enabledCurrencySet) as $currencyCode) {
            $public[strtoupper((string) $currencyCode)] = $defaultModel;
        }
    }

    ksort($public);
    return $public;
}

function normalizePricingModelForCompare(array $model): array
{
    $normalized = [
        'type' => normalizeText($model['type'] ?? ''),
        'free' => is_array($model['free'] ?? null) ? $model['free'] : null,
        'once' => is_array($model['once'] ?? null) ? $model['once'] : null,
        'recurrent' => is_array($model['recurrent'] ?? null) ? $model['recurrent'] : [],
    ];

    if (is_array($normalized['recurrent'])) {
        ksort($normalized['recurrent']);
    }
    return $normalized;
}

function pricingModelsEqual(array $left, array $right): bool
{
    return normalizePricingModelForCompare($left) === normalizePricingModelForCompare($right);
}

function convertAmountByRate(mixed $amount, float $multiplier, int $decimals = 2): mixed
{
    $numeric = decimalToFloat($amount);
    if ($numeric === null) {
        return $amount;
    }

    $precision = max(0, min(6, $decimals));
    $converted = $numeric * $multiplier;
    return number_format($converted, $precision, '.', '');
}

function convertPricingModelByRate(array $model, float $multiplier, int $decimals = 2): array
{
    $converted = $model;

    foreach (['free', 'once'] as $section) {
        if (!isset($converted[$section]) || !is_array($converted[$section])) {
            continue;
        }
        if (array_key_exists('price', $converted[$section])) {
            $converted[$section]['price'] = (string) convertAmountByRate(
                $converted[$section]['price'],
                $multiplier,
                $decimals
            );
        }
        if (array_key_exists('setup', $converted[$section])) {
            $converted[$section]['setup'] = (string) convertAmountByRate(
                $converted[$section]['setup'],
                $multiplier,
                $decimals
            );
        }
    }

    if (isset($converted['recurrent']) && is_array($converted['recurrent'])) {
        foreach ($converted['recurrent'] as $period => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (array_key_exists('price', $entry)) {
                $entry['price'] = (string) convertAmountByRate(
                    $entry['price'],
                    $multiplier,
                    $decimals
                );
            }
            if (array_key_exists('setup', $entry)) {
                $entry['setup'] = (string) convertAmountByRate(
                    $entry['setup'],
                    $multiplier,
                    $decimals
                );
            }
            $converted['recurrent'][$period] = $entry;
        }
    }

    return $converted;
}

function normalizeDomainTldItem(array $row): array
{
    return [
        'id' => (string) pickFirst($row, ['id'], ''),
        'tld' => normalizeText(pickFirst($row, ['tld', 'extension'], '')),
        'enabled' => boolLike(pickFirst($row, ['active', 'enabled', 'status'], true), true),
        'allow_register' => boolLike(
            pickFirst($row, ['allow_register', 'allow_registration', 'registration_enabled'], true),
            true
        ),
        'allow_transfer' => boolLike(
            pickFirst($row, ['allow_transfer', 'transfer_enabled'], true),
            true
        ),
        'min_years' => (string) pickFirst($row, ['min_years'], '1'),
        'pricing' => [
            'register' => normalizeText(pickFirst($row, ['price_registration', 'register_price'], '')),
            'renew' => normalizeText(pickFirst($row, ['price_renew', 'renew_price'], '')),
            'transfer' => normalizeText(pickFirst($row, ['price_transfer', 'transfer_price'], '')),
        ],
    ];
}

function convertDomainPricingByRate(array $pricing, float $multiplier, int $decimals = 2): array
{
    return [
        'register' => (string) convertAmountByRate($pricing['register'] ?? '', $multiplier, $decimals),
        'renew' => (string) convertAmountByRate($pricing['renew'] ?? '', $multiplier, $decimals),
        'transfer' => (string) convertAmountByRate($pricing['transfer'] ?? '', $multiplier, $decimals),
    ];
}

function sanitizeAddonForPublic(array $addon, array $enabledCurrencySet): array
{
    return [
        'id' => (string) ($addon['id'] ?? ''),
        'title' => normalizeText($addon['title'] ?? ''),
        'description' => normalizeText($addon['description'] ?? ''),
        'slug' => normalizeText($addon['slug'] ?? ''),
        'order_url' => normalizeText($addon['order_url'] ?? ''),
        'icon_url' => normalizeText($addon['icon_url'] ?? ''),
        'pricing' => sanitizePricingForPublic((array) ($addon['pricing'] ?? []), $enabledCurrencySet),
        'limitations' => sanitizeLimitationsForPublic((array) ($addon['limitations'] ?? [])),
    ];
}

function sanitizeProductForPublic(array $product, array $enabledCurrencySet): array
{
    // Extract raw features from hosting plan limitations (no formatting, just data)
    $limitations = (array) ($product['limitations'] ?? []);
    $features = [];
    
    // Map limitation keys to feature names (standardized names)
    $keyMap = [
        'addon' => 'addon_domains',
        'addons' => 'addon_domains',
        'database' => 'databases',
        'databases' => 'databases',
        'db' => 'databases',
        'email' => 'email_accounts',
        'email_accounts' => 'email_accounts',
        'mailbox' => 'email_accounts',
        'ftp' => 'ftp_accounts',
        'ftp_accounts' => 'ftp_accounts',
        'cron' => 'cron_jobs',
        'cron_jobs' => 'cron_jobs',
        'subdomain' => 'subdomains',
        'subdomains' => 'subdomains',
        'disk' => 'disk',
        'inode' => 'inodes',
        'bandwidth' => 'bandwidth',
        'traffic' => 'bandwidth',
        'websites' => 'websites',
        'ram' => 'ram',
        'cpu' => 'cpu_cores',
    ];
    
    // Store features as array of {key, value} for raw data
     foreach ($limitations as $key => $value) {
         if ($value === '0' || $value === 0 || $value === '' || $value === -1 || is_bool($value)) {
             continue;
         }
         // Map to standardized key
         $lowerKey = strtolower((string) $key);
         $standardKey = null;
         foreach ($keyMap as $searchKey => $mappedKey) {
             if (strpos($lowerKey, $searchKey) !== false) {
                 $standardKey = $mappedKey;
                 break;
             }
         }
         if ($standardKey !== null) {
             $features[] = [
                 'key' => $standardKey,
                 'value' => (string) $value,
             ];
         }
     }
     
     // Reorder features: bandwidth first, then disk, then others
     $reordered = [];
     $featuresByKey = [];
     foreach ($features as $feature) {
         $featuresByKey[$feature['key']][] = $feature;
     }
     
     // Desired order
     $desiredOrder = ['disk', 'bandwidth', 'addon_domains', 'databases', 'email_accounts', 'ftp_accounts', 'subdomains', 'cron_jobs', 'inodes', 'websites', 'ram', 'cpu_cores'];
     foreach ($desiredOrder as $key) {
         if (isset($featuresByKey[$key])) {
             foreach ($featuresByKey[$key] as $feature) {
                 $reordered[] = $feature;
             }
         }
     }
     // Add any remaining features not in desired order
     foreach ($featuresByKey as $key => $features_list) {
         if (!in_array($key, $desiredOrder)) {
             foreach ($features_list as $feature) {
                 $reordered[] = $feature;
             }
         }
     }
     
     return [
        'id' => (string) ($product['id'] ?? ''),
        'title' => normalizeText($product['title'] ?? ''),
        'description' => normalizeText($product['description'] ?? ''),
        'slug' => normalizeText($product['slug'] ?? ''),
        'type' => normalizeText($product['type'] ?? ''),
        'order_url' => normalizeText($product['order_url'] ?? ''),
        'icon_url' => normalizeText($product['icon_url'] ?? ''),
        'category_id' => (string) ($product['category_id'] ?? ''),
        'category_title' => normalizeText($product['category_title'] ?? ''),
        'pricing' => sanitizePricingForPublic((array) ($product['pricing'] ?? []), $enabledCurrencySet),
        'features' => $reordered,
        'addons' => [],
    ];
}

function pickFirst(mixed $row, array $keys, mixed $fallback = null): mixed
{
    if (!is_array($row)) {
        return $fallback;
    }
    foreach ($keys as $key) {
        if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $fallback;
}

function normalizePricing(mixed $pricing): array
{
    if (!is_array($pricing)) {
        return [];
    }

    $isPricingModel = static function (array $row): bool {
        if (array_key_exists('type', $row) || array_key_exists('recurrent', $row)) {
            return true;
        }
        return array_key_exists('free', $row) || array_key_exists('once', $row);
    };

    $normalizeModel = static function (array $row): array {
        $model = [
            'type' => normalizeText($row['type'] ?? ''),
            'free' => null,
            'once' => null,
            'recurrent' => [],
        ];

        if (array_key_exists('free', $row)) {
            $model['free'] = normalizePricingPeriod($row['free']);
        }
        if (array_key_exists('once', $row)) {
            $model['once'] = normalizePricingPeriod($row['once']);
        }

        $recurrent = $row['recurrent'] ?? null;
        if (is_array($recurrent)) {
            foreach ($recurrent as $period => $entry) {
                $model['recurrent'][strtoupper((string) $period)] = normalizePricingPeriod($entry);
            }
        } else {
            // Some payloads expose periods at root-level without a "recurrent" wrapper.
            foreach ($row as $period => $entry) {
                $p = strtolower((string) $period);
                if (in_array($p, ['type', 'free', 'once'], true)) {
                    continue;
                }
                if (!is_array($entry) && !is_numeric($entry)) {
                    continue;
                }
                $model['recurrent'][strtoupper((string) $period)] = normalizePricingPeriod($entry);
            }
        }

        return $model;
    };

    // Shape A: single-currency pricing model.
    if ($isPricingModel($pricing)) {
        return ['__DEFAULT' => $normalizeModel($pricing)];
    }

    // Shape B: per-currency map where each entry is a pricing model.
    $currencies = [];
    foreach ($pricing as $currency => $value) {
        if (!is_array($value)) {
            continue;
        }
        $code = strtoupper((string) $currency);
        if ($isPricingModel($value)) {
            $currencies[$code] = $normalizeModel($value);
            continue;
        }

        // Shape C: legacy periods-per-currency map.
        $currencies[$code] = [
            'type' => '',
            'free' => isset($value['free']) ? normalizePricingPeriod($value['free']) : null,
            'once' => isset($value['once']) ? normalizePricingPeriod($value['once']) : null,
            'recurrent' => [],
        ];
        foreach ($value as $period => $periodValue) {
            $p = strtolower((string) $period);
            if (in_array($p, ['free', 'once', 'type'], true)) {
                continue;
            }
            $currencies[$code]['recurrent'][strtoupper((string) $period)] = normalizePricingPeriod($periodValue);
        }
    }

    return $currencies;
}

function normalizePricingPeriod(mixed $value): array
{
    if (is_numeric($value)) {
        return [
            'price' => (string) $value,
            'setup' => null,
            'enabled' => true,
        ];
    }

    if (!is_array($value)) {
        return [
            'price' => null,
            'setup' => null,
            'enabled' => false,
        ];
    }

    $price = pickFirst($value, ['price', 'value', 'amount', 'm_renewal_price'], null);
    $setup = pickFirst($value, ['setup', 'setup_price', 'setup_fee'], null);
    $enabledRaw = pickFirst($value, ['enabled', 'active', 'status'], true);
    $enabled = boolLike($enabledRaw, true);

    return [
        'price' => ($price === null) ? null : (string) $price,
        'setup' => ($setup === null) ? null : (string) $setup,
        'enabled' => $enabled,
    ];
}

function flattenConfig(mixed $value, string $prefix = ''): array
{
    if (!is_array($value)) {
        return [];
    }

    $result = [];
    foreach ($value as $key => $item) {
        $keyStr = ($prefix === '') ? (string) $key : ($prefix . '.' . (string) $key);
        if (is_array($item)) {
            $result += flattenConfig($item, $keyStr);
            continue;
        }

        if ($item === null || is_object($item)) {
            continue;
        }
        $result[$keyStr] = $item;
    }

    return $result;
}

function extractLimitations(mixed $config): array
{
    if (!is_array($config)) {
        return [];
    }

    $flat = flattenConfig($config);
    $limits = [];
    $pattern = '/(max|limit|quota|database|db|addon|subdomain|domain|email|mailbox|ftp|cron|disk|inode|bandwidth|traffic|ram|cpu|site|website|account)/i';

    foreach ($flat as $key => $value) {
        if (!preg_match($pattern, $key)) {
            continue;
        }
        if (is_bool($value) || is_numeric($value) || is_string($value)) {
            $limits[$key] = $value;
        }
    }

    return $limits;
}

function normalizeCategoryItem(array $row): array
{
    $id = (string) pickFirst($row, ['id', 'category_id'], '');
    $title = normalizeText(pickFirst($row, ['title', 'name', 'label'], ''));
    return [
        'id' => $id,
        'title' => $title,
        'slug' => normalizeText(pickFirst($row, ['slug'], '')),
        'description' => normalizeText(pickFirst($row, ['description'], '')),
        'icon_url' => normalizeText(pickFirst($row, ['icon_url', 'icon'], '')),
        'products' => [],
    ];
}

function normalizeProductItem(array $row, string $billingBaseUrl): array
{
    $id = (string) pickFirst($row, ['id', 'product_id'], '');
    $slug = normalizeText(pickFirst($row, ['slug'], ''));
    $existingOrderUrl = normalizeText(pickFirst($row, ['order_url', 'url'], ''));
    $orderUrl = $existingOrderUrl;
    if ($orderUrl === '' && $slug !== '') {
        $orderUrl = rtrim($billingBaseUrl, '/') . '/order/' . rawurlencode($slug);
    } elseif ($orderUrl === '') {
        $orderUrl = rtrim($billingBaseUrl, '/') . '/order?product_id=' . rawurlencode($id);
    }

    $config = pickFirst($row, ['config'], []);
    if (!is_array($config)) {
        $config = [];
    }

    $addons = pickFirst($row, ['addons', 'addon_ids'], []);
    if (!is_array($addons)) {
        $addons = [];
    }

    return [
        'id' => $id,
        'title' => normalizeText(pickFirst($row, ['title', 'name'], '')),
        'description' => normalizeText(pickFirst($row, ['description'], '')),
        'type' => normalizeText(pickFirst($row, ['type'], '')),
        'status' => normalizeText(pickFirst($row, ['status'], '')),
        'slug' => $slug,
        'order_url' => $orderUrl,
        'category_id' => (string) pickFirst($row, ['product_category_id', 'category_id'], ''),
        'icon_url' => normalizeText(pickFirst($row, ['icon_url', 'icon'], '')),
        'hidden' => boolLike(pickFirst($row, ['hidden'], false), false),
        'setup' => normalizeText(pickFirst($row, ['setup'], '')),
        'stock_control' => boolLike(pickFirst($row, ['stock_control'], false), false),
        'quantity_in_stock' => (string) pickFirst($row, ['quantity_in_stock'], ''),
        'allow_quantity_select' => boolLike(pickFirst($row, ['allow_quantity_select'], false), false),
        'pricing' => normalizePricing(pickFirst($row, ['pricing'], [])),
        'addons' => array_values($addons),
        'upgrades' => pickFirst($row, ['upgrades'], []),
        'config' => $config,
        'limitations' => extractLimitations($config),
    ];
}

function normalizeCurrencyItem(array $row, string $defaultCode): array
{
    $code = strtoupper(normalizeText(pickFirst($row, ['code', 'currency', 'currency_code'], '')));
    $apiDefault = boolLike(pickFirst($row, ['default', 'is_default'], false), false);
    $fallbackDefault = ($code !== '' && strtoupper($defaultCode) === $code);
    $priceDecimalsRaw = pickFirst($row, ['price_format', 'decimals', 'precision'], '2');
    $priceDecimals = (int) (is_numeric($priceDecimalsRaw) ? $priceDecimalsRaw : 2);
    return [
        'code' => $code,
        'title' => normalizeText(pickFirst($row, ['title', 'name'], $code)),
        'sign' => normalizeText(pickFirst($row, ['sign', 'symbol'], '')),
        'format' => normalizeText(pickFirst($row, ['format'], '')),
        'conversion_rate' => normalizeText(pickFirst($row, ['conversion_rate', 'rate'], '')),
        'price_decimals' => max(0, min(6, $priceDecimals)),
        'enabled' => boolLike(pickFirst($row, ['enabled', 'active', 'status'], true), true),
        'is_default' => ($apiDefault || $fallbackDefault),
    ];
}

function normalizeGatewayItem(array $row): array
{
    return [
        'id' => (string) pickFirst($row, ['id', 'gateway_id'], ''),
        'code' => normalizeText(pickFirst($row, ['gateway', 'code'], '')),
        'title' => normalizeText(pickFirst($row, ['title', 'name'], '')),
        'enabled' => boolLike(pickFirst($row, ['enabled', 'active'], true), true),
        'allow_single' => boolLike(pickFirst($row, ['allow_single'], true), true),
        'allow_recurrent' => boolLike(pickFirst($row, ['allow_recurrent'], true), true),
        'accepted_currencies' => is_array(pickFirst($row, ['accepted_currencies'], []))
            ? array_values((array) pickFirst($row, ['accepted_currencies'], []))
            : [],
        'config' => is_array(pickFirst($row, ['config'], []))
            ? (array) pickFirst($row, ['config'], [])
            : [],
    ];
}

function normalizeHostingPlanItem(array $row): array
{
    $config = pickFirst($row, ['config'], []);
    if (!is_array($config)) {
        $config = [];
    }
    
    // FossBilling returns plan features at top level: bandwidth, quota, max_ftp, max_sql, etc.
    // Map them to standard limitation keys
    $featureMap = [
        'bandwidth' => 'bandwidth',
        'quota' => 'disk',
        'max_ftp' => 'ftp',
        'max_sql' => 'database',
        'max_pop' => 'email',
        'max_sub' => 'subdomain',
        'max_park' => 'addon',
        'max_addon' => 'addon',
    ];
    
    $limitations = [];
    foreach ($featureMap as $apiKey => $limitationKey) {
        $value = pickFirst($row, [$apiKey], '');
        if ($value !== '' && $value !== '0' && $value !== 0) {
            $limitations[$limitationKey] = (string) $value;
        }
    }
    
    // Also extract from config if present
    $configLimitations = extractLimitations($config);
    $limitations = array_merge($limitations, $configLimitations);
    
    return [
        'id' => (string) pickFirst($row, ['id'], ''),
        'name' => normalizeText(pickFirst($row, ['name', 'title'], '')),
        'status' => normalizeText(pickFirst($row, ['status'], '')),
        'config' => $config,
        'limitations' => $limitations,
    ];
}

function mergeNonEmpty(array $base, array $incoming): array
{
    foreach ($incoming as $key => $value) {
        if (!array_key_exists($key, $base)) {
            $base[$key] = $value;
            continue;
        }
        if (is_array($base[$key]) && is_array($value)) {
            $base[$key] = mergeNonEmpty($base[$key], $value);
            continue;
        }
        if ($base[$key] === '' || $base[$key] === null || $base[$key] === []) {
            $base[$key] = $value;
        }
    }
    return $base;
}

function getThemeAssetUrl(string $envVarName, string $themeBaseUrl, string $defaultAssetPath): string
{
    $envValue = envString($envVarName);
    // If ENV variable is explicitly set to non-empty, use that override
    if ($envValue !== null && $envValue !== '') {
        return normalizeText($envValue);
    }
    
    // If no theme base URL is available, return empty
    if ($themeBaseUrl === '') {
        return '';
    }
    
    // Build full URL from theme base URL + asset path
    // This is a fallback for standard paths; actual values should come from theme customizations
    $themeBase = rtrim($themeBaseUrl, '/');
    $assetPath = ltrim($defaultAssetPath, '/');
    
    if ($assetPath === '') {
        return '';
    }
    
    return $themeBase . '/' . $assetPath;
}

function run(): int
{
    $isCliSapi = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    $isShellInvokedCgi = (
        PHP_SAPI === 'cgi-fcgi'
        && !isset($_SERVER['REQUEST_METHOD'])
        && !isset($_SERVER['REMOTE_ADDR'])
        && !empty($_SERVER['argv'])
    );

    if (!$isCliSapi && !$isShellInvokedCgi) {
        http_response_code(403);
        echo "This script is intended to run from CLI.\n";
        return 1;
    }

    loadDotEnvFile(getcwd() . '/.env');
    loadDotEnvFile(__DIR__ . '/.env');

    $options = getopt('', [
        'out::',
        'pretty::',
        'show-errors::',
        'timeout::',
        'max-pages::',
        'per-page::',
        'base-url::',
        'api-key::',
        'public-url::',
        'strict-tls::',
        'exclude-patterns::',
    ]);
    if (!is_array($options)) {
        $options = [];
    }

    $baseUrl = (string) (cliOption($options, 'base-url', envString('BILLING_BASE_URL', '')));
    $apiKey = (string) (cliOption($options, 'api-key', envString('BILLING_API_KEY', '')));
    $publicUrl = (string) (cliOption($options, 'public-url', envString('PUBLIC_SITE_URL', '')));
    $outFile = (string) (cliOption($options, 'out', envString('DATA_OUTPUT', getcwd() . '/data.json')));
    $timeout = (int) cliOption($options, 'timeout', envString('BILLING_TIMEOUT', '25'));
    $maxPages = (int) cliOption($options, 'max-pages', envString('BILLING_MAX_PAGES', '25'));
    $perPage = (int) cliOption($options, 'per-page', envString('BILLING_PER_PAGE', '100'));
    $pretty = boolLike(cliOption($options, 'pretty', envString('JSON_PRETTY', '1')), true);
    $showErrors = boolLike(cliOption($options, 'show-errors', envString('GEN_SHOW_ERRORS', '0')), false);
    $strictTls = boolLike(cliOption($options, 'strict-tls', envString('BILLING_STRICT_TLS', '1')), true);
    $excludePatterns = envCsv(
        'EXCLUDE_PRODUCT_PATTERNS',
        'tld,domain register,domain registration,domain transfer,domain renewal'
    );
    $excludePatternsOpt = cliOption($options, 'exclude-patterns', null);
    if (is_string($excludePatternsOpt) && trim($excludePatternsOpt) !== '') {
        $excludePatterns = array_values(
            array_filter(
                array_map('trim', explode(',', $excludePatternsOpt)),
                static fn (string $item): bool => $item !== ''
            )
        );
    }

    if ($apiKey === '') {
        throw new RuntimeException(
            'Missing BILLING_API_KEY. Set it in .env or pass --api-key=...'
        );
    }

    $api = new FossBillingApiClient($baseUrl, $apiKey, $timeout, $strictTls);
    $warnings = [];

    // 1) Branding / company info and theme assets.
    $company = [];
    $themeInfo = [];
    try {
        $companyResult = $api->callWithFallback('guest', ['system/company'], []);
        if (is_array($companyResult)) {
            $company = $companyResult;
        }
    } catch (Throwable $error) {
        $warnings[] = 'company: ' . $error->getMessage();
    }

    // Fetch active theme information from FossBilling guest API.
    // This gives us the theme URL and metadata for the active/default client-area theme.
    $themeSettings = [];
    try {
        $themeResult = $api->callWithFallback(
            'guest',
            ['extension/theme'],
            ['client' => 1]
        );
        if (is_array($themeResult)) {
            $themeInfo = $themeResult;
            
            // Fetch theme customizations from database via admin API.
            // FossBilling stores theme settings in extension_meta table, accessible via
            // admin/extension/config_get with ext=theme_<theme_code>
            $themeCode = normalizeText(pickFirst($themeResult, ['code', 'name'], ''));
            if ($themeCode !== '') {
                try {
                    $themeConfigResult = $api->callWithFallback(
                        'admin',
                        ['extension/config_get'],
                        ['ext' => 'theme_' . $themeCode]
                    );
                    if (is_array($themeConfigResult)) {
                        $themeSettings = $themeConfigResult;
                    }
                } catch (Throwable $configError) {
                    $warnings[] = 'theme_config_get: ' . $configError->getMessage();
                }
            }
        }
    } catch (Throwable $error) {
        // Theme info is optional - warn but don't fail
        $warnings[] = 'theme_info: ' . $error->getMessage();
    }

    // 2) Categories
    $categoriesById = [];
    try {
        $pairsRaw = $api->callWithFallback('admin', ['product/category_get_pairs'], []);
        foreach (flattenPairs($pairsRaw) as $id => $title) {
            $categoriesById[$id] = [
                'id' => (string) $id,
                'title' => (string) $title,
                'slug' => '',
                'description' => '',
                'icon_url' => '',
                'products' => [],
            ];
        }
    } catch (Throwable $error) {
        $warnings[] = 'category_pairs: ' . $error->getMessage();
    }

    try {
        $categoryList = fetchPaginated(
            $api,
            'guest',
            ['product/category_get_list'],
            [],
            $perPage,
            $maxPages
        );
        foreach ($categoryList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeCategoryItem($row);
            if ($normalized['id'] === '') {
                continue;
            }
            $existing = $categoriesById[$normalized['id']] ?? [];
            $categoriesById[$normalized['id']] = mergeNonEmpty($existing, $normalized);
        }
    } catch (Throwable $error) {
        $warnings[] = 'category_list: ' . $error->getMessage();
    }

    // 3) Products
    $productsById = [];
    try {
        $productList = fetchPaginated(
            $api,
            'admin',
            ['product/get_list'],
            ['show_hidden' => true],
            $perPage,
            $maxPages
        );
        if ($productList === []) {
            $productList = fetchPaginated(
                $api,
                'guest',
                ['product/get_list'],
                ['show_hidden' => true],
                $perPage,
                $maxPages
            );
        }

        foreach ($productList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeProductItem($row, $baseUrl);
            if ($normalized['id'] === '') {
                continue;
            }
            $productsById[$normalized['id']] = $normalized;
        }

        // Enrich each product with detailed endpoint data.
        foreach (array_keys($productsById) as $productId) {
            try {
                $details = $api->callWithFallback(
                    'admin',
                    ['product/get'],
                    ['id' => $productId]
                );
                if (is_array($details)) {
                    $merged = normalizeProductItem($details, $baseUrl);
                    $productsById[$productId] = mergeNonEmpty($merged, $productsById[$productId]);
                }
            } catch (Throwable $error) {
                // Guest fallback may still provide some details.
                try {
                    $details = $api->callWithFallback(
                        'guest',
                        ['product/get'],
                        ['id' => $productId]
                    );
                    if (is_array($details)) {
                        $merged = normalizeProductItem($details, $baseUrl);
                        $productsById[$productId] = mergeNonEmpty($merged, $productsById[$productId]);
                    }
                } catch (Throwable $guestError) {
                    $warnings[] = sprintf(
                        'product_%s_details: %s',
                        $productId,
                        $guestError->getMessage()
                    );
                }
            }
        }
    } catch (Throwable $error) {
        $warnings[] = 'products: ' . $error->getMessage();
    }

    // 4) Addons
    $addonsById = [];
    try {
        $addonPairsRaw = $api->callWithFallback('admin', ['product/addon_get_pairs'], []);
        $addonPairs = flattenPairs($addonPairsRaw);
        foreach ($addonPairs as $addonId => $addonTitle) {
            $addonsById[$addonId] = [
                'id' => (string) $addonId,
                'title' => (string) $addonTitle,
                'status' => '',
                'pricing' => [],
                'config' => [],
                'limitations' => [],
            ];
        }

        foreach (array_keys($addonsById) as $addonId) {
            try {
                $addon = $api->callWithFallback('admin', ['product/addon_get'], ['id' => $addonId]);
                if (is_array($addon)) {
                    $normalized = normalizeProductItem($addon, $publicUrl);
                    $addonsById[$addonId] = mergeNonEmpty($addonsById[$addonId], $normalized);
                }
            } catch (Throwable $error) {
                $warnings[] = sprintf('addon_%s: %s', $addonId, $error->getMessage());
            }
        }
    } catch (Throwable $error) {
        $warnings[] = 'addons: ' . $error->getMessage();
    }

    // 5) Hosting plans (useful for extracting plan limitations)
    $hostingPlansById = [];
    try {
        $hpList = fetchPaginated(
            $api,
            'admin',
            ['servicehosting/hp_get_list'],
            [],
            $perPage,
            $maxPages
        );
        foreach ($hpList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeHostingPlanItem($row);
            if ($normalized['id'] === '') {
                continue;
            }
            $hostingPlansById[$normalized['id']] = $normalized;
        }

        foreach (array_keys($hostingPlansById) as $hpId) {
            try {
                $hpDetails = $api->callWithFallback('admin', ['servicehosting/hp_get'], ['id' => $hpId]);
                if (is_array($hpDetails)) {
                    $normalized = normalizeHostingPlanItem($hpDetails);
                    $hostingPlansById[$hpId] = mergeNonEmpty($hostingPlansById[$hpId], $normalized);
                }
            } catch (Throwable $error) {
                $warnings[] = sprintf('hosting_plan_%s: %s', $hpId, $error->getMessage());
            }
        }
    } catch (Throwable $error) {
        $warnings[] = 'hosting_plans: ' . $error->getMessage();
    }

    // Attach hosting plan limits to products when a plan reference exists.
    $hostingPlansByName = [];
    foreach ($hostingPlansById as $hp) {
        if (($hp['name'] ?? '') !== '') {
            $hostingPlansByName[strtolower((string) $hp['name'])] = $hp;
        }
    }

    foreach ($productsById as $id => $product) {
        $config = is_array($product['config'] ?? null) ? $product['config'] : [];
        $planRef = pickFirst($config, ['plan_id', 'hosting_plan_id', 'hp_id', 'service_hosting_hp_id'], '');
        $planNameRef = normalizeText(pickFirst($config, ['plan', 'hosting_plan', 'name'], ''));
        $hp = null;
        if ($planRef !== '' && isset($hostingPlansById[(string) $planRef])) {
            $hp = $hostingPlansById[(string) $planRef];
        } elseif ($planNameRef !== '') {
            $hp = $hostingPlansByName[strtolower($planNameRef)] ?? null;
        }

        if (is_array($hp)) {
            $product['hosting_plan'] = [
                'id' => (string) ($hp['id'] ?? ''),
                'name' => (string) ($hp['name'] ?? ''),
            ];
            // Merge hosting plan limitations with product limitations (product ones take precedence)
            $mergedLimitations = is_array($hp['limitations'] ?? null) ? $hp['limitations'] : [];
            if (is_array($product['limitations'] ?? null)) {
                foreach ($product['limitations'] as $key => $value) {
                    if ($value !== '' && $value !== '0' && $value !== 0) {
                        $mergedLimitations[$key] = $value;
                    }
                }
            }
            $product['limitations'] = $mergedLimitations;
            $productsById[$id] = $product;
        }
    }

    // 6) Currencies
    $defaultCurrencyCode = '';
    $currenciesByCode = [];
    try {
        $defaultCurrencyRaw = $api->callWithFallback('admin', ['currency/get_default'], []);
        if (is_array($defaultCurrencyRaw)) {
            $defaultCurrencyCode = strtoupper((string) pickFirst($defaultCurrencyRaw, ['code', 'currency', 'currency_code'], ''));
        }
    } catch (Throwable $error) {
        $warnings[] = 'currency_default: ' . $error->getMessage();
    }

    try {
        $currencyList = fetchPaginated(
            $api,
            'admin',
            ['currency/get_list'],
            [],
            $perPage,
            $maxPages
        );
        foreach ($currencyList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeCurrencyItem($row, $defaultCurrencyCode);
            if ($normalized['code'] === '') {
                continue;
            }
            $currenciesByCode[$normalized['code']] = $normalized;
        }
    } catch (Throwable $error) {
        $warnings[] = 'currency_list: ' . $error->getMessage();
    }

    if ($currenciesByCode === []) {
        try {
            $pairs = $api->callWithFallback('guest', ['currency/get_pairs'], []);
            foreach (flattenPairs($pairs) as $code => $title) {
                $currenciesByCode[strtoupper($code)] = [
                    'code' => strtoupper((string) $code),
                    'title' => (string) $title,
                    'sign' => '',
                    'format' => '',
                    'conversion_rate' => (strtoupper((string) $code) === $defaultCurrencyCode) ? '1' : '',
                    'price_decimals' => 2,
                    'enabled' => true,
                    'is_default' => (strtoupper((string) $code) === $defaultCurrencyCode),
                ];
            }
        } catch (Throwable $error) {
            $warnings[] = 'currency_pairs: ' . $error->getMessage();
        }
    }

    // 6.1) Domain TLD catalog and registration product slug
     $domainTldsByKey = [];
     $domainRegistrationProductSlug = '';
     $domainLoaded = false;
     
     // Find domain registration product slug before filtering out domain products
     if ($domainRegistrationProductSlug === '') {
         foreach ($productsById as $productId => $product) {
             if (!is_array($product)) {
                 continue;
             }
             $type = lowerText($product['type'] ?? '');
             if (($type === 'domain' || $type === 'tld') && statusIsPublic($product['status'] ?? '', true)) {
                 $slug = normalizeText($product['slug'] ?? '');
                 if ($slug !== '') {
                     $domainRegistrationProductSlug = $slug;
                     break;
                 }
             }
         }
     }
     
     try {
        $tldList = fetchPaginated(
            $api,
            'admin',
            ['servicedomain/tld_get_list'],
            [],
            $perPage,
            $maxPages
        );
        foreach ($tldList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeDomainTldItem($row);
            $tldKey = strtolower($normalized['tld']);
            if ($tldKey === '' || !$normalized['enabled']) {
                continue;
            }
            $domainTldsByKey[$tldKey] = $normalized;
        }
        $domainLoaded = true;
    } catch (Throwable $error) {
        $warnings[] = 'domain_tlds_admin: ' . $error->getMessage();
    }
    if (!$domainLoaded) {
        try {
            $tldList = fetchPaginated(
                $api,
                'guest',
                ['servicedomain/tld_get_list'],
                [],
                $perPage,
                $maxPages
            );
            foreach ($tldList as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalized = normalizeDomainTldItem($row);
                $tldKey = strtolower($normalized['tld']);
                if ($tldKey === '' || !$normalized['enabled']) {
                    continue;
                }
                $domainTldsByKey[$tldKey] = $normalized;
            }
        } catch (Throwable $error) {
            $warnings[] = 'domain_tlds_guest: ' . $error->getMessage();
        }
    }

    // Refresh product/addon pricing per enabled currency for accurate UI output.
    $enabledCurrencyCodesForPricing = [];
    $currencyRateByCode = [];
    $currencyDecimalsByCode = [];
    $effectiveDefaultCurrencyCode = strtoupper($defaultCurrencyCode);

    foreach ($currenciesByCode as $currencyCode => $currencyRow) {
        $code = strtoupper((string) $currencyCode);
        if (!boolLike($currencyRow['enabled'] ?? true, true)) {
            continue;
        }

        $enabledCurrencyCodesForPricing[] = $code;
        $rateFloat = decimalToFloat((string) ($currencyRow['conversion_rate'] ?? ''));
        if ($rateFloat !== null && $rateFloat > 0) {
            $currencyRateByCode[$code] = $rateFloat;
        }
        $currencyDecimalsByCode[$code] = (int) ($currencyRow['price_decimals'] ?? 2);

        if (boolLike($currencyRow['is_default'] ?? false, false)) {
            $effectiveDefaultCurrencyCode = $code;
        }
    }

    $enabledCurrencyCodesForPricing = array_values(array_unique($enabledCurrencyCodesForPricing));
    if ($effectiveDefaultCurrencyCode === '' && $enabledCurrencyCodesForPricing !== []) {
        $effectiveDefaultCurrencyCode = $enabledCurrencyCodesForPricing[0];
    }
    if ($effectiveDefaultCurrencyCode !== '' && !isset($currencyRateByCode[$effectiveDefaultCurrencyCode])) {
        $currencyRateByCode[$effectiveDefaultCurrencyCode] = 1.0;
    }

    $publicDomainsByTld = [];
    foreach ($domainTldsByKey as $tldKey => $domainRow) {
        if (!is_array($domainRow) || !boolLike($domainRow['enabled'] ?? true, true)) {
            continue;
        }

        $basePricing = is_array($domainRow['pricing'] ?? null) ? $domainRow['pricing'] : [];
        $pricingByCurrency = [];
        foreach ($enabledCurrencyCodesForPricing as $currencyCode) {
            $decimals = $currencyDecimalsByCode[$currencyCode] ?? 2;
            if (
                $effectiveDefaultCurrencyCode !== '' &&
                isset($currencyRateByCode[$currencyCode]) &&
                isset($currencyRateByCode[$effectiveDefaultCurrencyCode]) &&
                $currencyRateByCode[$effectiveDefaultCurrencyCode] > 0
            ) {
                $multiplier = $currencyRateByCode[$currencyCode] / $currencyRateByCode[$effectiveDefaultCurrencyCode];
                $pricingByCurrency[$currencyCode] = convertDomainPricingByRate($basePricing, $multiplier, $decimals);
            } else {
                $pricingByCurrency[$currencyCode] = [
                    'register' => (string) ($basePricing['register'] ?? ''),
                    'renew' => (string) ($basePricing['renew'] ?? ''),
                    'transfer' => (string) ($basePricing['transfer'] ?? ''),
                ];
            }
        }

        $publicDomainsByTld[$tldKey] = [
            'id' => (string) ($domainRow['id'] ?? ''),
            'tld' => (string) ($domainRow['tld'] ?? ''),
            'enabled' => boolLike($domainRow['enabled'] ?? true, true),
            'allow_register' => boolLike($domainRow['allow_register'] ?? true, true),
            'allow_transfer' => boolLike($domainRow['allow_transfer'] ?? true, true),
            'min_years' => (string) ($domainRow['min_years'] ?? '1'),
            'pricing' => $pricingByCurrency,
        ];
    }

    $pickPricingModelForCurrency = static function (array $normalizedPricing, string $currencyCode): ?array {
        if (isset($normalizedPricing[$currencyCode]) && is_array($normalizedPricing[$currencyCode])) {
            return $normalizedPricing[$currencyCode];
        }
        if (isset($normalizedPricing['__DEFAULT']) && is_array($normalizedPricing['__DEFAULT'])) {
            return $normalizedPricing['__DEFAULT'];
        }
        if ($normalizedPricing !== []) {
            $first = reset($normalizedPricing);
            if (is_array($first)) {
                return $first;
            }
        }
        return null;
    };

    if ($enabledCurrencyCodesForPricing !== []) {
        foreach ($productsById as $productId => $product) {
            if (!is_array($product)) {
                continue;
            }

            $pricingByCurrency = [];
            foreach ($enabledCurrencyCodesForPricing as $currencyCode) {
                try {
                    $details = $api->callWithFallback(
                        'admin',
                        ['product/get'],
                        ['id' => $productId, 'currency' => $currencyCode]
                    );
                    if (!is_array($details)) {
                        continue;
                    }
                    $normalizedPricing = normalizePricing(pickFirst($details, ['pricing'], []));
                    $picked = $pickPricingModelForCurrency($normalizedPricing, $currencyCode);
                    if ($picked !== null) {
                        $pricingByCurrency[$currencyCode] = $picked;
                    }
                } catch (Throwable $error) {
                    $warnings[] = sprintf(
                        'product_%s_pricing_%s: %s',
                        (string) $productId,
                        $currencyCode,
                        $error->getMessage()
                    );
                }
            }

            $baseModel = null;
            if (
                $effectiveDefaultCurrencyCode !== '' &&
                isset($pricingByCurrency[$effectiveDefaultCurrencyCode]) &&
                is_array($pricingByCurrency[$effectiveDefaultCurrencyCode])
            ) {
                $baseModel = $pricingByCurrency[$effectiveDefaultCurrencyCode];
            } else {
                $existing = is_array($product['pricing'] ?? null) ? $product['pricing'] : [];
                $baseModel = $pickPricingModelForCurrency($existing, $effectiveDefaultCurrencyCode);
            }

            if (is_array($baseModel) && $effectiveDefaultCurrencyCode !== '') {
                foreach ($enabledCurrencyCodesForPricing as $currencyCode) {
                    if ($currencyCode === $effectiveDefaultCurrencyCode) {
                        $pricingByCurrency[$currencyCode] = $baseModel;
                        continue;
                    }

                    if (
                        !isset($currencyRateByCode[$currencyCode]) ||
                        !isset($currencyRateByCode[$effectiveDefaultCurrencyCode]) ||
                        $currencyRateByCode[$effectiveDefaultCurrencyCode] <= 0
                    ) {
                        continue;
                    }

                    $shouldConvert =
                        !isset($pricingByCurrency[$currencyCode]) ||
                        !is_array($pricingByCurrency[$currencyCode]) ||
                        pricingModelsEqual($pricingByCurrency[$currencyCode], $baseModel);
                    if (!$shouldConvert) {
                        continue;
                    }

                    $multiplier = $currencyRateByCode[$currencyCode] / $currencyRateByCode[$effectiveDefaultCurrencyCode];
                    $decimals = $currencyDecimalsByCode[$currencyCode] ?? 2;
                    $pricingByCurrency[$currencyCode] = convertPricingModelByRate($baseModel, $multiplier, $decimals);
                }
            }

            if ($pricingByCurrency !== []) {
                $product['pricing'] = $pricingByCurrency;
                $productsById[$productId] = $product;
            }
        }

        foreach ($addonsById as $addonId => $addon) {
            if (!is_array($addon)) {
                continue;
            }

            $pricingByCurrency = [];
            foreach ($enabledCurrencyCodesForPricing as $currencyCode) {
                try {
                    $details = $api->callWithFallback(
                        'admin',
                        ['product/addon_get'],
                        ['id' => $addonId, 'currency' => $currencyCode]
                    );
                    if (!is_array($details)) {
                        continue;
                    }
                    $normalizedPricing = normalizePricing(pickFirst($details, ['pricing'], []));
                    $picked = $pickPricingModelForCurrency($normalizedPricing, $currencyCode);
                    if ($picked !== null) {
                        $pricingByCurrency[$currencyCode] = $picked;
                    }
                } catch (Throwable $error) {
                    $warnings[] = sprintf(
                        'addon_%s_pricing_%s: %s',
                        (string) $addonId,
                        $currencyCode,
                        $error->getMessage()
                    );
                }
            }

            $baseModel = null;
            if (
                $effectiveDefaultCurrencyCode !== '' &&
                isset($pricingByCurrency[$effectiveDefaultCurrencyCode]) &&
                is_array($pricingByCurrency[$effectiveDefaultCurrencyCode])
            ) {
                $baseModel = $pricingByCurrency[$effectiveDefaultCurrencyCode];
            } else {
                $existing = is_array($addon['pricing'] ?? null) ? $addon['pricing'] : [];
                $baseModel = $pickPricingModelForCurrency($existing, $effectiveDefaultCurrencyCode);
            }

            if (is_array($baseModel) && $effectiveDefaultCurrencyCode !== '') {
                foreach ($enabledCurrencyCodesForPricing as $currencyCode) {
                    if ($currencyCode === $effectiveDefaultCurrencyCode) {
                        $pricingByCurrency[$currencyCode] = $baseModel;
                        continue;
                    }

                    if (
                        !isset($currencyRateByCode[$currencyCode]) ||
                        !isset($currencyRateByCode[$effectiveDefaultCurrencyCode]) ||
                        $currencyRateByCode[$effectiveDefaultCurrencyCode] <= 0
                    ) {
                        continue;
                    }

                    $shouldConvert =
                        !isset($pricingByCurrency[$currencyCode]) ||
                        !is_array($pricingByCurrency[$currencyCode]) ||
                        pricingModelsEqual($pricingByCurrency[$currencyCode], $baseModel);
                    if (!$shouldConvert) {
                        continue;
                    }

                    $multiplier = $currencyRateByCode[$currencyCode] / $currencyRateByCode[$effectiveDefaultCurrencyCode];
                    $decimals = $currencyDecimalsByCode[$currencyCode] ?? 2;
                    $pricingByCurrency[$currencyCode] = convertPricingModelByRate($baseModel, $multiplier, $decimals);
                }
            }

            if ($pricingByCurrency !== []) {
                $addon['pricing'] = $pricingByCurrency;
                $addonsById[$addonId] = $addon;
            }
        }
    }

    // 7) Gateways
    $gatewaysById = [];
    try {
        $gatewayList = fetchPaginated(
            $api,
            'admin',
            ['invoice/gateway_get_list'],
            [],
            $perPage,
            $maxPages
        );
        foreach ($gatewayList as $row) {
            if (!is_array($row)) {
                continue;
            }
            $normalized = normalizeGatewayItem($row);
            $id = $normalized['id'] !== '' ? $normalized['id'] : ($normalized['code'] ?: uniqid('gw_', true));
            $gatewaysById[$id] = $normalized;
        }
    } catch (Throwable $error) {
        $warnings[] = 'gateway_list: ' . $error->getMessage();
    }

    if ($gatewaysById === []) {
        try {
            $gatewayPairs = $api->callWithFallback('admin', ['invoice/gateway_get_pairs'], []);
            foreach (flattenPairs($gatewayPairs) as $id => $title) {
                $gatewaysById[(string) $id] = [
                    'id' => (string) $id,
                    'code' => '',
                    'title' => (string) $title,
                    'enabled' => true,
                    'allow_single' => true,
                    'allow_recurrent' => true,
                    'accepted_currencies' => [],
                    'config' => [],
                ];
            }
        } catch (Throwable $error) {
            $warnings[] = 'gateway_pairs: ' . $error->getMessage();
        }
    }

    if ($gatewaysById === []) {
        try {
            $guestGateways = $api->callWithFallback('guest', ['invoice/gateways'], []);
            $guestGatewayList = asList($guestGateways);
            foreach ($guestGatewayList as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $normalized = normalizeGatewayItem($row);
                $id = $normalized['id'] !== '' ? $normalized['id'] : ($normalized['code'] ?: uniqid('gw_', true));
                $gatewaysById[$id] = $normalized;
            }
        } catch (Throwable $error) {
            $warnings[] = 'guest_gateways: ' . $error->getMessage();
        }
    }

    // Build strict UI-only data from fetched records.
    $enabledCurrencySet = [];
    $publicCurrenciesByCode = [];
    $currencyRatesToDefault = [];
    $defaultCurrencyForRates = '';
    foreach ($currenciesByCode as $code => $currency) {
        if (!boolLike($currency['enabled'] ?? true, true)) {
            continue;
        }
        $currencyCode = strtoupper((string) $code);
        $enabledCurrencySet[$currencyCode] = true;

        $isDefaultCurrency = boolLike($currency['is_default'] ?? false, false);
        if ($isDefaultCurrency) {
            $defaultCurrencyForRates = $currencyCode;
        }

        $rateText = normalizeText($currency['conversion_rate'] ?? '');
        if ($rateText === '' && $isDefaultCurrency) {
            $rateText = '1';
        }
        $rateFloat = decimalToFloat($rateText);
        if ($rateFloat !== null && $rateFloat > 0) {
            $currencyRatesToDefault[$currencyCode] = $rateFloat;
        }

        $publicCurrenciesByCode[$currencyCode] = [
            'code' => $currencyCode,
            'title' => normalizeText($currency['title'] ?? $currencyCode),
            'sign' => normalizeText($currency['sign'] ?? ''),
            'format' => normalizeText($currency['format'] ?? ''),
            'conversion_rate' => $rateText,
            'is_default' => $isDefaultCurrency,
        ];
    }

    if ($defaultCurrencyForRates === '' && $defaultCurrencyCode !== '' && isset($enabledCurrencySet[$defaultCurrencyCode])) {
        $defaultCurrencyForRates = $defaultCurrencyCode;
    }
    if ($defaultCurrencyForRates === '' && $publicCurrenciesByCode !== []) {
        $defaultCurrencyForRates = (string) array_key_first($publicCurrenciesByCode);
    }
    if ($defaultCurrencyForRates !== '' && !isset($currencyRatesToDefault[$defaultCurrencyForRates])) {
        $currencyRatesToDefault[$defaultCurrencyForRates] = 1.0;
        if (isset($publicCurrenciesByCode[$defaultCurrencyForRates])) {
            $publicCurrenciesByCode[$defaultCurrencyForRates]['conversion_rate'] = '1';
            $publicCurrenciesByCode[$defaultCurrencyForRates]['is_default'] = true;
        }
    }

    $currencyRelations = [];
    foreach ($currencyRatesToDefault as $fromCode => $fromRate) {
        if ($fromRate <= 0) {
            continue;
        }
        $currencyRelations[$fromCode] = [];
        foreach ($currencyRatesToDefault as $toCode => $toRate) {
            if ($toRate <= 0) {
                continue;
            }
            $currencyRelations[$fromCode][$toCode] = formatDecimal($toRate / $fromRate, 8);
        }
        ksort($currencyRelations[$fromCode]);
    }

    $publicAddonsById = [];
    foreach ($addonsById as $addonId => $addon) {
        if (!is_array($addon) || !isPublicAddon($addon, $excludePatterns)) {
            continue;
        }
        $sanitized = sanitizeAddonForPublic($addon, $enabledCurrencySet);
        if ($sanitized['id'] === '' || $sanitized['title'] === '') {
            continue;
        }
        $publicAddonsById[(string) $addonId] = $sanitized;
    }

    // Build category -> products relation and annotate category titles in products.
    foreach ($productsById as $productId => $product) {
        $categoryId = (string) ($product['category_id'] ?? '');
        if ($categoryId !== '' && isset($categoriesById[$categoryId])) {
            $categoriesById[$categoryId]['products'][] = $productId;
            $productsById[$productId]['category_title'] = $categoriesById[$categoryId]['title'];
        } else {
            $productsById[$productId]['category_title'] = '';
        }
    }

    $publicProductsById = [];
    foreach ($productsById as $productId => $product) {
        if (!is_array($product) || !isPublicProduct($product, $excludePatterns)) {
            continue;
        }
        $sanitized = sanitizeProductForPublic($product, $enabledCurrencySet);
        if ($sanitized['id'] === '' || $sanitized['title'] === '') {
            continue;
        }

        $addonIds = [];
        foreach ((array) ($product['addons'] ?? []) as $addonRef) {
            $id = (string) $addonRef;
            if ($id !== '' && isset($publicAddonsById[$id])) {
                $addonIds[] = $id;
            }
        }
        $sanitized['addons'] = array_values(array_unique($addonIds));
        $publicProductsById[(string) $productId] = $sanitized;
    }

    $publicCategoriesById = [];
    foreach ($categoriesById as $categoryId => $category) {
        if (!is_array($category)) {
            continue;
        }
        $productIds = array_values(array_unique(array_map('strval', (array) ($category['products'] ?? []))));
        $productIds = array_values(array_filter(
            $productIds,
            static fn (string $id): bool => isset($publicProductsById[$id])
        ));
        if ($productIds === []) {
            continue;
        }
        $publicCategoriesById[(string) $categoryId] = [
            'id' => (string) ($category['id'] ?? $categoryId),
            'title' => normalizeText($category['title'] ?? ''),
            'slug' => normalizeText($category['slug'] ?? ''),
            'description' => normalizeText($category['description'] ?? ''),
            'icon_url' => normalizeText($category['icon_url'] ?? ''),
            'products' => $productIds,
        ];
    }

    $publicGatewaysById = [];
    foreach ($gatewaysById as $gatewayId => $gateway) {
        if (!is_array($gateway) || !boolLike($gateway['enabled'] ?? true, true)) {
            continue;
        }
        $acceptedCurrencies = [];
        foreach ((array) ($gateway['accepted_currencies'] ?? []) as $code) {
            $currencyCode = strtoupper(normalizeText($code));
            if ($currencyCode !== '' && isset($enabledCurrencySet[$currencyCode])) {
                $acceptedCurrencies[] = $currencyCode;
            }
        }
        $publicGatewaysById[(string) $gatewayId] = [
            'id' => (string) ($gateway['id'] ?? $gatewayId),
            'code' => normalizeText($gateway['code'] ?? ''),
            'title' => normalizeText($gateway['title'] ?? ''),
            'allow_single' => boolLike($gateway['allow_single'] ?? true, true),
            'allow_recurrent' => boolLike($gateway['allow_recurrent'] ?? true, true),
            'accepted_currencies' => array_values(array_unique($acceptedCurrencies)),
        ];
    }

    // Ensure deterministic output ordering.
    ksort($publicCategoriesById);
    ksort($publicProductsById);
    ksort($publicAddonsById);
    ksort($publicCurrenciesByCode);
    ksort($publicDomainsByTld);
    ksort($publicGatewaysById);

    // Build custom assets from ENV variables (optional overrides)
    $customAssets = [];
    $envLogoUrl = envString('SITE_LOGO_URL');
    $envLogoDarkUrl = envString('SITE_LOGO_DARK_URL');
    $envFaviconUrl = envString('SITE_FAVICON_URL');
    $envHeaderBgUrl = envString('SITE_HEADER_BG_URL');
    $envFooterBgUrl = envString('SITE_FOOTER_BG_URL');
    
    if ($envLogoUrl !== null && $envLogoUrl !== '') {
        $customAssets['logo_url'] = $envLogoUrl;
    }
    if ($envLogoDarkUrl !== null && $envLogoDarkUrl !== '') {
        $customAssets['logo_dark_url'] = $envLogoDarkUrl;
    }
    if ($envFaviconUrl !== null && $envFaviconUrl !== '') {
        $customAssets['favicon_url'] = $envFaviconUrl;
    }
    if ($envHeaderBgUrl !== null && $envHeaderBgUrl !== '') {
        $customAssets['header_bg_url'] = $envHeaderBgUrl;
    }
    if ($envFooterBgUrl !== null && $envFooterBgUrl !== '') {
        $customAssets['footer_bg_url'] = $envFooterBgUrl;
    }

    // ENV overrides for branding (optional)
    $envMotto = envString('SITE_MOTTO');
    $envBrandMark = envString('SITE_BRAND_MARK');
    
    $meta = [
        'generated_at' => gmdate('c'),
        'generator' => 'fossbilling-static-site-gen',
        'public_site_url' => $publicUrl,
        'billing_base_url' => $baseUrl,
        'default_currency' => $defaultCurrencyForRates,
        'custom_assets' => $customAssets !== [] ? $customAssets : null,
        'counts' => [
            'categories' => count($publicCategoriesById),
            'products' => count($publicProductsById),
            'addons' => count($publicAddonsById),
            'currencies' => count($publicCurrenciesByCode),
            'domains' => count($publicDomainsByTld),
            'gateways' => count($publicGatewaysById),
        ],
    ];

    $publicRateMap = [];
    foreach ($currencyRatesToDefault as $code => $rate) {
        $publicRateMap[(string) $code] = formatDecimal((float) $rate, 8);
    }
    ksort($publicRateMap);

    // Get motto from: ENV override → FossBilling signature → empty
    $companySignature = normalizeText(pickFirst($company, ['signature'], ''));
    $motto = ($envMotto !== null && $envMotto !== '') ? $envMotto : $companySignature;
    
    // Get brand_mark from ENV only (optional visual element)
    $brandMark = ($envBrandMark !== null && $envBrandMark !== '') ? $envBrandMark : '';

    $payload = [
        'meta' => $meta,
        'branding' => [
            'company' => [
                'name' => normalizeText(pickFirst($company, ['name'], '')),
                'email' => normalizeText(pickFirst($company, ['email'], '')),
                'phone' => normalizeText(pickFirst($company, ['tel', 'phone'], '')),
                'www' => normalizeText(pickFirst($company, ['www', 'website'], '')),
                'address' => normalizeText(pickFirst($company, ['address_1', 'address'], '')),
                'city' => normalizeText(pickFirst($company, ['city'], '')),
                'country' => normalizeText(pickFirst($company, ['country'], '')),
            ],
            'motto' => $motto,
            'brand_mark' => $brandMark,
            'clientarea_url' => rtrim($baseUrl, '/'),
            'theme' => [
                'name' => normalizeText(pickFirst($themeInfo, ['name'], '')),
                'code' => normalizeText(pickFirst($themeInfo, ['code'], '')),
                'version' => normalizeText(pickFirst($themeInfo, ['version'], '')),
                'url' => normalizeText(pickFirst($themeInfo, ['url'], '')),
            ],
            'assets' => [
                'logo_url' => normalizeText(pickFirst($company, ['logo_url'], ''))
                    ?: normalizeText(pickFirst($themeSettings, ['login_page_logo_url', 'logo_url'], ''))
                    ?: getThemeAssetUrl('', normalizeText(pickFirst($themeInfo, ['url'], '')), 'assets/logo.svg'),
                'logo_dark_url' => normalizeText(pickFirst($company, ['logo_url_dark'], ''))
                    ?: normalizeText(pickFirst($themeSettings, ['logo_dark_url'], ''))
                    ?: getThemeAssetUrl('', normalizeText(pickFirst($themeInfo, ['url'], '')), 'assets/logo-dark.svg'),
                'favicon_url' => normalizeText(pickFirst($company, ['favicon_url'], ''))
                    ?: normalizeText(pickFirst($themeSettings, ['favicon_url'], ''))
                    ?: getThemeAssetUrl('', normalizeText(pickFirst($themeInfo, ['url'], '')), 'assets/favicon.svg'),
                'header_bg_url' => normalizeText(pickFirst($themeSettings, ['header_bg_url', 'header_background'], ''))
                    ?: getThemeAssetUrl('', normalizeText(pickFirst($themeInfo, ['url'], '')), 'assets/header-bg.jpg'),
                'footer_bg_url' => normalizeText(pickFirst($themeSettings, ['footer_bg_url', 'footer_background'], ''))
                    ?: getThemeAssetUrl('', normalizeText(pickFirst($themeInfo, ['url'], '')), 'assets/footer-bg.jpg'),
            ],
            'footer_content' => normalizeText(pickFirst($themeSettings, ['footer_content', 'footer_html'], '')),
        ],
        'categories' => array_values($publicCategoriesById),
        'products' => array_values($publicProductsById),
        'addons' => array_values($publicAddonsById),
        'currencies' => array_values($publicCurrenciesByCode),
        'domains' => array_values($publicDomainsByTld),
        'currency_rates' => [
             'base_currency' => $defaultCurrencyForRates,
             'rates_to_base' => $publicRateMap,
             'relations' => $currencyRelations,
         ],
         'gateways' => array_values($publicGatewaysById),
         'domain_registration_slug' => $domainRegistrationProductSlug,
        ];

    if ($showErrors && $warnings !== []) {
        fwrite(STDERR, "[gen.php] warnings:\n");
        foreach ($warnings as $warning) {
            fwrite(STDERR, " - " . $warning . "\n");
        }
    }

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if ($pretty) {
        $flags |= JSON_PRETTY_PRINT;
    }
    $json = json_encode($payload, $flags);
    if ($json === false) {
        throw new RuntimeException('Failed to encode data.json payload');
    }

    $outDir = dirname($outFile);
    if (!is_dir($outDir)) {
        if (!mkdir($outDir, 0775, true) && !is_dir($outDir)) {
            throw new RuntimeException('Unable to create output directory: ' . $outDir);
        }
    }

    if (file_put_contents($outFile, $json . PHP_EOL) === false) {
        throw new RuntimeException('Failed to write output file: ' . $outFile);
    }

    fwrite(STDOUT, sprintf("Generated %s\n", $outFile));
    return 0;
}

try {
    exit(run());
} catch (Throwable $error) {
    fwrite(STDERR, '[gen.php] ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
