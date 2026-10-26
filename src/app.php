<?php
// Shared setup for every page: config, database, session and small helpers.

declare(strict_types=1);

const CYCLES = ['weekly' => 'Every week', 'monthly' => 'Every month', 'yearly' => 'Every year'];

session_start();
ob_start();

function config(): array
{
    static $config = null;
    if ($config === null) {
        $path = __DIR__ . '/../config.php';
        $config = is_file($path) ? require $path : require __DIR__ . '/../config.example.php';
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $pdo = new PDO(
            "mysql:host={$c['db_host']};dbname={$c['db_name']};charset=utf8mb4",
            $c['db_user'],
            $c['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

// Visitors never see raw errors. Log the details, show a plain page.
set_exception_handler(function (Throwable $e) {
    error_log('subtrack: ' . $e);
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    $message = $e instanceof PDOException
        ? "subtrack can't reach its database right now. Check that MySQL is running and that config.php has the right details."
        : 'Something went wrong on our side. Please try again in a moment.';
    require __DIR__ . '/../views/error.php';
    exit;
});

function e(?string $text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

function money(float $amount): string
{
    return number_format($amount, 2) . ' ' . e(config()['currency']);
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (!hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
        flash('That form had expired, so nothing was saved. Please try again.', 'error');
        redirect('index.php');
    }
}

function flash(string $message, string $type = 'ok'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function redirect(string $to): never
{
    header("Location: $to", true, 303);
    exit;
}

// How much one subscription costs per month, whatever its cycle
function monthly_cost(array $sub): float
{
    return match ($sub['cycle']) {
        'weekly' => (float) $sub['price'] * 52 / 12,
        'yearly' => (float) $sub['price'] / 12,
        default => (float) $sub['price'],
    };
}

// Moves a renewal date that has already passed forward to the next one
function roll_forward(string $date, string $cycle, DateTimeImmutable $today): string
{
    $step = ['weekly' => '+1 week', 'monthly' => '+1 month', 'yearly' => '+1 year'][$cycle];
    $d = new DateTimeImmutable($date);
    while ($d < $today) {
        $d = $d->modify($step);
    }
    return $d->format('Y-m-d');
}

function days_until(string $date, DateTimeImmutable $today): int
{
    return (int) $today->diff(new DateTimeImmutable($date))->format('%r%a');
}

function when(int $days): string
{
    return match (true) {
        $days === 0 => 'today',
        $days === 1 => 'tomorrow',
        default => "in $days days",
    };
}

// Checks the add/edit form. Returns [clean values, list of problems].
function validate(array $in): array
{
    $errors = [];
    $name = trim($in['name'] ?? '');
    $price = str_replace(',', '.', trim($in['price'] ?? ''));
    $cycle = $in['cycle'] ?? '';
    $date = $in['next_renewal'] ?? '';
    $category = trim($in['category'] ?? '');

    if ($name === '') $errors['name'] = 'Give it a name, like Netflix or Spotify.';
    elseif (mb_strlen($name) > 80) $errors['name'] = 'Keep the name under 80 characters.';

    if ($price === '' || !is_numeric($price) || (float) $price < 0) $errors['price'] = 'Enter the price as a number, like 4.99.';
    elseif ((float) $price > 99999999) $errors['price'] = 'That price looks too big. Check it and try again.';

    if (!isset(CYCLES[$cycle])) $errors['cycle'] = 'Choose how often it renews.';

    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$d || $d->format('Y-m-d') !== $date) $errors['next_renewal'] = 'Pick the date it next renews.';

    if (mb_strlen($category) > 40) $errors['category'] = 'Keep the category under 40 characters.';

    return [compact('name', 'price', 'cycle', 'category') + ['next_renewal' => $date], $errors];
}
