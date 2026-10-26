<?php
require __DIR__ . '/../src/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('index.php');
check_csrf();

$code = $_POST['home'] ?? '';
if (isset(CURRENCIES[$code])) {
    set_home_currency($code);
    flash("Totals are now shown in " . CURRENCIES[$code][0] . "s.");
} else {
    flash("That currency isn't supported yet.", 'error');
}
redirect('index.php');
