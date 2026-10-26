<?php
$title = 'subtrack';
$count = count($subs);
require __DIR__ . '/head.php';
?>
    <main class="page">
        <?php if ($flash): ?>
            <p class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
        <?php endif; ?>

        <section class="summary" aria-label="Summary">
            <?php if ($count): ?>
                <h1>You spend <span class="amount"><?= money($monthly) ?></span> a month</h1>
                <p>That's <?= money($monthly * 12) ?> a year across <?= $count ?> subscription<?= $count === 1 ? '' : 's' ?>.</p>
            <?php else: ?>
                <h1>Keep track of what you pay for</h1>
                <p>Add your subscriptions and subtrack shows what they cost you each month and warns you before each one renews.</p>
            <?php endif; ?>
        </section>

        <div class="layout<?= $count ? '' : ' is-empty' ?>">
            <?php if ($count): ?>
            <div class="lists">
                    <section aria-labelledby="soonHeading">
                        <h2 id="soonHeading">Coming up</h2>
                        <?php if ($soon): ?>
                            <ul class="soon">
                                <?php foreach ($soon as $s): ?>
                                    <li>
                                        <span class="name"><?= e($s['name']) ?></span>
                                        <span class="when">renews <?= when($s['days']) ?></span>
                                        <span class="price"><?= money((float) $s['price']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="quiet">Nothing renews in the next <?= (int) config()['warn_days'] ?> days.</p>
                        <?php endif; ?>
                    </section>

                    <section aria-labelledby="allHeading">
                        <h2 id="allHeading">All subscriptions</h2>
                        <ul class="subs">
                            <?php foreach ($subs as $s): ?>
                                <li class="<?= $editing && $editing['id'] === $s['id'] ? 'is-editing' : '' ?>">
                                    <div class="sub-main">
                                        <span class="name"><?= e($s['name']) ?></span>
                                        <span class="meta">
                                            <?= e(CYCLES[$s['cycle']]) ?>, next on <?= (new DateTimeImmutable($s['next_renewal']))->format('j M') ?>
                                            <?php if ($s['category'] !== ''): ?> · <?= e($s['category']) ?><?php endif; ?>
                                        </span>
                                    </div>
                                    <div class="sub-cost">
                                        <span class="price"><?= money((float) $s['price']) ?></span>
                                        <?php if ($s['cycle'] !== 'monthly'): ?>
                                            <span class="meta">about <?= money(monthly_cost($s)) ?> a month</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="sub-actions">
                                        <a href="index.php?edit=<?= (int) $s['id'] ?>#form">Edit</a>
                                        <form method="post" action="delete.php" onsubmit="return confirm('Remove <?= e(addslashes($s['name'])) ?>? This can\'t be undone.')">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                            <button type="submit" class="link-button">Remove</button>
                                        </form>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
            </div>
            <?php endif; ?>

            <section id="form" class="panel" aria-labelledby="formHeading">
                <h2 id="formHeading"><?= $editing ? 'Edit ' . e($editing['name']) : 'Add a subscription' ?></h2>
                <form method="post" action="save.php" novalidate>
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>

                    <?php
                    $field = function (string $key) use ($errors) {
                        return isset($errors[$key])
                            ? ' aria-invalid="true" aria-describedby="' . $key . 'Error"'
                            : '';
                    };
                    $error = function (string $key) use ($errors) {
                        return isset($errors[$key])
                            ? '<p class="field-error" id="' . $key . 'Error">' . e($errors[$key]) . '</p>'
                            : '';
                    };
                    ?>

                    <label for="name">Name</label>
                    <input id="name" name="name" type="text" maxlength="80" placeholder="Netflix" value="<?= e($old['name'] ?? '') ?>"<?= $field('name') ?>>
                    <?= $error('name') ?>

                    <div class="pair">
                        <div>
                            <label for="price">Price (<?= e(config()['currency']) ?>)</label>
                            <input id="price" name="price" type="text" inputmode="decimal" placeholder="4.99" value="<?= e($old['price'] ?? '') ?>"<?= $field('price') ?>>
                            <?= $error('price') ?>
                        </div>
                        <div>
                            <label for="cycle">Renews</label>
                            <select id="cycle" name="cycle"<?= $field('cycle') ?>>
                                <?php foreach (CYCLES as $value => $label): ?>
                                    <option value="<?= $value ?>"<?= ($old['cycle'] ?? 'monthly') === $value ? ' selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $error('cycle') ?>
                        </div>
                    </div>

                    <label for="next_renewal">Next renewal</label>
                    <input id="next_renewal" name="next_renewal" type="date" value="<?= e($old['next_renewal'] ?? '') ?>"<?= $field('next_renewal') ?>>
                    <?= $error('next_renewal') ?>

                    <label for="category">Category <span class="optional">(optional)</span></label>
                    <input id="category" name="category" type="text" maxlength="40" placeholder="Streaming" value="<?= e($old['category'] ?? '') ?>"<?= $field('category') ?>>
                    <?= $error('category') ?>

                    <div class="form-actions">
                        <button type="submit" class="button"><?= $editing ? 'Save changes' : 'Add subscription' ?></button>
                        <?php if ($editing): ?><a href="index.php">Cancel</a><?php endif; ?>
                    </div>
                </form>
            </section>
        </div>
    </main>
</body>
</html>
