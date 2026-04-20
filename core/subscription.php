<?php

function qrk_table_exists(PDO $pdo, string $table): bool
{
    $table_escaped = str_replace("'", "''", $table);
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table_escaped}'");
    return (bool)$stmt->fetchColumn();
}

function qrk_is_digital_package_slug(?string $package_slug): bool
{
    if (!is_string($package_slug) || $package_slug === '') {
        return false;
    }

    $normalized = strtolower(trim($package_slug));
    return str_contains($normalized, 'panel')
        || str_contains($normalized, 'smart')
        || str_contains($normalized, 'akilli');
}

function qrk_user_has_any_subscription(PDO $pdo, int $user_id): bool
{
    if ($user_id <= 0 || !qrk_table_exists($pdo, 'subscriptions')) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM subscriptions WHERE user_id = ? LIMIT 1');
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetchColumn();
}

function qrk_user_has_active_subscription(PDO $pdo, int $user_id): bool
{
    if ($user_id <= 0 || !qrk_table_exists($pdo, 'subscriptions')) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT 1
         FROM subscriptions
         WHERE user_id = ?
           AND status IN ('active', 'cancel_at_period_end')
           AND (current_period_end IS NULL OR current_period_end >= NOW())
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([$user_id]);
    return (bool)$stmt->fetchColumn();
}

function qrk_user_has_digital_access(PDO $pdo, int $user_id, ?string $package_slug_fallback): bool
{
    if ($user_id <= 0) {
        return false;
    }

    if (!qrk_table_exists($pdo, 'subscriptions')) {
        return qrk_is_digital_package_slug($package_slug_fallback);
    }

    if (qrk_user_has_any_subscription($pdo, $user_id)) {
        return qrk_user_has_active_subscription($pdo, $user_id);
    }

    return qrk_is_digital_package_slug($package_slug_fallback);
}

