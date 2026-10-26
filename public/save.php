<?php
require __DIR__ . '/../src/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();

$id = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int) $_POST['id'] : null;
[$data, $errors] = validate($_POST);

if ($errors) {
    $_SESSION['old'] = $_POST;
    $_SESSION['errors'] = $errors;
    redirect('index.php' . ($id ? "?edit=$id" : '') . '#form');
}

$values = [$data['name'], $data['price'], $data['currency'], $data['cycle'], $data['next_renewal'], $data['category']];
if ($id) {
    $stmt = db()->prepare('UPDATE subscriptions SET name = ?, price = ?, currency = ?, cycle = ?, next_renewal = ?, category = ? WHERE id = ?');
    $stmt->execute([...$values, $id]);
    flash("Saved your changes to {$data['name']}.");
} else {
    $stmt = db()->prepare('INSERT INTO subscriptions (name, price, currency, cycle, next_renewal, category) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute($values);
    flash("Added {$data['name']}.");
}
redirect('index.php');
