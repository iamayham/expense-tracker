# Expense Tracker

A web-based personal finance tracker built with PHP, MySQL, PDO, and vanilla JavaScript.

## Live Demo
- [https://trackexpenses.org/](https://trackexpenses.org/)

## Features
- User registration, login, logout, and forgot-password flow
- Secure password hashing, session handling, and CSRF protection
- Dashboard with balance, monthly expense summary, charts, and recent transactions
- Expense management with add, edit, delete, category filters, date filters, and paid / not paid status
- Income management with add, edit, delete, and date filtering
- Default and custom categories
- User settings for profile, password, theme, notifications, and preferred currency
- Currency-aware formatting across the app

## Requirements
- XAMPP with Apache and MySQL running, or Docker
- PHP 8.2 recommended
- MySQL 5.7+ or MariaDB equivalent

## Quick Start (XAMPP)
1. Place the project in `htdocs/expense-tracker`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `expense_tracker` if it does not already exist.
5. Import `database/schema.sql`.
6. Open the app at `http://localhost/expense-tracker/`.

### Database Config (`config/db.php`)
For XAMPP, you usually do not need environment variables.

Default values:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=expense_tracker
DB_USER=root
DB_PASS=
```

Also supported (optional environment variables):

```env
MYSQLHOST=localhost
MYSQLPORT=3306
MYSQLDATABASE=expense_tracker
MYSQLUSER=root
MYSQLPASSWORD=
```

## Docker
Build:

```bash
docker build -t expense-tracker .
```

Run:

```bash
docker run -p 8080:80 expense-tracker
```

You still need a reachable MySQL database and correct DB environment variables.

## Demo Account
- Email: `demo@example.com`
- Password: `password`

## Security Notes
- All database queries use prepared statements through PDO
- Passwords are hashed with `password_hash()`
- Login regenerates the session ID
- Mutating requests require a valid CSRF token
- Output is escaped in views

## Passkeys (WebAuthn / Biometrics)

This project supports passkey-based login for:
- iPhone Safari (Face ID / Touch ID)
- Android Chrome (Fingerprint / screen lock biometrics)

### 1) Install dependency

```bash
composer install
```

### 2) Database table

The app auto-creates `user_passkeys` in `config/db.php`, or you can import from `database/schema.sql`.

Stored fields:
- `user_id`
- `credential_id`
- `public_key`
- `sign_count`

### 3) API endpoint

Passkey API: `api/webauthn.php` (POST + CSRF required)

Actions:
- `register_begin`
- `register_finish`
- `login_begin`
- `login_finish`

### 4) Frontend flow

Login page includes:
- `Register with Face ID / Fingerprint`
- `Login with Face ID / Fingerprint`

Implemented in `assets/js/webauthn.js` using:
- `navigator.credentials.create()` for registration
- `navigator.credentials.get()` for authentication
- platform authenticator and required user verification (provided by server options)

### 5) Security controls

- HTTPS is required (localhost allowed for local dev)
- Challenge is generated per ceremony and stored in session
- Challenge expires after 5 minutes
- RP ID and origin are checked before verification
- Sign counter is stored and updated to reduce replay risk

### 6) Fallback UX

If passkey is unavailable or fails, users can continue using email/password login on the same page.

## Notes
- Default categories are shared, while custom categories belong to each user
- The sample data includes starter expenses, income entries, and categories for the demo account
- `config/db.php` contains lightweight compatibility checks so older local databases can pick up newer columns and tables automatically
