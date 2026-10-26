<?php
require __DIR__ . '/../src/app.php';

$today = new DateTimeImmutable('today');
$home = home_currency();
$rates = rates();
$subs = db()->query('SELECT * FROM subscriptions ORDER BY next_renewal, name')->fetchAll();

// Renewal dates that have passed move on to the next one
$update = db()->prepare('UPDATE subscriptions SET next_renewal = ? WHERE id = ?');
foreach ($subs as &$sub) {
    $next = roll_forward($sub['next_renewal'], $sub['cycle'], $today);
    if ($next !== $sub['next_renewal']) {
        $update->execute([$next, $sub['id']]);
        $sub['next_renewal'] = $next;
    }
    $sub['days'] = days_until($sub['next_renewal'], $today);
}
unset($sub);
usort($subs, fn($a, $b) => [$a['days'], $a['name']] <=> [$b['days'], $b['name']]);

$monthly = array_sum(array_map(fn($s) => monthly_home($s, $home), $subs));
$soon = array_filter($subs, fn($s) => $s['days'] <= config()['warn_days']);

// Every charge in the next 30 days, day by day, for the strip at the top
const HORIZON = 30;
$days = array_fill(0, HORIZON + 1, []);
$nextMonthSpend = 0.0;
foreach ($subs as $s) {
    $step = ['weekly' => '+1 week', 'monthly' => '+1 month', 'yearly' => '+1 year'][$s['cycle']];
    for ($d = new DateTimeImmutable($s['next_renewal']); ($n = days_until($d->format('Y-m-d'), $today)) <= HORIZON; $d = $d->modify($step)) {
        $days[$n][] = $s['name'];
        $nextMonthSpend += convert((float) $s['price'], $s['currency'], $home);
    }
}

// The form is either adding a new one or editing one from the list
$editing = null;
if (isset($_GET['edit'])) {
    foreach ($subs as $s) {
        if ((string) $s['id'] === $_GET['edit']) $editing = $s;
    }
}
$old = $_SESSION['old'] ?? ($editing ?? []);
$errors = $_SESSION['errors'] ?? [];
unset($_SESSION['old'], $_SESSION['errors']);
$flash = take_flash();

require __DIR__ . '/../views/home.php';
