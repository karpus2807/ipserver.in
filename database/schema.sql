CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    department VARCHAR(120) NOT NULL,
    year_level VARCHAR(80) NOT NULL,
    enrollment_no VARCHAR(80) NOT NULL,
    phone VARCHAR(30) DEFAULT '',
    role ENUM('admin', 'student') NOT NULL DEFAULT 'student',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    remember_token VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_code VARCHAR(80) NOT NULL UNIQUE,
    item_name VARCHAR(150) NOT NULL,
    category VARCHAR(80) NOT NULL,
    brand VARCHAR(120) DEFAULT '',
    serial_number VARCHAR(120) DEFAULT '',
    status ENUM('available', 'issued', 'maintenance') NOT NULL DEFAULT 'available',
    location VARCHAR(120) DEFAULT '',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    item_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    issued_by INT UNSIGNED DEFAULT NULL,
    issue_status ENUM('pending_otp', 'issued', 'cancelled') NOT NULL DEFAULT 'pending_otp',
    return_status ENUM('not_requested', 'otp_sent', 'returned') NOT NULL DEFAULT 'not_requested',
    issue_otp VARCHAR(12) DEFAULT NULL,
    issue_otp_expires_at DATETIME DEFAULT NULL,
    issue_verified_at DATETIME DEFAULT NULL,
    issued_at DATETIME DEFAULT NULL,
    return_requested_at DATETIME DEFAULT NULL,
    return_otp VARCHAR(12) DEFAULT NULL,
    return_otp_expires_at DATETIME DEFAULT NULL,
    return_verified_at DATETIME DEFAULT NULL,
    returned_at DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transactions_item FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transactions_issuer FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE SET NULL
);
