# Expense Tracker

A web-based personal finance tracker built with PHP, MySQL, PDO, and vanilla JavaScript. It helps users manage expenses, record income, organize categories, and monitor balance changes from a simple dashboard.

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

## Project Structure
```text
expense-tracker/
├── index.php
├── Dockerfile
├── auth/
│   ├── forgot-password.php
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── api/
│   ├── categories.php
│   ├── expenses.php
│   └── settings.php
├── assets/
│   ├── css/style.css
│   ├── images/
│   └── js/app.js
├── config/
│   └── db.php
├── database/
│   └── schema.sql
├── includes/
│   ├── footer.php
│   ├── functions.php
│   └── header.php
├── pages/
│   ├── categories.php
│   ├── expenses.php
│   ├── income.php
│   └── settings.php
└── README.md
```

## Requirements
- XAMPP with Apache and MySQL running, or Docker
- PHP 8.2 recommended
- MySQL 5.7+ or MariaDB equivalent

## Local Setup With XAMPP
1. Place the project in `htdocs/expense-tracker`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. Create a database named `expense_tracker` if it does not already exist.
5. Import `database/schema.sql`.
6. Open the app at `http://localhost/expense-tracker`.

For XAMPP, you usually do not need environment variables.
Set your database credentials directly in `config/db.php` (`$dbHost`, `$dbPort`, `$dbName`, `$dbUser`, `$dbPass`), then start Apache + MySQL and open `http://localhost/expense-tracker/`.

Default values in `config/db.php`:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=expense_tracker
DB_USER=root
DB_PASS=
```

Typical XAMPP local settings:

```text
Host: localhost
Port: 3306
Database: expense_tracker
Username: root
Password: (empty by default unless you changed it)
App URL: http://localhost/expense-tracker/
```

If your MySQL settings are different, update `config/db.php` to match your XAMPP MySQL credentials.

`config/db.php` also supports environment variables (optional):

```env
MYSQLHOST=localhost
MYSQLPORT=3306
MYSQLDATABASE=expense_tracker
MYSQLUSER=root
MYSQLPASSWORD=
```

Docker users can pass the same variables when running the container:

```bash
docker run -p 8080:80 \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=3306 \
  -e DB_NAME=expense_tracker \
  -e DB_USER=root \
  -e DB_PASS=your_password \
  expense-tracker
```

## Docker
A basic `Dockerfile` is included.

Build the image:

```bash
docker build -t expense-tracker .
```

Run the container:

```bash
docker run -p 8080:80 expense-tracker
```

You still need a MySQL database available and the correct DB environment variables when running the container.

## Demo Account
- Email: `demo@example.com`
- Password: `password`

## Install As App (PWA)
- Open the live site over HTTPS (for localhost, service worker works in modern browsers).
- In Chrome/Edge: open the browser menu and choose `Install app` / `Add to Home screen`.
- In Safari (iPhone): tap `Share` -> `Add to Home Screen`.
- After install, the app opens in standalone mode like a native app.

## Security Notes
- All database queries use prepared statements through PDO
- Passwords are hashed with `password_hash()`
- Login regenerates the session ID
- Mutating requests require a valid CSRF token
- Output is escaped in views

## Notes
- Default categories are shared, while custom categories belong to each user
- The sample data includes starter expenses, income entries, and categories for the demo account
- `config/db.php` contains lightweight compatibility checks so older local databases can pick up newer columns and tables automatically
