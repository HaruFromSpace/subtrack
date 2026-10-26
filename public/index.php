<?php
require __DIR__ . '/../src/app.php';

$today = new DateTimeImmutable('today');
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

$monthly = array_sum(array_map('monthly_cost', $subs));
$soon = array_filter($subs, fn($s) => $s['days'] <= config()['warn_days']);

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
