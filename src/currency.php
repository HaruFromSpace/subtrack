<?php
// Currencies, exchange rates and converting between them.

declare(strict_types=1);

// Rough built-in rates (units per 1 USD), only used when live rates can't be fetched
// and none were saved before. The Gulf currencies and JOD are pegged, so they stay right.
const CURRENCIES = [
    'JOD' => ['Jordanian dinar', 0.709],
    'USD' => ['US dollar', 1.0],
    'EUR' => ['Euro', 0.92],
    'GBP' => ['British pound', 0.78],
    'SAR' => ['Saudi riyal', 3.75],
    'AED' => ['UAE dirham', 3.6725],
    'QAR' => ['Qatari riyal', 3.64],
    'KWD' => ['Kuwaiti dinar', 0.307],
    'BHD' => ['Bahraini dinar', 0.376],
    'OMR' => ['Omani rial', 0.3845],
    'EGP' => ['Egyptian pound', 48.5],
    'CAD' => ['Canadian dollar', 1.37],
    'AUD' => ['Australian dollar', 1.52],
];

const RATES_URL = 'https://open.er-api.com/v6/latest/USD';
const RATES_MAX_AGE = 86400;

// The currency totals are shown in. Saved in the database, so it can be changed on the page.
function home_currency(): string
{
    $stmt = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute(['home_currency']);
    $code = $stmt->fetchColumn();
    if (is_string($code) && isset(CURRENCIES[$code])) return $code;
    $default = config()['currency'] ?? 'JOD';
    return isset(CURRENCIES[$default]) ? $default : 'JOD';
}

function set_home_currency(string $code): void
{
    db()->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)')->execute(['home_currency', $code]);
}

// Returns ['rates' => [code => per USD], 'source' => live|saved|builtin, 'date' => ?string]
function rates(): array
{
    static $result = null;
    if ($result !== null) return $result;

    $saved = db()->query('SELECT code, per_usd, fetched_at FROM rates')->fetchAll();
    $savedAt = $saved ? min(array_map(fn($r) => strtotime($r['fetched_at']), $saved)) : 0;

    // After a failed try, wait an hour before asking again so pages stay fast
    $stmt = db()->prepare('SELECT value FROM settings WHERE name = ?');
    $stmt->execute(['rates_failed_at']);
    $recentlyFailed = time() - (int) $stmt->fetchColumn() < 3600;

    if (time() - $savedAt > RATES_MAX_AGE && !$recentlyFailed) {
        $live = fetch_live_rates();
        if (!$live) db()->prepare('REPLACE INTO settings (name, value) VALUES (?, ?)')->execute(['rates_failed_at', (string) time()]);
    }
    if (!empty($live)) {
        $stmt = db()->prepare('REPLACE INTO rates (code, per_usd, fetched_at) VALUES (?, ?, NOW())');
        foreach ($live as $code => $rate) $stmt->execute([$code, $rate]);
        return $result = ['rates' => $live, 'source' => 'live', 'date' => date('Y-m-d')];
    }

    $rates = array_map(fn($c) => $c[1], CURRENCIES);
    if ($saved) {
        foreach ($saved as $r) $rates[$r['code']] = (float) $r['per_usd'];
        $fresh = time() - $savedAt <= RATES_MAX_AGE;
        return $result = ['rates' => $rates, 'source' => $fresh ? 'live' : 'saved', 'date' => date('Y-m-d', $savedAt)];
    }
    return $result = ['rates' => $rates, 'source' => 'builtin', 'date' => null];
}

// Asks the rates service once, with a short timeout. Returns null if it can't.
function fetch_live_rates(): ?array
{
    $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
    $body = @file_get_contents(RATES_URL, false, $ctx);
    $data = $body ? json_decode($body, true) : null;
    if (($data['result'] ?? '') !== 'success' || !is_array($data['rates'] ?? null)) {
        error_log('subtrack: could not fetch exchange rates');
        return null;
    }
    $rates = [];
    foreach (array_keys(CURRENCIES) as $code) {
        if (isset($data['rates'][$code]) && $data['rates'][$code] > 0) $rates[$code] = (float) $data['rates'][$code];
    }
    return count($rates) === count(CURRENCIES) ? $rates : null;
}

function convert(float $amount, string $from, string $to): float
{
    if ($from === $to) return $amount;
    $r = rates()['rates'];
    return $amount / $r[$from] * $r[$to];
}

// A subscription's monthly cost in the home currency
function monthly_home(array $sub, string $home): float
{
    return convert(monthly_cost($sub), $sub['currency'], $home);
}
