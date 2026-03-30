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
            flash('error', 'Email and password are both required.');
        } elseif (attempt_login($email, $password, $remember)) {
            flash('success', 'Welcome back. Your session has started successfully.');
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
                <div class="auth-showcase auth-showcase-login">
                    <div class="showcase-orb orb-one"></div>
                    <div class="showcase-orb orb-two"></div>
                    <div class="showcase-grid"></div>
                    <div class="showcase-content">
                        <span class="eyebrow">Secure Access</span>
                        <h1>Start inside a portal that feels alive</h1>
                        <p>Designed for smoother sign-in, faster approvals, and a more polished lab experience from the first screen.</p>
                        <div class="showcase-metrics">
                            <article>
                                <strong>OTP Flow</strong>
                                <span>Issue and return approvals stay protected through email verification.</span>
                            </article>
                            <article>
                                <strong>Smart Session</strong>
                                <span>Persistent browser login reduces repeated sign-ins for daily lab work.</span>
                            </article>
                        </div>
                    </div>
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
                    <p class="muted">New user? <a href="<?= route_url('register') ?>">Create account</a></p>
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
            'year_level' => (string) ($_POST['category'] ?? ''),
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
            flash('error', 'Please enter a valid email address.');
        } elseif (strlen($payload['password']) < 8) {
            flash('error', 'Password must be at least 8 characters long.');
        } else {
            try {
                create_user($payload);
                flash('success', 'Registration completed. You can now log in.');
                redirect('login');
            } catch (Throwable $exception) {
                flash_exception('Registration failed.', $exception, ['route' => 'register', 'email' => $payload['email']]);
            }
        }
    }

    render_page('register', function (): void {
        render_guest_header('Register', 'Self-registration for centralized lab records');
        ?>
        <section class="auth-shell">
            <div class="auth-card auth-card-wide">
                <div class="auth-showcase auth-showcase-register">
                    <div class="showcase-orb orb-three"></div>
                    <div class="showcase-orb orb-four"></div>
                    <div class="showcase-grid"></div>
                    <div class="showcase-content">
                        <span class="eyebrow">User Onboarding</span>
                        <h1>Register through a cleaner, more premium flow</h1>
                        <p>Capture the right records with a guided layout that feels less like a form and more like a proper portal experience.</p>
                        <div class="showcase-steps">
                            <div>
                                <span>01</span>
                                <p>Select the correct department and category.</p>
                            </div>
                            <div>
                                <span>02</span>
                                <p>Fill in identity and contact details once.</p>
                            </div>
                            <div>
                                <span>03</span>
                                <p>Move directly into the student workflow after login.</p>
                            </div>
                        </div>
                    </div>
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
                        <span>Category</span>
                        <select name="category" required>
                            <option value="">Select category</option>
                            <?php foreach (category_options() as $category): ?>
                                <option value="<?= e($category) ?>"><?= e($category) ?></option>
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
$isStaff = is_staff_user($currentUser);
$canManageInventory = can_manage_inventory($currentUser);
$inventorySearch = trim((string) ($_GET['q'] ?? ''));
$staffTab = $_GET['tab'] ?? match ($route) {
    'inventory' => 'complete-inventory',
    'inventory-update' => 'inventory-update',
    default => 'overview',
};
$editingInventory = $canManageInventory && isset($_GET['edit']) ? inventory_item_by_id((int) $_GET['edit']) : null;

if ($route === 'users' && $isAdmin && $method === 'POST') {
    $payload = [
        'name' => trim((string) ($_POST['name'] ?? '')),
        'email' => strtolower(trim((string) ($_POST['email'] ?? ''))),
        'password' => (string) ($_POST['password'] ?? ''),
        'department' => (string) ($_POST['department'] ?? ''),
        'year_level' => (string) ($_POST['category'] ?? ''),
        'enrollment_no' => trim((string) ($_POST['enrollment_no'] ?? '')),
        'phone' => trim((string) ($_POST['phone'] ?? '')),
        'role' => (string) ($_POST['role'] ?? 'student'),
    ];

    try {
        create_user($payload);
        flash('success', 'The new user record was added successfully.');
    } catch (Throwable $exception) {
        flash_exception('The user record could not be saved.', $exception, ['route' => 'users', 'email' => $payload['email']]);
    }

    redirect('users');
}

if ($route === 'inventory-save' && $canManageInventory && $method === 'POST') {
    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);
    $payload = inventory_payload_from_form($_POST);

    try {
        save_inventory_item($payload, $inventoryId > 0 ? $inventoryId : null);
        flash('success', $inventoryId > 0 ? 'The inventory item was updated successfully.' : 'The inventory item was added successfully.');
    } catch (Throwable $exception) {
        flash_exception('The inventory item could not be saved.', $exception, [
            'route' => 'inventory-save',
            'inventory_id' => $inventoryId,
            'invt_ctrl_no' => $payload['invt_ctrl_no'],
        ]);
    }

    redirect('inventory-update');
}

if ($route === 'inventory-delete' && $canManageInventory && $method === 'POST') {
    $inventoryId = (int) ($_POST['inventory_id'] ?? 0);

    try {
        delete_inventory_item($inventoryId);
        flash('success', 'The inventory item was removed successfully.');
    } catch (Throwable $exception) {
        flash_exception('The inventory item could not be removed.', $exception, ['route' => 'inventory-delete', 'inventory_id' => $inventoryId]);
    }

    redirect('inventory');
}

if ($route === 'issue' && $isAdmin && $method === 'POST') {
    try {
        request_issue((int) ($_POST['item_id'] ?? 0), (int) ($_POST['user_id'] ?? 0), (int) $currentUser['id']);
        flash('success', 'The issue OTP has been sent to the student email address.');
    } catch (Throwable $exception) {
        flash_exception('The issue request could not be created.', $exception, ['route' => 'issue']);
    }

    redirect('issue');
}

if ($route === 'verify-issue' && $method === 'POST') {
    try {
        verify_issue_otp((int) ($_POST['transaction_id'] ?? 0), trim((string) ($_POST['otp'] ?? '')), (int) $currentUser['id']);
        flash('success', 'OTP verified. The inventory has been issued successfully.');
    } catch (Throwable $exception) {
        flash_exception('Issue OTP verification failed.', $exception, ['route' => 'verify-issue']);
    }

    redirect('dashboard');
}

if ($route === 'request-return' && $method === 'POST') {
    try {
        request_return((int) ($_POST['transaction_id'] ?? 0), (int) $currentUser['id']);
        flash('success', 'The return OTP has been sent to your registered email address.');
    } catch (Throwable $exception) {
        flash_exception('The return request could not be created.', $exception, ['route' => 'request-return']);
    }

    redirect('dashboard');
}

if ($route === 'verify-return' && $method === 'POST') {
    try {
        verify_return_otp((int) ($_POST['transaction_id'] ?? 0), trim((string) ($_POST['otp'] ?? '')), (int) $currentUser['id']);
        flash('success', 'Return OTP verified. The inventory item is now available again.');
    } catch (Throwable $exception) {
        flash_exception('Return OTP verification failed.', $exception, ['route' => 'verify-return']);
    }

    redirect('dashboard');
}

if ($route === 'csv-upload' && $canManageInventory && $method === 'POST') {
    try {
        import_inventory_csv($_FILES['csv_file'] ?? null);
        flash('success', 'CSV import completed successfully.');
    } catch (Throwable $exception) {
        flash_exception('The CSV import failed.', $exception, ['route' => 'csv-upload']);
    }

    redirect('inventory-update');
}

$stats = dashboard_stats((int) $currentUser['id'], $isAdmin || $isStaff);
$items = $canManageInventory ? all_inventory_items($inventorySearch) : all_inventory_items();
$students = student_users();
$transactions = transactions_for_user((int) $currentUser['id'], $isAdmin);
$pending = pending_issue_requests((int) $currentUser['id'], $isAdmin);
$activeReturns = active_user_transactions((int) $currentUser['id']);

render_page($route, function () use ($route, $currentUser, $isAdmin, $isStaff, $canManageInventory, $staffTab, $stats, $items, $students, $transactions, $pending, $activeReturns, $editingInventory, $inventorySearch): void {
    render_app_header(ucfirst(str_replace('-', ' ', $route)), $currentUser, $isAdmin);
    ?>
    <section class="hero-band">
        <div>
            <span class="eyebrow">Lab Operations</span>
            <h1><?= e($isStaff ? 'Staff Inventory Workspace' : 'Student Inventory Dashboard') ?></h1>
            <p><?= e($isStaff ? 'Search the full inventory, upload CSV updates, edit records, and trace issues with debug-friendly responses.' : 'Track issued assets, complete OTP verification, and manage your return requests from one place.') ?></p>
        </div>
        <div class="quick-stats">
            <?php foreach ($stats as $label => $value): ?>
                <article class="stat-card"><span><?= e($label) ?></span><strong><?= e((string) $value) ?></strong></article>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if ($isStaff && in_array($route, ['dashboard', 'inventory', 'inventory-update'], true)): ?>
        <section class="content-grid">
            <article class="panel full-width">
                <div class="tab-strip">
                    <a class="tab-chip <?= $staffTab === 'overview' ? 'active' : '' ?>" href="<?= route_url('dashboard') ?>">Overview</a>
                    <a class="tab-chip <?= $staffTab === 'complete-inventory' ? 'active' : '' ?>" href="<?= route_url('inventory') ?>">Complete Inventory</a>
                    <a class="tab-chip <?= $staffTab === 'inventory-update' ? 'active' : '' ?>" href="<?= route_url('inventory-update') ?>">Inventory Update</a>
                </div>
            </article>
            <?php if ($staffTab === 'overview'): ?>
                <article class="panel">
                    <div class="panel-head"><h2>Workspace Summary</h2></div>
                    <div class="list-stack">
                        <div class="list-row"><div><strong>Total visible inventory</strong><p><?= e((string) count($items)) ?> records available for search and review.</p></div></div>
                        <div class="list-row"><div><strong>Search priority</strong><p>Results support inventory control number, item description, long description, item name, and item code.</p></div></div>
                        <div class="list-row"><div><strong>Debug visibility</strong><p>Every failure writes a reference into `storage/debug.log` so issues can be traced quickly.</p></div></div>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-head"><h2>Recent Inventory Records</h2></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Invt. Ctrl No</th><th>Item Description</th><th>Issued To</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach (array_slice($items, 0, 8) as $item): ?>
                                    <tr>
                                        <td><?= e($item['invt_ctrl_no'] ?: '-') ?></td>
                                        <td><?= e($item['item_description'] ?: $item['item_name']) ?></td>
                                        <td><?= e($item['issued_to'] ?: '-') ?></td>
                                        <td><?= e(labelize($item['status'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($items === []): ?><tr><td colspan="4">No inventory records are available yet.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php elseif ($staffTab === 'complete-inventory'): ?>
                <article class="panel full-width">
                    <div class="panel-head"><h2>Complete Inventory Search</h2></div>
                    <form method="get" class="search-bar">
                        <input type="hidden" name="route" value="inventory">
                        <input type="text" name="q" value="<?= e($inventorySearch) ?>" placeholder="Search by Invt. ctrl No, Item Description, Item Long Description, Item Code">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </form>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>Invt. Ctrl No</th><th>Item Description</th><th>Item Long Description</th><th>Item Code</th><th>Department</th><th>Issued To</th><th>Status</th><th>Actions</th></tr></thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td><?= e($item['invt_ctrl_no'] ?: '-') ?></td>
                                        <td><?= e($item['item_description'] ?: $item['item_name']) ?></td>
                                        <td><?= e(substr((string) ($item['item_long_description'] ?? ''), 0, 80)) ?></td>
                                        <td><?= e($item['item_code']) ?></td>
                                        <td><?= e($item['department_name'] ?: '-') ?></td>
                                        <td><?= e($item['issued_to'] ?: '-') ?></td>
                                        <td><?= e(labelize($item['status'])) ?></td>
                                        <td>
                                            <div class="action-inline">
                                                <a class="btn btn-secondary btn-small" href="<?= route_url('inventory-update') ?>&edit=<?= e((string) $item['id']) ?>">Edit</a>
                                                <form method="post" action="<?= route_url('inventory-delete') ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="inventory_id" value="<?= e((string) $item['id']) ?>">
                                                    <button type="submit" class="btn btn-danger btn-small">Delete</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($items === []): ?><tr><td colspan="8">No inventory records matched your search.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            <?php else: ?>
                <article class="panel">
                    <div class="panel-head"><h2>CSV Inventory Update</h2></div>
                    <form method="post" enctype="multipart/form-data" class="form-stack" action="<?= route_url('csv-upload') ?>">
                        <?= csrf_field() ?>
                        <label><span>Upload inventory CSV</span><input type="file" name="csv_file" accept=".csv" required></label>
                        <button type="submit" class="btn btn-primary">Import and Update Inventory</button>
                        <p class="muted">Expected headers include `Invt. ctrl No`, `Item Description`, `Item Long Description`, `Item Code`, and the related export columns. Import errors are logged with debug references.</p>
                    </form>
                    <div class="debug-card">
                        <strong>Debugging</strong>
                        <p>All handled exceptions are written to `storage/debug.log` with a reference code shown in the portal response.</p>
                    </div>
                </article>
                <article class="panel">
                    <div class="panel-head"><h2><?= $editingInventory ? 'Edit Inventory Item' : 'Add Inventory Item' ?></h2></div>
                    <form method="post" class="form-grid" action="<?= route_url('inventory-save') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="inventory_id" value="<?= e((string) ($editingInventory['id'] ?? '')) ?>">
                        <label><span>Invt. Ctrl No</span><input type="text" name="invt_ctrl_no" value="<?= e((string) ($editingInventory['invt_ctrl_no'] ?? '')) ?>" required></label>
                        <label><span>Item Code</span><input type="text" name="item_code" value="<?= e((string) ($editingInventory['item_code'] ?? '')) ?>" required></label>
                        <label><span>Item Description</span><input type="text" name="item_description" value="<?= e((string) ($editingInventory['item_description'] ?? '')) ?>" required></label>
                        <label><span>Item Long Description</span><textarea name="item_long_description" rows="3"><?= e((string) ($editingInventory['item_long_description'] ?? '')) ?></textarea></label>
                        <label><span>Department Name</span><input type="text" name="department_name" value="<?= e((string) ($editingInventory['department_name'] ?? '')) ?>"></label>
                        <label><span>Issued To</span><input type="text" name="issued_to" value="<?= e((string) ($editingInventory['issued_to'] ?? '')) ?>"></label>
                        <label><span>Issue Type</span><input type="text" name="issue_type" value="<?= e((string) ($editingInventory['issue_type'] ?? '')) ?>"></label>
                        <label><span>Lab Code</span><input type="text" name="lab_code" value="<?= e((string) ($editingInventory['lab_code'] ?? '')) ?>"></label>
                        <label><span>Quantity</span><input type="text" name="quantity" value="<?= e((string) ($editingInventory['quantity'] ?? '')) ?>"></label>
                        <label><span>Unit</span><input type="text" name="unit" value="<?= e((string) ($editingInventory['unit'] ?? '')) ?>"></label>
                        <label><span>Value</span><input type="text" name="value_text" value="<?= e((string) ($editingInventory['value_text'] ?? '')) ?>"></label>
                        <label><span>Net Effective Value</span><input type="text" name="net_eff_value" value="<?= e((string) ($editingInventory['net_eff_value'] ?? '')) ?>"></label>
                        <label><span>Status</span><select name="status"><option value="available" <?= (($editingInventory['status'] ?? '') === 'available') ? 'selected' : '' ?>>Available</option><option value="issued" <?= (($editingInventory['status'] ?? '') === 'issued') ? 'selected' : '' ?>>Issued</option><option value="maintenance" <?= (($editingInventory['status'] ?? '') === 'maintenance') ? 'selected' : '' ?>>Maintenance</option></select></label>
                        <label><span>Location</span><input type="text" name="location" value="<?= e((string) ($editingInventory['location'] ?? '')) ?>"></label>
                        <label><span>GIS No.</span><input type="text" name="gis_no" value="<?= e((string) ($editingInventory['gis_no'] ?? '')) ?>"></label>
                        <label><span>GIS Date</span><input type="text" name="gis_date" value="<?= e((string) ($editingInventory['gis_date'] ?? '')) ?>"></label>
                        <label><span>NC No.</span><input type="text" name="nc_no" value="<?= e((string) ($editingInventory['nc_no'] ?? '')) ?>"></label>
                        <label><span>NC Date</span><input type="text" name="nc_date" value="<?= e((string) ($editingInventory['nc_date'] ?? '')) ?>"></label>
                        <label><span>Source</span><input type="text" name="source_name" value="<?= e((string) ($editingInventory['source_name'] ?? '')) ?>"></label>
                        <label><span>QR View</span><input type="text" name="qr_view" value="<?= e((string) ($editingInventory['qr_view'] ?? '')) ?>"></label>
                        <label class="full-span"><span>Notes</span><textarea name="notes" rows="3"><?= e((string) ($editingInventory['notes'] ?? '')) ?></textarea></label>
                        <div class="form-actions full-span">
                            <button type="submit" class="btn btn-primary"><?= $editingInventory ? 'Update Inventory' : 'Save Inventory' ?></button>
                            <?php if ($editingInventory): ?><a class="btn btn-secondary" href="<?= route_url('inventory-update') ?>">Cancel Editing</a><?php endif; ?>
                        </div>
                    </form>
                </article>
            <?php endif; ?>
        </section>
    <?php elseif ($route === 'dashboard'): ?>
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
                    <label><span>Category</span><select name="category" required><option value="">Select</option><?php foreach (category_options() as $category): ?><option value="<?= e($category) ?>"><?= e($category) ?></option><?php endforeach; ?></select></label>
                    <label><span>Role</span><select name="role"><option value="student">Student</option><option value="admin">Admin</option></select></label>
                    <label><span>Password</span><input type="password" name="password" minlength="8" required></label>
                    <div class="form-actions full-span"><button type="submit" class="btn btn-primary">Save User</button></div>
                </form>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>Registered Users</h2></div>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Name</th><th>Department</th><th>Category</th><th>Role</th><th>Email</th></tr></thead>
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
                            <?php if ($items === []): ?><tr><td colspan="5">No inventory has been added yet.</td></tr><?php endif; ?>
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
                    <label><span>Select Item</span><select name="item_id" required><option value="">Choose available item</option><?php foreach (available_inventory_items() as $item): ?><option value="<?= e((string) $item['id']) ?>"><?= e($item['item_description'] ?: $item['item_name']) ?> (<?= e($item['invt_ctrl_no'] ?: $item['item_code']) ?>)</option><?php endforeach; ?></select></label>
                    <div class="form-actions full-span"><button type="submit" class="btn btn-primary">Send Issue OTP</button></div>
                </form>
            </article>
            <article class="panel">
                <div class="panel-head"><h2>Issue Queue</h2></div>
                <div class="list-stack">
                    <?php foreach ($pending as $entry): ?>
                        <div class="list-row"><div><strong><?= e($entry['item_name']) ?></strong><p><?= e($entry['user_name']) ?> | <?= e($entry['user_email']) ?></p></div><span class="pill"><?= e(labelize($entry['issue_status'])) ?></span></div>
                    <?php endforeach; ?>
                    <?php if ($pending === []): ?><p class="muted">Pending issue requests will appear here.</p><?php endif; ?>
                </div>
            </article>
        </section>
    <?php else: ?>
        <section class="panel"><h2>Page not available</h2><p class="muted">The requested route is either restricted or has not been set up yet.</p></section>
    <?php endif;
    render_footer();
});
