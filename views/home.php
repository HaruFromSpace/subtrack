<?php
$title = 'subtrack';
$count = count($subs);
$currency = e($home);
$currencyOptions = function (string $selected, bool $short = false) {
    $out = '';
    foreach (CURRENCIES as $code => [$label]) {
        $out .= '<option value="' . $code . '"' . ($code === $selected ? ' selected' : '') . '>' . $code . ($short ? '' : ' · ' . e($label)) . '</option>';
    }
    return $out;
};

$field = function (string $key) use ($errors) {
    return isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . 'Error"' : '';
};
$error = function (string $key) use ($errors) {
    return isset($errors[$key]) ? '<p class="field-error" id="' . $key . 'Error">' . e($errors[$key]) . '</p>' : '';
};

require __DIR__ . '/head.php';
?>
    <header class="band">
        <div class="wrap">
            <a class="wordmark" href="index.php">subtrack</a>

            <?php if ($count): ?>
                <h1 class="total">
                    <span class="figure"><?= number_format($monthly, 2) ?></span>
                    <span class="unit"><?= $currency ?><br>a month</span>
                </h1>
                <p class="band-note">
                    <?= number_format($monthly * 12, 2) ?> <?= $currency ?> a year across <?= $count ?> subscription<?= $count === 1 ? '' : 's' ?>.
                    The next 30 days take <?= number_format($nextMonthSpend, 2) ?> <?= $currency ?>.
                </p>
                <form class="home-currency" method="post" action="currency.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <label for="home">Show totals in</label>
                    <select id="home" name="home"><?= $currencyOptions($home) ?></select>
                    <button type="submit">Switch</button>
                </form>
                <?php if ($rates['source'] !== 'live'): ?>
                    <p class="rates-note">
                        <?= $rates['source'] === 'saved'
                            ? 'Exchange rates couldn\'t be updated just now, so totals use the rates saved on ' . (new DateTimeImmutable($rates['date']))->format('j M') . '.'
                            : 'Exchange rates couldn\'t be loaded, so totals in other currencies use rough built-in rates for now.' ?>
                    </p>
                <?php endif; ?>

                <section class="month" aria-labelledby="monthHeading">
                    <h2 id="monthHeading" class="visually-hidden">The next 30 days</h2>
                    <ol class="strip">
                        <?php foreach ($days as $n => $names):
                            $date = $today->modify("+$n day"); ?>
                            <li style="--i: <?= $n ?>" class="<?= $names ? 'charge' : '' ?><?= $n === 0 ? ' today' : '' ?><?= $date->format('j') === '1' ? ' first' : '' ?>"
                                <?php if ($names): ?>title="<?= e($date->format('D j M') . ': ' . implode(', ', $names)) ?>"<?php endif; ?>>
                                <span class="visually-hidden"><?= $date->format('D j M') ?><?= $names ? ': ' . e(implode(', ', $names)) : ': nothing' ?></span>
                                <?php if ($date->format('j') === '1'): ?><span class="month-name" aria-hidden="true"><?= $date->format('M') ?></span><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <p class="strip-ends" aria-hidden="true"><span>Today</span><span>In 30 days</span></p>

                    <?php if ($soon): ?>
                        <ul class="soon">
                            <?php foreach ($soon as $s): ?>
                                <li>
                                    <span class="soon-when"><?= ucfirst(when($s['days'])) ?></span>
                                    <span class="soon-name"><?= e($s['name']) ?></span>
                                    <span class="soon-price"><?= number_format((float) $s['price'], 2) ?> <small><?= e($s['currency']) ?></small></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="band-note">Nothing renews in the next <?= (int) config()['warn_days'] ?> days.</p>
                    <?php endif; ?>
                </section>
            <?php else: ?>
                <h1 class="headline">What do your subscriptions cost you?</h1>
                <p class="band-note">Add them below and subtrack adds them up, month by month, and shows when each one takes money next.</p>
            <?php endif; ?>
        </div>
    </header>

    <main class="wrap main">
        <?php if ($flash): ?>
            <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <div class="layout<?= $count ? '' : ' is-empty' ?>">
            <?php if ($count): ?>
                <section class="list" aria-labelledby="allHeading">
                    <h2 id="allHeading">Everything you pay for</h2>
                    <ul class="subs">
                        <?php foreach ($subs as $s): ?>
                            <li class="<?= $editing && $editing['id'] === $s['id'] ? 'is-editing' : '' ?>">
                                <div class="sub-main">
                                    <span class="name"><?= e($s['name']) ?></span>
                                    <span class="meta"><?= e(CYCLES[$s['cycle']]) ?> · next <?= (new DateTimeImmutable($s['next_renewal']))->format('j M') ?><?php if ($s['category'] !== ''): ?> · <?= e($s['category']) ?><?php endif; ?></span>
                                    <span class="sub-actions">
                                        <a href="index.php?edit=<?= (int) $s['id'] ?>#form">Edit</a>
                                        <form method="post" action="delete.php" onsubmit="return confirm('Remove <?= e(addslashes($s['name'])) ?>? This can\'t be undone.')">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="link-button">Remove</button>
                                        </form>
                                    </span>
                                </div>
                                <div class="sub-cost">
                                    <span class="price"><?= number_format((float) $s['price'], 2) ?></span>
                                    <span class="unit-line"><?= e($s['currency']) ?> <?= ['weekly' => 'a week', 'monthly' => 'a month', 'yearly' => 'a year'][$s['cycle']] ?></span>
                                    <?php if ($s['cycle'] !== 'monthly' || $s['currency'] !== $home): ?>
                                        <span class="meta">about <?= number_format(monthly_home($s, $home), 2) ?> <?= $currency ?> a month</span>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <section id="form" class="add" aria-labelledby="formHeading">
                <h2 id="formHeading"><?= $editing ? 'Edit ' . e($editing['name']) : 'Add a subscription' ?></h2>
                <form method="post" action="save.php" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

                    <div class="field">
                        <label for="name">Name</label>
                        <input id="name" name="name" type="text" maxlength="80" placeholder="Netflix" autocomplete="off" value="<?= e($old['name'] ?? '') ?>"<?= $field('name') ?>>
                        <?= $error('name') ?>
                    </div>

                    <div class="pair">
                        <div class="field">
                            <label for="price">Price</label>
                            <input id="price" name="price" type="text" inputmode="decimal" placeholder="4.99" value="<?= e($old['price'] ?? '') ?>"<?= $field('price') ?>>
                            <?= $error('price') ?>
                        </div>
                        <div class="field">
                            <label for="currency">Currency</label>
                            <select id="currency" name="currency"<?= $field('currency') ?>><?= $currencyOptions($old['currency'] ?? $home, true) ?></select>
                            <?= $error('currency') ?>
                        </div>
                    </div>

                    <div class="pair">
                        <div class="field">
                            <label for="cycle">Renews</label>
                            <select id="cycle" name="cycle"<?= $field('cycle') ?>>
                                <?php foreach (CYCLES as $value => $label): ?>
                                    <option value="<?= $value ?>"<?= ($old['cycle'] ?? 'monthly') === $value ? ' selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $error('cycle') ?>
                        </div>
                        <div class="field">
                            <label for="next_renewal">Next renewal</label>
                            <input id="next_renewal" name="next_renewal" type="date" value="<?= e($old['next_renewal'] ?? '') ?>"<?= $field('next_renewal') ?>>
                            <?= $error('next_renewal') ?>
                        </div>
                    </div>

                    <div class="field">
                        <label for="category">Category <span class="optional">(optional)</span></label>
                        <input id="category" name="category" type="text" maxlength="40" placeholder="Streaming" autocomplete="off" value="<?= e($old['category'] ?? '') ?>"<?= $field('category') ?>>
                        <?= $error('category') ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button"><?= $editing ? 'Save changes' : 'Add it' ?></button>
                        <?php if ($editing): ?><a href="index.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
