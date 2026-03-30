# Inventory Management Portal

PHP + MySQL based lab portal for `ipserver.in` with session management, remember-cookie support, student registration, inventory records, OTP-verified issue/return flow, and CSV inventory import.

## Core Features

- Student self-registration with department/year dropdowns
- Admin login plus automatic default admin seeding
- Secure session handling with optional remember-me cookie
- Inventory register with status tracking
- OTP-based inventory issue on student email
- Student-driven inventory return with OTP verification
- CSV upload for bulk inventory import/update
- Responsive, polished dashboard for admin and student roles

## Tech Stack

- PHP
- MySQL / phpMyAdmin
- Plain CSS frontend

## Project Structure

- `index.php`: root entry point
- `public/index.php`: portal controller and route handling
- `public/assets/app.css`: UI styling
- `app/`: config, database, auth, helpers
- `database/schema.sql`: table definitions
- `deploy.php`: deployment script, do not modify

## Required Environment Variables

Set these on your hosting/server before deploy:

- `APP_NAME=Lab Inventory Portal`
- `APP_URL=https://ipserver.in`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_NAME=lab_inventory_portal`
- `DB_USER=your_mysql_user`
- `DB_PASS=your_mysql_password`
- `MAIL_FROM=noreply@ipserver.in`
- `MAIL_ENABLED=true`
- `ADMIN_NAME=Lab Administrator`
- `ADMIN_EMAIL=admin@ipserver.in`
- `ADMIN_PASSWORD=Admin@123`

## Default Admin

On first successful database boot, one admin is created automatically if no admin exists:

- Email: value of `ADMIN_EMAIL`
- Password: value of `ADMIN_PASSWORD`

Change these server env values before production use.

## CSV Import Format

Expected CSV headers:

- `item_code`
- `item_name`
- `category`
- `brand`
- `serial_number`
- `location`
- `notes`

`item_code` is treated as unique. Re-importing the same code updates the record.

## OTP Email Notes

- Portal uses PHP `mail()` for OTP delivery.
- If server mail is unavailable, messages are written to `storage/mail.log`.
- This makes local testing possible even before live mail is configured.

## Workflow Summary

1. Student registers and logs in.
2. Admin adds/imports inventory.
3. Admin selects a student and inventory item, then sends issue OTP.
4. Student verifies issue OTP from dashboard.
5. Item becomes officially issued.
6. Student starts return from dashboard.
7. Return OTP is sent to student email.
8. Student verifies return OTP.
9. Item becomes available again.

## Notes

- Database tables are auto-created from `database/schema.sql` on app boot.
- `deploy.php` has been left untouched as requested.
