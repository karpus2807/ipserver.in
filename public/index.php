<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

$route = $_GET['route'] ?? 'dashboard';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$currentUser = current_user();

if ($method === 'POST') {
    verify_csrf();
}

if ($route === 'login') {
    if ($method === 'POST') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $remember = isset($_POST['remember']);

        if ($email === '' || $password === '') {
            flash('error', 'Email aur password dono required hain.');
        } elseif (attempt_login($email, $password, $remember)) {
            flash('success', 'Welcome back. Session successfully start ho gaya.');
            redirect('dashboard');
        } else {
            flash('error', 'Invalid credentials. Please try again.');
        }
    }

    render_page('login', function (): void {
        render_guest_header('Login', 'Lab inventory access with secure session management');
        ?>
        <section class="auth-shell">
            <div class="auth-card">
                <div class="auth-copy">
                    <span class="eyebrow">Secure Access</span>
                    <h1>Lab portal me sign in kijiye</h1>
                    <p>Sessions aur optional remember cookie ke saath fast login experience.</p>
                    <ul class="feature-list">
                        <li>OTP-based issue and return verification</li>
                        <li>Student records + inventory lifecycle in one place</li>
                        <li>Dropdown-driven forms for faster lab operations</li>
                    </ul>
                </div>
                <form method="post" class="panel form-stack">
                    <?= csrf_field() ?>
                    <label><span>Email</span><input type="email" name="email" placeholder="student@college.edu" required></label>
                    <label><span>Password</span><input type="password" name="password" placeholder="Enter password" required></label>
                    <label class="inline-check">
                        <input type="checkbox" name="remember" value="1">
                        <span>Remember me on this browser</span>
                    </label>
                    <button type="submit" class="btn btn-primary">Sign In</button>
                    <p class="muted">New student? <a href="<?= route_url('register') ?>">Create account</a></p>
                </form>
            </div>
        </section>
        <?php
        render_footer();
    });
    exit;
}

if ($route === 'register') {
    if ($method === 'POST') {
        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
            'password' => (string) ($_POST['password'] ?? ''),
            'department' => (string) ($_POST['department'] ?? ''),
            'year_level' => (string) ($_POST['year_level'] ?? ''),
            'enrollment_no' => trim((string) ($_POST['enrollment_no'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
        ];

        $missing = array_filter(
            ['name', 'email', 'password', 'department', 'year_level', 'enrollment_no'],
            static fn (string $field): bool => $payload[$field] === ''
        );

        if ($missing !== []) {
            flash('error', 'Please fill all required fields before registration.');
        } elseif (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Valid email address enter kijiye.');
        } elseif (strlen($payload['password']) < 8) {
            flash('error', 'Password minimum 8 characters ka hona chahiye.');
        } else {
            try {
                create_user($payload);
                flash('success', 'Registration complete. Ab login karke dashboard open kijiye.');
                redirect('login');
            } catch (Throwable $exception) {
                flash('error', $exception->getMessage());
            }
        }
    }

    render_page('register', function (): void {
        render_guest_header('Register', 'Student self-registration for centralized lab records');
        ?>
        <section class="auth-shell">
            <div class="auth-card auth-card-wide">
                <div class="auth-copy">
                    <span class="eyebrow">Student Onboarding</span>
                    <h1>Registration record ek baar me complete kijiye</h1>
                    <p>Dropdown-led form se clean lab database maintain karna easy rahega.</p>
                </div>
                <form method="post" class="panel form-grid">
                    <?= csrf_field() ?>
                    <label><span>Full Name</span><input type="text" name="name" required></label>
                    <label><span>Email</span><input type="email" name="email" required></label>
                    <label><span>Enrollment No.</span><input type="text" name="enrollment_no" required></label>
                    <label><span>Phone</span><input type="text" name="phone"></label>
                    <label>
                        <span>Department</span>
                        <select name="department" required>
                            <option value="">Select department</option>
                            <?php foreach (department_options() as $department): ?>
                                <option value="<?= e($department) ?>"><?= e($department) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Year / Semester</span>
                        <select name="year_level" required>
                            <option value="">Select year</option>
                            <?php foreach (year_options() as $year): ?>
                                <option value="<?= e($year) ?>"><?= e($year) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="full-span"><span>Password</span><input type="password" name="password" minlength="8" required></label>
                    <div class="form-actions full-span">
                        <button type="submit" class="btn btn-primary">Create Account</button>
                        <a class="btn btn-secondary" href="<?= route_url('login') ?>">Back to login</a>
                    </div>
                </form>
            </div>
        </section>
        <?php
        render_footer();
    });
    exit;
}

if ($route === 'logout') {
    logout_user();
    flash('success', 'You have been logged out safely.');
    redirect('login');
}

require_login();
$currentUser = current_user();
$isAdmin = is_admin();

if ($route === 'users' && $isAdmin && $method === 'POST') {
    $payload = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'password' => (string) ($_POST['password'] ?? ''),
        'department' => (string) ($_POST['department'] ?? ''),
        'year_level' => (string) ($_POST['year_level'] ?? ''),
        'enrollment_no' => trim((string) ($_POST['enrollment_no'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'role' => (string) ($_POST['role'] ?? 'student'),
    ];

    try {
        create_user($payload);
        flash('success', 'New user record successfully add ho gaya.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('users');
}

if ($route === 'inventory' && $isAdmin && $method === 'POST') {
    $itemCode = trim((string) ($_POST['item_code'] ?? ''));
    $itemName = trim((string) ($_POST['item_name'] ?? ''));
    $category = (string) ($_POST['category'] ?? '');
    $brand = trim((string) ($_POST['brand'] ?? ''));
    $serial = trim((string) ($_POST['serial_number'] ?? ''));
    $location = trim((string) ($_POST['location'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if ($itemCode === '' || $itemName === '' || $category === '') {
        flash('error', 'Item code, item name, aur category required hain.');
    } else {
        try {
            db()->prepare(
                'INSERT INTO inventory_items (item_code, item_name, category, brand, serial_number, location, notes)
                 VALUES (:item_code, :item_name, :category, :brand, :serial_number, :location, :notes)'
            )->execute([
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'category' => $category,
                'brand' => $brand,
                'serial_number' => $serial,
                'location' => $location,
                'notes' => $notes,
            ]);
            flash('success', 'Inventory item add ho gaya.');
        } catch (Throwable $exception) {
            flash('error', 'Item save nahi hua. Duplicate item code ya invalid data ho sakta hai.');
        }
    }

    redirect('inventory');
}

if ($route === 'issue' && $isAdmin && $method === 'POST') {
    try {
        request_issue((int) ($_POST['item_id'] ?? 0), (int) ($_POST['user_id'] ?? 0), (int) $currentUser['id']);
        flash('success', 'Issue OTP student email par send kar diya gaya.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('issue');
}

if ($route === 'verify-issue' && $method === 'POST') {
    try {
        verify_issue_otp((int) ($_POST['transaction_id'] ?? 0), trim((string) ($_POST['otp'] ?? '')), (int) $currentUser['id']);
        flash('success', 'OTP verified. Inventory successfully issued.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('dashboard');
}

if ($route === 'request-return' && $method === 'POST') {
    try {
        request_return((int) ($_POST['transaction_id'] ?? 0), (int) $currentUser['id']);
        flash('success', 'Return OTP aapke registered email par send kar diya gaya.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('dashboard');
}

if ($route === 'verify-return' && $method === 'POST') {
    try {
        verify_return_otp((int) ($_POST['transaction_id'] ?? 0), trim((string) ($_POST['otp'] ?? '')), (int) $currentUser['id']);
        flash('success', 'Return OTP verified. Inventory available stock me wapas aa gayi.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('dashboard');
}

if ($route === 'csv-upload' && $isAdmin && $method === 'POST') {
    try {
        import_inventory_csv($_FILES['csv_file'] ?? null);
        flash('success', 'CSV import complete.');
    } catch (Throwable $exception) {
        flash('error', $exception->getMessage());
    }

    redirect('inventory');
}

$stats = dashboard_stats((int) $currentUser['id'], $isAdmin);
$items = all_inventory_items();
$students = student_users();
$transactions = transactions_for_user((int) $currentUser['id'], $isAdmin);
$pending = pending_issue_requests((int) $currentUser['id'], $isAdmin);
$activeReturns = active_user_transactions((int) $currentUser['id']);

render_page($route, function () use ($route, $currentUser, $isAdmin, $stats, $items, $students, $transactions, $pending, $activeReturns): void {
    render_app_header(ucfirst(str_replace('-', ' ', $route)), $currentUser, $isAdmin);
    ?>
    <section class="hero-band">
        <div>
            <span class="eyebrow">Lab Operations</span>
            <h1><?= e($isAdmin ? 'Advanced Inventory Control Panel' : 'Student Inventory Dashboard') ?></h1>
            <p><?= e($isAdmin ? 'Students, assets, OTP approvals, and CSV imports ek hi portal me manage kijiye.' : 'Issued assets, pending OTP approvals, aur self-return workflow yahin se handle kijiye.') ?></p>
        </div>
        <div class="quick-stats">
            <?php foreach ($stats as $label => $value): ?>
                <article class="stat-card"><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if ($route === 'dashboard'): ?>
        <section class="content-grid">
            <article class="panel">
                <div class="panel-head"><h2><?= $isAdmin ? 'Recent Transactions' : 'My Inventory' ?></h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Item</th><?php if ($isAdmin): ?><th>User</th><?php endif; ?><th>Issue Status</th><th>Return Status</th><th>Issued On</th></tr></thead>
                        <tbody>
                            <?php foreach (array_slice($transactions, 0, 8) as $transaction): ?>
                                <tr>
                                    <td><?= e($transaction['item_name']) ?> <small><?= e($transaction['item_code']) ?></small></td>
                                    <?php if ($isAdmin): ?><td><?= e($transaction['user_name']) ?></td><?php endif; ?>
                                    <td><span class="pill"><?= e(labelize($transaction['issue_status'])) ?></span></td>
                                    <td><span class="pill pill-light"><?= e(labelize($transaction['return_status'])) ?></span></td>
                                    <td><?= e(format_date($transaction['issued_at'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($transactions === []): ?><tr><td colspan="<?= $isAdmin ? '5' : '4' ?>">No transaction data yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>Pending OTP Actions</h2></div>
                <?php if ($pending === []): ?><p class="muted">Abhi koi pending OTP verification nahi hai.</p><?php endif; ?>
                <?php foreach ($pending as $entry): ?>
                    <div class="otp-card">
                        <div>
                            <strong><?= e($entry['item_name']) ?></strong>
                            <p><?= e($entry['user_name']) ?> • <?= e(labelize($entry['issue_status'])) ?> / <?= e(labelize($entry['return_status'])) ?></p>
                        </div>
                        <?php if (!$isAdmin && $entry['issue_status'] === 'pending_otp'): ?>
                            <form method="post" class="otp-form" action="<?= route_url('verify-issue') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="transaction_id" value="<?= e((string) $entry['id']) ?>">
                                <input type="text" name="otp" maxlength="6" placeholder="Enter issue OTP" required>
                                <button class="btn btn-primary">Verify Issue</button>
                            </form>
                        <?php elseif (!$isAdmin && $entry['return_status'] === 'otp_sent'): ?>
                            <form method="post" class="otp-form" action="<?= route_url('verify-return') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="transaction_id" value="<?= e((string) $entry['id']) ?>">
                                <input type="text" name="otp" maxlength="6" placeholder="Enter return OTP" required>
                                <button class="btn btn-primary">Verify Return</button>
                            </form>
                        <?php else: ?>
                            <p class="muted">Admin can monitor this request from the transaction history.</p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </article>
            <?php if (!$isAdmin): ?>
                <article class="panel full-width">
                    <div class="panel-head"><h2>Return Active Inventory</h2></div>
                    <div class="list-stack">
                        <?php foreach ($activeReturns as $transaction): ?>
                            <div class="list-row">
                                <div>
                                    <strong><?= e($transaction['item_name']) ?></strong>
                                    <p><?= e($transaction['item_code']) ?> • Issued <?= e(format_date($transaction['issued_at'])) ?></p>
                                </div>
                                <form method="post" action="<?= route_url('request-return') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="transaction_id" value="<?= e((string) $transaction['id']) ?>">
                                    <button class="btn btn-secondary"><?= $transaction['return_status'] === 'otp_sent' ? 'Resend Return OTP' : 'Start Return' ?></button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($activeReturns === []): ?><p class="muted">Aapke paas abhi koi active issued item nahi hai.</p><?php endif; ?>
                    </div>
                </article>
            <?php endif; ?>
        </section>
    <?php elseif ($route === 'users' && $isAdmin): ?>
        <section class="content-grid admin-grid">
            <article class="panel">
                <div class="panel-head"><h2>Add User</h2></div>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <label><span>Name</span><input type="text" name="name" required></label>
                    <label><span>Email</span><input type="email" name="email" required></label>
                    <label><span>Enrollment No.</span><input type="text" name="enrollment_no" required></label>
                    <label><span>Phone</span><input type="text" name="phone"></label>
                    <label><span>Department</span><select name="department" required><option value="">Select</option><?php foreach (department_options() as $department): ?><option value="<?= e($department) ?>"><?= e($department) ?></option><?php endforeach; ?></select></label>
                    <label><span>Year</span><select name="year_level" required><option value="">Select</option><?php foreach (year_options() as $year): ?><option value="<?= e($year) ?>"><?= e($year) ?></option><?php endforeach; ?></select></label>
                    <label><span>Role</span><select name="role"><option value="student">Student</option><option value="admin">Admin</option></select></label>
                    <label><span>Password</span><input type="password" name="password" minlength="8" required></label>
                    <div class="form-actions full-span"><button type="submit" class="btn btn-primary">Save User</button></div>
                </form>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>Registered Users</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Department</th><th>Year</th><th>Role</th><th>Email</th></tr></thead>
                        <tbody><?php foreach (all_users() as $user): ?><tr><td><?= e($user['name']) ?></td><td><?= e($user['department']) ?></td><td><?= e($user['year_level']) ?></td><td><?= e(ucfirst($user['role'])) ?></td><td><?= e($user['email']) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </article>
        </section>
    <?php elseif ($route === 'inventory' && $isAdmin): ?>
        <section class="content-grid admin-grid">
            <article class="panel">
                <div class="panel-head"><h2>Add Inventory Item</h2></div>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <label><span>Item Code</span><input type="text" name="item_code" required></label>
                    <label><span>Item Name</span><input type="text" name="item_name" required></label>
                    <label><span>Category</span><select name="category" required><option value="">Select category</option><?php foreach (inventory_categories() as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label>
                    <label><span>Brand</span><input type="text" name="brand"></label>
                    <label><span>Serial Number</span><input type="text" name="serial_number"></label>
                    <label><span>Location</span><input type="text" name="location" placeholder="Lab Shelf A2"></label>
                    <label class="full-span"><span>Notes</span><textarea name="notes" rows="3"></textarea></label>
                    <div class="form-actions full-span"><button type="submit" class="btn btn-primary">Add Item</button></div>
                </form>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>CSV Import</h2></div>
                <form method="post" enctype="multipart/form-data" class="form-stack" action="<?= route_url('csv-upload') ?>">
                    <?= csrf_field() ?>
                    <label><span>Upload CSV</span><input type="file" name="csv_file" accept=".csv" required></label>
                    <button type="submit" class="btn btn-secondary">Import CSV</button>
                    <p class="muted">Expected headers: item_code, item_name, category, brand, serial_number, location, notes</p>
                </form>
            </article>
            <article class="panel full-width">
                <div class="panel-head"><h2>Inventory Register</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Code</th><th>Name</th><th>Category</th><th>Status</th><th>Location</th></tr></thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr><td><?= e($item['item_code']) ?></td><td><?= e($item['item_name']) ?></td><td><?= e($item['category']) ?></td><td><?= e(labelize($item['status'])) ?></td><td><?= e($item['location']) ?></td></tr>
                            <?php endforeach; ?>
                            <?php if ($items === []): ?><tr><td colspan="5">Inventory abhi add nahi hua hai.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    <?php elseif ($route === 'issue' && $isAdmin): ?>
        <section class="content-grid admin-grid">
            <article class="panel">
                <div class="panel-head"><h2>Issue Inventory</h2></div>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?>
                    <label><span>Select Student</span><select name="user_id" required><option value="">Choose student</option><?php foreach ($students as $student): ?><option value="<?= e((string) $student['id']) ?>"><?= e($student['name']) ?> (<?= e($student['enrollment_no']) ?>)</option><?php endforeach; ?></select></label>
                    <label><span>Select Item</span><select name="item_id" required><option value="">Choose available item</option><?php foreach (available_inventory_items() as $item): ?><option value="<?= e((string) $item['id']) ?>"><?= e($item['item_name']) ?> (<?= e($item['item_code']) ?>)</option><?php endforeach; ?></select></label>
                    <div class="form-actions full-span"><button type="submit" class="btn btn-primary">Send Issue OTP</button></div>
                </form>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>Issue Queue</h2></div>
                <div class="list-stack">
                    <?php foreach ($pending as $entry): ?>
                        <div class="list-row"><div><strong><?= e($entry['item_name']) ?></strong><p><?= e($entry['user_name']) ?> • <?= e($entry['user_email']) ?></p></div><span class="pill"><?= e(labelize($entry['issue_status'])) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($pending === []): ?><p class="muted">Pending issue requests appear here.</p><?php endif; ?>
                </div>
            </article>
        </section>
    <?php else: ?>
        <section class="panel"><h2>Page not available</h2><p class="muted">Requested route ya to restricted hai ya abhi setup nahi hua.</p></section>
    <?php endif;
    render_footer();
});
