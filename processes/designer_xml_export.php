<?php
require_once __DIR__ . '/../core/security.php';
ensure_session_started();
require_once __DIR__ . '/../core/db.php';
require_once __DIR__ . '/../core/dynamic_form.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['user_role']) ||
    $_SESSION['user_role'] !== 'designer'
) {
    header('Location: ../auth/login.php');
    exit();
}

function redirect_back_to_order(int $order_id, string $error_key): void
{
    $target = '../designer/order_details.php?id=' . max(1, $order_id) . '&xml_error=' . urlencode($error_key);
    header('Location: ' . $target);
    exit();
}

function project_base_url_for_designer_export(): string
{
    $env_app_url = trim((string) getenv('APP_URL'));
    if ($env_app_url !== '') {
        return rtrim($env_app_url, '/');
    }

    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    $scheme = $is_https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $script_name = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/processes/designer_xml_export.php');
    $project_path = preg_replace('#/processes/[^/]+$#', '', $script_name);
    return $scheme . '://' . $host . rtrim((string) $project_path, '/');
}

try {
    df_ensure_dynamic_form_schema($pdo);
    df_seed_default_form_fields($pdo);

    $order_id = (int) ($_GET['order_id'] ?? 0);
    if ($order_id <= 0) {
        redirect_back_to_order(1, 'invalid_order');
    }

    $order_stmt = $pdo->prepare(
        "SELECT o.*, u.name AS customer_name, u.email AS customer_email, u.phone AS customer_phone
         FROM orders o
         JOIN users u ON u.id = o.user_id
         WHERE o.id = ?
         LIMIT 1"
    );
    $order_stmt->execute([$order_id]);
    $order = $order_stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) {
        redirect_back_to_order($order_id, 'not_found');
    }

    $profile_stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1");
    $profile_stmt->execute([(int) $order['user_id']]);
    $profile = $profile_stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $profile_url = '';
    if (!empty($profile['slug'])) {
        $profile_url = project_base_url_for_designer_export() . '/kartvizit.php?slug=' . rawurlencode((string) $profile['slug']);
    }

    $variables = [
        'order_id' => (string) $order_id,
        'order_package' => (string) ($order['package'] ?? ''),
        'order_status' => (string) ($order['status'] ?? ''),
        'customer_name' => (string) ($order['customer_name'] ?? ''),
        'customer_email' => (string) ($order['customer_email'] ?? ''),
        'customer_phone' => (string) ($order['customer_phone'] ?? ''),
        'company_name' => (string) ($order['company_name'] ?? ''),
        'job_title' => (string) ($order['job_title'] ?? ''),
        'design_notes' => (string) ($order['design_notes'] ?? ''),
        'logo_path' => (string) ($order['logo_path'] ?? ''),
        'profile_slug' => (string) ($profile['slug'] ?? ''),
        'profile_url' => $profile_url,
        'profile_qr_path' => (string) ($profile['qr_path'] ?? ''),
    ];

    $answer_rows = df_get_order_answers($pdo, $order_id);
    foreach ($answer_rows as $answer_row) {
        $field_key = (string) ($answer_row['field_key'] ?? '');
        $field_value = (string) ($answer_row['value_text'] ?? '');
        if ($field_key === '') {
            continue;
        }
        $variables[$field_key] = $field_value;
    }

    if (df_table_exists($pdo, 'social_links') && !empty($profile['id'])) {
        $social_stmt = $pdo->prepare(
            "SELECT platform, url
             FROM social_links
             WHERE profile_id = ?
             ORDER BY sort_order ASC, id ASC"
        );
        $social_stmt->execute([(int) $profile['id']]);
        $social_links = $social_stmt->fetchAll(PDO::FETCH_ASSOC);

        $platform_counter = [];
        foreach ($social_links as $social_link) {
            $platform = trim((string) ($social_link['platform'] ?? 'link'));
            $url = trim((string) ($social_link['url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $base_key = 'social_' . df_to_ai_variable_key($platform) . '_url';
            if (!isset($platform_counter[$base_key])) {
                $platform_counter[$base_key] = 0;
            }
            $platform_counter[$base_key]++;
            $suffix = $platform_counter[$base_key] > 1 ? '_' . $platform_counter[$base_key] : '';

            $variables[$base_key . $suffix] = $url;
        }
    }

    $dataset_name = 'Order_' . $order_id;
    $xml_content = df_build_illustrator_xml($variables, $dataset_name);

    $filename = 'order_' . $order_id . '_illustrator_variables.xml';
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo $xml_content;
    exit();
} catch (Throwable $e) {
    error_log('designer_xml_export_error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    $fallback_order_id = (int) ($_GET['order_id'] ?? 1);
    redirect_back_to_order($fallback_order_id, 'export_failed');
}
