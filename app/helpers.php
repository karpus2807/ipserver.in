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
    $isStaff = is_staff_user($currentUser);
    $userLabel = $isStaff ? 'Staff' : ucfirst((string) ($currentUser['role'] ?? 'User'));
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
            <?php endif; ?>
            <?php if ($isStaff): ?>
                <a href="<?= route_url('inventory') ?>">Inventory</a>
                <a href="<?= route_url('inventory-update') ?>">Inventory Update</a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <a href="<?= route_url('issue') ?>">Issue Centre</a>
            <?php endif; ?>
        </nav>
        <div class="user-chip">
            <span><?= e($currentUser['name']) ?></span>
            <small><?= e($userLabel) ?></small>
            <a href="<?= route_url('logout') ?>">Logout</a>
        </div>
    </header>
    <?php
}

function render_footer(): void
{
    echo '<footer class="site-footer">Built for secure lab inventory operations on ipserver.in</footer>';
}

function is_staff_user(?array $user = null): bool
{
    $user ??= current_user();

    if ($user === null) {
        return false;
    }

    $role = strtolower(trim((string) ($user['role'] ?? '')));
    $category = strtolower(trim((string) ($user['year_level'] ?? '')));
    $department = strtolower(trim((string) ($user['department'] ?? '')));
    $enrollment = strtoupper(trim((string) ($user['enrollment_no'] ?? '')));

    return $role === 'admin'
        || $category === 'staff'
        || $department === 'administration'
        || str_starts_with($enrollment, 'ADMIN-');
}

function can_manage_inventory(?array $user = null): bool
{
    return is_staff_user($user);
}

function debug_enabled(): bool
{
    return (bool) app_config()['debug'];
}

function log_debug_exception(Throwable $exception, array $context = []): string
{
    $reference = 'DBG-' . strtoupper(bin2hex(random_bytes(4)));
    $payload = [
        'reference' => $reference,
        'time' => date('c'),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'trace' => $exception->getTraceAsString(),
        'context' => $context,
    ];

    file_put_contents(
        ensure_storage_path() . '/debug.log',
        json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL,
        FILE_APPEND
    );

    return $reference;
}

function flash_exception(string $fallbackMessage, Throwable $exception, array $context = []): void
{
    $reference = log_debug_exception($exception, $context);
    $message = $fallbackMessage . ' Reference: ' . $reference;

    if (debug_enabled()) {
        $message .= ' Details: ' . $exception->getMessage();
    }

    flash('error', $message);
}

function department_options(): array
{
    return ['Computer Science and Engineering(CSE)', 'Electrical Engineering', 'Electronics Engineering', 'Mechanical Engineering', 'Civil Engineering', 'Administration'];
}

function category_options(): array
{
    return ['Student', 'Staff'];
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

function all_inventory_items(?string $search = null): array
{
    $search = trim((string) $search);

    if ($search === '') {
        return db()->query('SELECT * FROM inventory_items ORDER BY created_at DESC')->fetchAll();
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM inventory_items
         WHERE invt_ctrl_no LIKE :search
            OR item_description LIKE :search
            OR item_long_description LIKE :search
            OR item_name LIKE :search
            OR item_code LIKE :search
         ORDER BY created_at DESC"
    );
    $stmt->execute(['search' => '%' . $search . '%']);
    return $stmt->fetchAll();
}

function available_inventory_items(): array
{
    return db()->query("SELECT * FROM inventory_items WHERE status = 'available' ORDER BY item_description ASC, item_name ASC")->fetchAll();
}

function inventory_item_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM inventory_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    return $stmt->fetch() ?: null;
}

function inventory_payload_from_form(array $input): array
{
    return [
        's_no' => trim((string) ($input['s_no'] ?? '')),
        'item_code' => trim((string) ($input['item_code'] ?? '')),
        'item_name' => trim((string) ($input['item_name'] ?? '')),
        'item_description' => trim((string) ($input['item_description'] ?? '')),
        'item_long_description' => trim((string) ($input['item_long_description'] ?? '')),
        'category' => trim((string) ($input['category'] ?? 'Other')),
        'quantity' => trim((string) ($input['quantity'] ?? '')),
        'unit' => trim((string) ($input['unit'] ?? '')),
        'value_text' => trim((string) ($input['value_text'] ?? '')),
        'net_eff_value' => trim((string) ($input['net_eff_value'] ?? '')),
        'invt_ctrl_no' => trim((string) ($input['invt_ctrl_no'] ?? '')),
        'department_name' => trim((string) ($input['department_name'] ?? '')),
        'issued_to' => trim((string) ($input['issued_to'] ?? '')),
        'issue_type' => trim((string) ($input['issue_type'] ?? '')),
        'lab_code' => trim((string) ($input['lab_code'] ?? '')),
        'gis_no' => trim((string) ($input['gis_no'] ?? '')),
        'gis_date' => trim((string) ($input['gis_date'] ?? '')),
        'nc_no' => trim((string) ($input['nc_no'] ?? '')),
        'nc_date' => trim((string) ($input['nc_date'] ?? '')),
        'source_name' => trim((string) ($input['source_name'] ?? '')),
        'qr_view' => trim((string) ($input['qr_view'] ?? '')),
        'brand' => trim((string) ($input['brand'] ?? '')),
        'serial_number' => trim((string) ($input['serial_number'] ?? '')),
        'status' => trim((string) ($input['status'] ?? 'available')),
        'location' => trim((string) ($input['location'] ?? '')),
        'notes' => trim((string) ($input['notes'] ?? '')),
    ];
}

function validate_inventory_payload(array $payload): void
{
    if ($payload['invt_ctrl_no'] === '') {
        throw new RuntimeException('Inventory control number is required.');
    }

    if ($payload['item_code'] === '') {
        throw new RuntimeException('Item code is required.');
    }

    if ($payload['item_description'] === '' && $payload['item_name'] === '') {
        throw new RuntimeException('At least one item title field is required.');
    }
}

function save_inventory_item(array $payload, ?int $id = null): void
{
    validate_inventory_payload($payload);

    $sqlPayload = $payload;
    $sqlPayload['item_name'] = $sqlPayload['item_name'] !== '' ? $sqlPayload['item_name'] : $sqlPayload['item_description'];

    if ($id === null) {
        $stmt = db()->prepare(
            'INSERT INTO inventory_items
            (s_no, item_code, item_name, item_description, item_long_description, category, quantity, unit, value_text, net_eff_value,
             invt_ctrl_no, department_name, issued_to, issue_type, lab_code, gis_no, gis_date, nc_no, nc_date, source_name, qr_view,
             brand, serial_number, status, location, notes)
             VALUES
            (:s_no, :item_code, :item_name, :item_description, :item_long_description, :category, :quantity, :unit, :value_text, :net_eff_value,
             :invt_ctrl_no, :department_name, :issued_to, :issue_type, :lab_code, :gis_no, :gis_date, :nc_no, :nc_date, :source_name, :qr_view,
             :brand, :serial_number, :status, :location, :notes)'
        );
        $stmt->execute($sqlPayload);
        return;
    }

    $sqlPayload['id'] = $id;
    $stmt = db()->prepare(
        'UPDATE inventory_items SET
            s_no = :s_no,
            item_code = :item_code,
            item_name = :item_name,
            item_description = :item_description,
            item_long_description = :item_long_description,
            category = :category,
            quantity = :quantity,
            unit = :unit,
            value_text = :value_text,
            net_eff_value = :net_eff_value,
            invt_ctrl_no = :invt_ctrl_no,
            department_name = :department_name,
            issued_to = :issued_to,
            issue_type = :issue_type,
            lab_code = :lab_code,
            gis_no = :gis_no,
            gis_date = :gis_date,
            nc_no = :nc_no,
            nc_date = :nc_date,
            source_name = :source_name,
            qr_view = :qr_view,
            brand = :brand,
            serial_number = :serial_number,
            status = :status,
            location = :location,
            notes = :notes
         WHERE id = :id'
    );
    $stmt->execute($sqlPayload);
}

function delete_inventory_item(int $id): void
{
    $stmt = db()->prepare('DELETE FROM inventory_items WHERE id = :id');
    $stmt->execute(['id' => $id]);
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
        throw new RuntimeException('The selected item is not currently available.');
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
        throw new RuntimeException('The entered OTP is incorrect.');
    }

    if (strtotime((string) $transaction['issue_otp_expires_at']) < time()) {
        throw new RuntimeException('This OTP has expired. Please ask the administrator to resend it.');
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
        throw new RuntimeException('No active issued inventory record was found.');
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
        throw new RuntimeException('The entered return OTP is incorrect.');
    }

    if (strtotime((string) $transaction['return_otp_expires_at']) < time()) {
        throw new RuntimeException('This return OTP has expired. Please request a new one.');
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
        throw new RuntimeException('Please upload a valid CSV file.');
    }

    $handle = fopen((string) $file['tmp_name'], 'rb');
    if (!$handle) {
        throw new RuntimeException('The CSV file could not be read.');
    }

    $header = fgetcsv($handle);
    if (!$header) {
        fclose($handle);
        throw new RuntimeException('The uploaded CSV file is empty.');
    }

    $header = array_map(static fn ($value) => strtolower(trim((string) $value)), $header);
    $required = ['item code', 'item description', 'invt. ctrl no'];

    foreach ($required as $column) {
        if (!in_array($column, $header, true)) {
            fclose($handle);
            throw new RuntimeException('Missing CSV column: ' . $column);
        }
    }

    $map = array_flip($header);

    $rowNumber = 1;
    $errors = [];

    while (($row = fgetcsv($handle)) !== false) {
        $rowNumber++;

        if (count(array_filter($row, static fn ($value) => trim((string) $value) !== '')) === 0) {
            continue;
        }

        try {
            $payload = [
                's_no' => trim((string) ($row[$map['s.no.']] ?? '')),
                'item_code' => trim((string) ($row[$map['item code']] ?? '')),
                'item_name' => trim((string) ($row[$map['item description']] ?? '')),
                'item_description' => trim((string) ($row[$map['item description']] ?? '')),
                'item_long_description' => trim((string) ($row[$map['item long description']] ?? '')),
                'category' => trim((string) ($row[$map['issue type']] ?? 'Imported')),
                'quantity' => trim((string) ($row[$map['qnty']] ?? '')),
                'unit' => trim((string) ($row[$map['unit']] ?? '')),
                'value_text' => trim((string) ($row[$map['value']] ?? '')),
                'net_eff_value' => trim((string) ($row[$map['net eff. value(inr)']] ?? '')),
                'invt_ctrl_no' => trim((string) ($row[$map['invt. ctrl no']] ?? '')),
                'department_name' => trim((string) ($row[$map['department name']] ?? '')),
                'issued_to' => trim((string) ($row[$map['issued to']] ?? '')),
                'issue_type' => trim((string) ($row[$map['issue type']] ?? '')),
                'lab_code' => trim((string) ($row[$map['lab code']] ?? '')),
                'gis_no' => trim((string) ($row[$map['gis no.']] ?? '')),
                'gis_date' => trim((string) ($row[$map['gis date']] ?? '')),
                'nc_no' => trim((string) ($row[$map['nc no.']] ?? '')),
                'nc_date' => trim((string) ($row[$map['nc date']] ?? '')),
                'source_name' => trim((string) ($row[$map['source']] ?? '')),
                'qr_view' => trim((string) ($row[$map['qr view']] ?? '')),
                'brand' => '',
                'serial_number' => trim((string) ($row[$map['gis no.']] ?? '')),
                'status' => trim((string) ($row[$map['issued to']] ?? '')) !== '' ? 'issued' : 'available',
                'location' => trim((string) ($row[$map['lab code']] ?? '')),
                'notes' => '',
            ];

            $existing = db()->prepare('SELECT id FROM inventory_items WHERE invt_ctrl_no = :invt_ctrl_no LIMIT 1');
            $existing->execute(['invt_ctrl_no' => $payload['invt_ctrl_no']]);
            $existingId = $existing->fetchColumn();

            save_inventory_item($payload, $existingId ? (int) $existingId : null);
        } catch (Throwable $exception) {
            $errors[] = [
                'row' => $rowNumber,
                'message' => $exception->getMessage(),
                'invt_ctrl_no' => trim((string) ($row[$map['invt. ctrl no']] ?? '')),
            ];
        }
    }

    fclose($handle);

    if ($errors !== []) {
        $reference = 'CSV-' . strtoupper(bin2hex(random_bytes(4)));
        file_put_contents(
            ensure_storage_path() . '/debug.log',
            json_encode(['reference' => $reference, 'errors' => $errors], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
        throw new RuntimeException('CSV import completed with errors. Reference: ' . $reference);
    }
}
