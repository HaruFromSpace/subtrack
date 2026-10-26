<?php
require __DIR__ . '/../src/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();

$id = ctype_digit($_POST['id'] ?? '') ? (int) $_POST['id'] : 0;
$stmt = db()->prepare('SELECT name FROM subscriptions WHERE id = ?');
$stmt->execute([$id]);
$name = $stmt->fetchColumn();

if ($name === false) {
    flash('That subscription was already removed.', 'error');
} else {
    db()->prepare('DELETE FROM subscriptions WHERE id = ?')->execute([$id]);
    flash("Removed $name.");
}
redirect('index.php');
