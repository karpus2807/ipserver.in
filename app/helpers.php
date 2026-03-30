<?php

declare(strict_types=1);

function bootstrap_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $config = app_config();
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'secure' => $config['cookie_secure'],
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function route_url(string $route): string
{
    $baseUrl = rtrim((string) app_config()['base_url'], '/');
    $prefix = $baseUrl !== '' ? $baseUrl : '';

    if ($route === 'dashboard') {
        return $prefix . '/index.php';
    }

    return $prefix . '/index.php?route=' . urlencode($route);
}

function asset_url(string $path): string
{
    $baseUrl = rtrim((string) app_config()['base_url'], '/');
    return $baseUrl . '/public/' . ltrim($path, '/');
}

function redirect(string $route): never
{
    header('Location: ' . route_url($route));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flash(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $messages;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(csrf_token(), $token)) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function render_page(string $bodyClass, callable $content): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e(app_config()['app_name']) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(asset_url('assets/app.css')) ?>">
    </head>
    <body class="<?= e($bodyClass) ?>">
        <?php foreach (pull_flash() as $message): ?>
            <div class="toast toast-<?= e($message['type']) ?>"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <?php $content(); ?>
    </body>
    </html>
    <?php
}

function render_guest_header(string $title, string $tagline): void
{
    ?>
    <header class="site-header guest">
        <a href="<?= route_url('dashboard') ?>" class="brand">
            <span class="brand-mark">IL</span>
            <div>
                <strong><?= e(app_config()['app_name']) ?></strong>
                <small><?= e($tagline) ?></small>
            </div>
        </a>
        <nav>
            <a href="<?= route_url('login') ?>">Login</a>
            <a href="<?= route_url('register') ?>">Register</a>
        </nav>
    </header>
    <?php
}

function render_app_header(string $title, array $currentUser, bool $isAdmin): void
{
    ?>
    <header class="site-header">
        <a href="<?= route_url('dashboard') ?>" class="brand">
            <span class="brand-mark">IL</span>
            <div>
                <strong><?= e(app_config()['app_name']) ?></strong>
                <small><?= e($title) ?></small>
            </div>
        </a>
        <nav>
            <a href="<?= route_url('dashboard') ?>">Dashboard</a>
            <?php if ($isAdmin): ?>
                <a href="<?= route_url('users') ?>">Users</a>
                <a href="<?= route_url('inventory') ?>">Inventory</a>
                <a href="<?= route_url('issue') ?>">Issue Centre</a>
            <?php endif; ?>
        </nav>
        <div class="user-chip">
            <span><?= e($currentUser['name']) ?></span>
            <small><?= e(ucfirst($currentUser['role'])) ?></small>
            <a href="<?= route_url('logout') ?>">Logout</a>
        </div>
    </header>
    <?php
}

function render_footer(): void
{
    echo '<footer class="site-footer">Built for secure lab inventory operations on ipserver.in</footer>';
}

function department_options(): array
{
    return ['Computer Science', 'Electronics', 'Mechanical', 'Civil', 'Physics Lab', 'Chemistry Lab', 'Administration'];
}

function year_options(): array
{
    return ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Semester', '6th Semester', '7th Semester', '8th Semester', 'Staff'];
}

function inventory_categories(): array
{
    return ['Laptop', 'Desktop', 'Projector', 'Router', 'Arduino Kit', 'Raspberry Pi', 'Sensor', 'Lab Tool', 'Peripheral', 'Other'];
}

function labelize(?string $value): string
{
    return ucwords(str_replace('_', ' ', (string) $value));
}

function format_date(?string $value): string
{
    if ($value === null || $value === '') {
        return '-';
    }

    return date('d M Y, h:i A', strtotime($value));
}

function ensure_storage_path(): string
{
    $path = dirname(__DIR__) . '/storage';
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }

    return $path;
}

function send_portal_mail(string $to, string $subject, string $body): void
{
    $config = app_config();
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $config['mail_from'],
    ];

    $sent = false;
    if ($config['mail_enabled']) {
        $sent = @mail($to, $subject, $body, implode("\r\n", $headers));
    }

    if (!$sent) {
        file_put_contents(
            ensure_storage_path() . '/mail.log',
            '[' . date('c') . "] {$to} | {$subject}\n{$body}\n\n",
            FILE_APPEND
        );
    }
}

function all_users(): array
{
    return db()->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
}

function student_users(): array
{
    $stmt = db()->query("SELECT * FROM users WHERE role = 'student' ORDER BY name ASC");
    return $stmt->fetchAll();
}

function all_inventory_items(): array
{
    return db()->query('SELECT * FROM inventory_items ORDER BY created_at DESC')->fetchAll();
}

function available_inventory_items(): array
{
    return db()->query("SELECT * FROM inventory_items WHERE status = 'available' ORDER BY item_name ASC")->fetchAll();
}

function dashboard_stats(int $userId, bool $isAdmin): array
{
    if ($isAdmin) {
        return [
            'Students' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn(),
            'Inventory' => (int) db()->query('SELECT COUNT(*) FROM inventory_items')->fetchColumn(),
            'Issued' => (int) db()->query("SELECT COUNT(*) FROM inventory_items WHERE status = 'issued'")->fetchColumn(),
            'Pending OTP' => (int) db()->query("SELECT COUNT(*) FROM inventory_transactions WHERE issue_status = 'pending_otp' OR return_status = 'otp_sent'")->fetchColumn(),
        ];
    }

    $stmt = db()->prepare(
        "SELECT
            SUM(CASE WHEN issue_status = 'issued' AND return_status = 'not_requested' THEN 1 ELSE 0 END) AS active_items,
            SUM(CASE WHEN issue_status = 'pending_otp' THEN 1 ELSE 0 END) AS issue_otp,
            SUM(CASE WHEN return_status = 'otp_sent' THEN 1 ELSE 0 END) AS return_otp
         FROM inventory_transactions
         WHERE user_id = :user_id"
    );
    $stmt->execute(['user_id' => $userId]);
    $stats = $stmt->fetch() ?: [];

    return [
        'Active Items' => (int) ($stats['active_items'] ?? 0),
        'Issue OTPs' => (int) ($stats['issue_otp'] ?? 0),
        'Return OTPs' => (int) ($stats['return_otp'] ?? 0),
        'Profile Status' => 'Active',
    ];
}

function transactions_for_user(int $userId, bool $isAdmin): array
{
    if ($isAdmin) {
        return db()->query(
            'SELECT t.*, u.name AS user_name, u.email AS user_email, i.item_name, i.item_code
             FROM inventory_transactions t
             INNER JOIN users u ON u.id = t.user_id
             INNER JOIN inventory_items i ON i.id = t.item_id
             ORDER BY t.created_at DESC'
        )->fetchAll();
    }

    $stmt = db()->prepare(
        'SELECT t.*, u.name AS user_name, u.email AS user_email, i.item_name, i.item_code
         FROM inventory_transactions t
         INNER JOIN users u ON u.id = t.user_id
         INNER JOIN inventory_items i ON i.id = t.item_id
         WHERE t.user_id = :user_id
         ORDER BY t.created_at DESC'
    );
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function active_user_transactions(int $userId): array
{
    $stmt = db()->prepare(
        "SELECT t.*, i.item_name, i.item_code
         FROM inventory_transactions t
         INNER JOIN inventory_items i ON i.id = t.item_id
         WHERE t.user_id = :user_id
           AND t.issue_status = 'issued'
           AND t.return_status IN ('not_requested', 'otp_sent')
         ORDER BY t.issued_at DESC"
    );
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll();
}

function pending_issue_requests(int $userId, bool $isAdmin): array
{
    $sql = 'SELECT t.*, u.name AS user_name, u.email AS user_email, i.item_name, i.item_code
            FROM inventory_transactions t
            INNER JOIN users u ON u.id = t.user_id
            INNER JOIN inventory_items i ON i.id = t.item_id
            WHERE (t.issue_status = \'pending_otp\' OR t.return_status = \'otp_sent\')';

    if (!$isAdmin) {
        $sql .= ' AND t.user_id = :user_id';
    }

    $sql .= ' ORDER BY t.created_at DESC';
    $stmt = db()->prepare($sql);
    $params = $isAdmin ? [] : ['user_id' => $userId];
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function create_user(array $payload): void
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
    $stmt->execute(['email' => $payload['email']]);

    if ((int) $stmt->fetchColumn() > 0) {
        throw new RuntimeException('Email already registered.');
    }

    $insert = db()->prepare(
        'INSERT INTO users (name, email, password_hash, department, year_level, enrollment_no, phone, role)
         VALUES (:name, :email, :password_hash, :department, :year_level, :enrollment_no, :phone, :role)'
    );

    $insert->execute([
        'name' => $payload['name'],
        'email' => $payload['email'],
        'password_hash' => password_hash($payload['password'], PASSWORD_DEFAULT),
        'department' => $payload['department'],
        'year_level' => $payload['year_level'],
        'enrollment_no' => $payload['enrollment_no'],
        'phone' => $payload['phone'] ?? '',
        'role' => $payload['role'] ?? 'student',
    ]);
}

function request_issue(int $itemId, int $userId, int $issuedBy): void
{
    $itemStmt = db()->prepare("SELECT * FROM inventory_items WHERE id = :id AND status = 'available'");
    $itemStmt->execute(['id' => $itemId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        throw new RuntimeException('Selected item available nahi hai.');
    }

    $userStmt = db()->prepare('SELECT * FROM users WHERE id = :id');
    $userStmt->execute(['id' => $userId]);
    $user = $userStmt->fetch();

    if (!$user) {
        throw new RuntimeException('Selected user not found.');
    }

    $otp = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    db()->prepare(
        "INSERT INTO inventory_transactions
        (item_id, user_id, issued_by, issue_status, return_status, issue_otp, issue_otp_expires_at)
        VALUES (:item_id, :user_id, :issued_by, 'pending_otp', 'not_requested', :issue_otp, :issue_otp_expires_at)"
    )->execute([
        'item_id' => $itemId,
        'user_id' => $userId,
        'issued_by' => $issuedBy,
        'issue_otp' => $otp,
        'issue_otp_expires_at' => $expires,
    ]);

    send_portal_mail(
        $user['email'],
        'Inventory Issue OTP',
        "Hello {$user['name']},\n\nYour OTP for inventory issue of {$item['item_name']} ({$item['item_code']}) is {$otp}.\nThis OTP will expire at {$expires}.\n\nIf you did not request this, please contact the lab admin."
    );
}

function verify_issue_otp(int $transactionId, string $otp, int $userId): void
{
    $stmt = db()->prepare(
        "SELECT t.*, i.item_name
         FROM inventory_transactions t
         INNER JOIN inventory_items i ON i.id = t.item_id
         WHERE t.id = :id AND t.user_id = :user_id"
    );
    $stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
    $transaction = $stmt->fetch();

    if (!$transaction || $transaction['issue_status'] !== 'pending_otp') {
        throw new RuntimeException('Pending issue request not found.');
    }

    if ($transaction['issue_otp'] !== $otp) {
        throw new RuntimeException('Entered OTP incorrect hai.');
    }

    if (strtotime((string) $transaction['issue_otp_expires_at']) < time()) {
        throw new RuntimeException('OTP expire ho chuka hai. Admin se naya OTP resend karvayen.');
    }

    db()->prepare(
        "UPDATE inventory_transactions
         SET issue_status = 'issued', issued_at = NOW(), issue_verified_at = NOW(), issue_otp = NULL
         WHERE id = :id"
    )->execute(['id' => $transactionId]);

    db()->prepare("UPDATE inventory_items SET status = 'issued' WHERE id = :id")
        ->execute(['id' => $transaction['item_id']]);
}

function request_return(int $transactionId, int $userId): void
{
    $stmt = db()->prepare(
        "SELECT t.*, u.name AS user_name, u.email AS user_email, i.item_name, i.item_code
         FROM inventory_transactions t
         INNER JOIN users u ON u.id = t.user_id
         INNER JOIN inventory_items i ON i.id = t.item_id
         WHERE t.id = :id AND t.user_id = :user_id"
    );
    $stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
    $transaction = $stmt->fetch();

    if (!$transaction || $transaction['issue_status'] !== 'issued') {
        throw new RuntimeException('Active issued inventory record nahi mila.');
    }

    $otp = (string) random_int(100000, 999999);
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    db()->prepare(
        "UPDATE inventory_transactions
         SET return_status = 'otp_sent', return_requested_at = NOW(), return_otp = :return_otp, return_otp_expires_at = :return_otp_expires_at
         WHERE id = :id"
    )->execute([
        'return_otp' => $otp,
        'return_otp_expires_at' => $expires,
        'id' => $transactionId,
    ]);

    send_portal_mail(
        $transaction['user_email'],
        'Inventory Return OTP',
        "Hello {$transaction['user_name']},\n\nYour OTP for returning {$transaction['item_name']} ({$transaction['item_code']}) is {$otp}.\nThis OTP will expire at {$expires}.\n\nVerify the OTP on your dashboard to complete the return."
    );
}

function verify_return_otp(int $transactionId, string $otp, int $userId): void
{
    $stmt = db()->prepare(
        'SELECT * FROM inventory_transactions WHERE id = :id AND user_id = :user_id'
    );
    $stmt->execute(['id' => $transactionId, 'user_id' => $userId]);
    $transaction = $stmt->fetch();

    if (!$transaction || $transaction['return_status'] !== 'otp_sent') {
        throw new RuntimeException('Pending return request not found.');
    }

    if ($transaction['return_otp'] !== $otp) {
        throw new RuntimeException('Entered return OTP incorrect hai.');
    }

    if (strtotime((string) $transaction['return_otp_expires_at']) < time()) {
        throw new RuntimeException('Return OTP expire ho chuka hai. Naya OTP request kijiye.');
    }

    db()->prepare(
        "UPDATE inventory_transactions
         SET return_status = 'returned', returned_at = NOW(), return_verified_at = NOW(), return_otp = NULL
         WHERE id = :id"
    )->execute(['id' => $transactionId]);

    db()->prepare("UPDATE inventory_items SET status = 'available' WHERE id = :id")
        ->execute(['id' => $transaction['item_id']]);
}

function import_inventory_csv(?array $file): void
{
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Valid CSV file upload kijiye.');
    }

    $handle = fopen((string) $file['tmp_name'], 'rb');
    if (!$handle) {
        throw new RuntimeException('CSV read nahi ho pa rahi.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('CSV empty hai.');
    }

    $header = array_map(static fn ($value) => strtolower(trim((string) $value)), $header);
    $required = ['item_code', 'item_name', 'category', 'brand', 'serial_number', 'location', 'notes'];

    foreach ($required as $column) {
        if (!in_array($column, $header, true)) {
            fclose($handle);
            throw new RuntimeException('Missing CSV column: ' . $column);
        }
    }

    $map = array_flip($header);
    $stmt = db()->prepare(
        'INSERT INTO inventory_items (item_code, item_name, category, brand, serial_number, location, notes)
         VALUES (:item_code, :item_name, :category, :brand, :serial_number, :location, :notes)
         ON DUPLICATE KEY UPDATE
            item_name = VALUES(item_name),
            category = VALUES(category),
            brand = VALUES(brand),
            serial_number = VALUES(serial_number),
            location = VALUES(location),
            notes = VALUES(notes)'
    );

    while (($row = fgetcsv($handle)) !== false) {
        if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        $stmt->execute([
            'item_code' => trim((string) ($row[$map['item_code']] ?? '')),
            'item_name' => trim((string) ($row[$map['item_name']] ?? '')),
            'category' => trim((string) ($row[$map['category']] ?? '')),
            'brand' => trim((string) ($row[$map['brand']] ?? '')),
            'serial_number' => trim((string) ($row[$map['serial_number']] ?? '')),
            'location' => trim((string) ($row[$map['location']] ?? '')),
            'notes' => trim((string) ($row[$map['notes']] ?? '')),
        ]);
    }

    fclose($handle);
}
