# 🐾 The Cat-alog Library

A secure, multi-page PHP/MySQL library management web application designed for student borrowing and catalog administration.

---

## 📋 Table of Contents

1. [Architectural Overview & Stack](#architectural-overview--stack)
2. [Database Schema & Reconstructed Design](#database-schema--reconstructed-design)
3. [Security Hardening Implemented](#security-hardening-implemented)
4. [Persistent Cloud Storage (S3 / Cloudflare R2)](#persistent-cloud-storage-s3--cloudflare-r2)
5. [Local Setup Instructions](#local-setup-instructions)
   - [Option A: Docker Compose (Recommended)](#option-a-docker-compose-recommended)
   - [Option B: Local MAMP / Native PHP & MySQL](#option-b-local-mamp--native-php--mysql)
6. [Administrative CLI Tools](#administrative-cli-tools)
   - [Database Installer](#database-installer)
   - [Initial Admin Provisioning](#initial-admin-provisioning)
   - [Image Sync to S3/R2](#image-sync-to-s3r2)
7. [Automated Regression Tests](#automated-regression-tests)
8. [Vercel & Cloud Deployment Guide](#vercel--cloud-deployment-guide)
   - [Vercel Deployment (Serverless PHP)](#vercel-deployment-serverless-php)
   - [Cloud MySQL Configuration](#cloud-mysql-configuration)
   - [Conventional Linux / Docker / VPS Deployment](#conventional-linux--docker--vps-deployment)
9. [Environment Variable Reference](#environment-variable-reference)

---

## 🏛️ Architectural Overview & Stack

- **Backend:** PHP 8.1+ / 8.2+ / 8.3+ with PDO MySQL.
- **Database:** MySQL 5.7+ / 8.0+ or MariaDB 10.3+.
- **Frontend:** Vanilla PHP views styled with custom CSS and Sniglet Google Typography.
- **Session Architecture:** Pluggable (`file` for native servers, `database` via `DatabaseSessionHandler` for serverless/distributed deployments).
- **Authentication:** Bcrypt passwords (`PASSWORD_DEFAULT`), session fixation protection via `session_regenerate_id()`, secure cookie parameters (`HttpOnly`, `SameSite=Lax`, conditional `Secure` on HTTPS).
- **Object Storage:** Pluggable storage driver (`local` filesystem for VPS/Docker/MAMP, `s3`/`r2` via `S3StorageClient` for serverless/Vercel).

---

## 🗄️ Database Schema & Reconstructed Design

The relational schema matching the application queries, foreign keys, and business rules:

1. **`users`**: Student accounts (`user_id`, `username`, `password`, `first_name`, `last_name`, `email`, `created_at`).
2. **`admin_users`**: Staff accounts (`admin_id`, `username`, `password`, `first_name`, `last_name`, `email`, `role`, `status`, `last_login`).
3. **`book_titles`**: Catalog bibliographic metadata (`title_id`, `title`, `author`, `category`, `published_year`, `published_month`, `book_image`, `status`, `total_copies`, `available_copies`).
4. **`book_copies`**: Physical library inventory items (`copy_id`, `title_id`, `book_id`, `copy_number`, `condition_status`, `acquisition_date`, `status`).
5. **`borrowed_books`**: Transaction loan records (`id`, `user_id`, `copy_id`, `title_id`, `book_id`, `borrow_date`, `due_date`, `return_date`, `status`, `renewal_count`, `fine_amount`).
6. **`password_resets`**: SHA-256 hashed single-use tokens expiring in 30 minutes (`id`, `user_id`, `token_hash`, `expires_at`, `used_at`).
7. **`rate_limits`**: Persistent endpoint attempt tracking by hashed IP (`id`, `rate_key`, `attempt_time`, `ip_address`).
8. **`sessions`**: Serverless session store (`id`, `data`, `access`).

### 🏷️ Concurrency-Safe Book ID Trigger

A database trigger `trg_book_copies_before_insert` generates standardized Book IDs automatically upon copy insertion:

$$\text{Format: } \{\text{TITLE\_2}\}\{\text{MONTH\_3}\}\{\text{DAY\_2}\}\{\text{YEAR\_4}\}\text{-}\{\text{CAT\_CODE}\}\{\text{TITLE\_ID\_3}\}\{\text{COPY\_NUM\_2}\}$$

*Example:* `"The Great Gatsby"`, Category Fiction, Title ID 1, Copy #1 added on April 10, 1925 $\rightarrow$ `THAPR101925-FIC00101`.

---

## 🛡️ Security Hardening Implemented

| Security Concern | Hardening Implemented |
|---|---|
| **SQL Injection** | Strict PDO prepared statements with parameterized queries. Allowlisted dynamic sort columns and directions. Disabled emulate prepares. |
| **Cross-Site Scripting (XSS)** | Universal output escaping helper `e()` applied across student and admin views. |
| **CSRF** | Cryptographic session tokens (`csrf_token()`, `csrf_field()`, `verify_csrf_token()`) enforced on all state-changing `POST` requests. |
| **Authentication & Fixation** | Passwords hashed with `PASSWORD_DEFAULT` (bcrypt), `session_regenerate_id(true)` upon successful login, strict inactivity timeout. |
| **Brute Force & Rate Limiting** | Database-backed rate limiting (`check_rate_limit()`) throttling login and registration attempts to 5 per 15-minute window per IP. |
| **Password Reset Enumeration** | Timing-safe responses; reset endpoints never disclose if an email or student number exists. |
| **File Upload Vulnerabilities** | Multilayer validation using `finfo(FILEINFO_MIME_TYPE)` (actual binary signature, not extension), randomized 16-byte hex filenames, `.htaccess` execution prevention in `uploads/`. |
| **Race Conditions in Borrowing** | Database transactions with row-level pessimistic locking (`FOR UPDATE`) on titles, user active loans, and copy inventory. Maximum 2 books per student and duplicate-title restrictions strictly enforced. |
| **Idempotent Returns** | Atomic status update checking `status IN ('borrowed', 'overdue', 'renewed')`. Repeated or concurrent return requests cannot increment inventory multiple times. |
| **Private Files Disclosure** | Root `.htaccess` and Vercel router blocking direct HTTP requests to `.env`, `.git`, `sql/`, `bin/`, `includes/`, and logs. |

---

## ☁️ Persistent Cloud Storage (S3 / Cloudflare R2)

Because serverless environments like Vercel have a **read-only ephemeral filesystem**, book cover images are handled through [`includes/storage.php`](file:///Users/christabelle.acedillo/Downloads/The-Catalog-Library-main/includes/storage.php).

### S3 / R2 Configuration

Set the following environment variables in `.env` (or Vercel Dashboard):

```ini
STORAGE_DRIVER=s3
S3_BUCKET=your-catalog-bucket
S3_REGION=auto
S3_KEY=your_access_key_id
S3_SECRET=your_secret_access_key
# For Cloudflare R2:
S3_ENDPOINT=https://<account_id>.r2.cloudflarestorage.com
S3_PUBLIC_URL=https://pub-<unique_hash>.r2.dev
```

### Bucket Access Policy (AWS S3)

To allow public read access for book cover images while keeping write permissions restricted to the server:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "PublicReadGetObject",
      "Effect": "Allow",
      "Principal": "*",
      "Action": "s3:GetObject",
      "Resource": "arn:aws:s3:::your-catalog-bucket/book-images/*"
    }
  ]
}
```

### CORS Configuration (Cloudflare R2 / AWS S3)

```json
[
  {
    "AllowedOrigins": ["*"],
    "AllowedMethods": ["GET", "HEAD"],
    "AllowedHeaders": ["*"],
    "MaxAgeSeconds": 3600
  }
]
```

---

## 🚀 Local Setup Instructions

### Option A: Docker Compose (Recommended)

1. Clone or download the repository:
   ```bash
   cd The-Catalog-Library-main
   ```

2. Copy the environment configuration:
   ```bash
   cp .env.example .env
   ```

3. Spin up the containers:
   ```bash
   docker compose up -d --build
   ```

4. The application will be available at:
   - Web App: `http://localhost:8080`
   - MySQL: `localhost:3307`

5. Create your administrator account:
   ```bash
   docker compose exec app php bin/create_admin.php
   ```

---

### Option B: Local MAMP / Native PHP & MySQL

1. Ensure MAMP or MySQL is running.
2. Copy `.env.example` to `.env`:
   ```bash
   cp .env.example .env
   ```
3. Set your database credentials in `.env`:
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=8889
   DB_NAME=the-catalog-library
   DB_USER=root
   DB_PASS=root
   DB_SOCKET=/Applications/MAMP/tmp/mysql/mysql.sock
   ```
4. Run the automated database installer:
   ```bash
   php bin/install_db.php --with-demo
   ```
5. Create your administrator account:
   ```bash
   php bin/create_admin.php
   ```
6. Access the web app in your browser (e.g. `http://localhost:8888/The-Catalog-Library-main`).

---

## 🛠️ Administrative CLI Tools

### Database Installer
Runs schema creation and loads optional demo books and student accounts:
```bash
# Production install (schema only, no demo accounts)
php bin/install_db.php

# Development install with demo catalog and student accounts
php bin/install_db.php --with-demo
```

### Initial Admin Provisioning
Prompts interactively for administrator credentials with password confirmation and minimum length checks, storing secure bcrypt hashes:
```bash
php bin/create_admin.php
```

### Image Sync to S3/R2
Synchronizes all existing local images in `uploads/book-images/` to your configured S3/Cloudflare R2 bucket:
```bash
php bin/migrate_images_to_s3.php
```

---

## 🧪 Automated Regression Tests

Run the full suite of unit, security, and database regression tests:
```bash
php tests/regression_test.php
```

The test runner verifies:
- `e()` XSS neutralization and entity encoding.
- `safe_redirect()` open-redirect prevention.
- `verify_csrf_token()` missing, invalid, and valid token handling.
- `check_rate_limit()` threshold blocking and clearing.
- `generate_book_id()` and MySQL trigger copy generation.
- User registration and unique constraints.
- Admin password verification.
- Password reset token hashing, expiry, and single-use atomic invalidation.
- 2-book borrowing limit and duplicate-title constraints.
- Idempotent return operations.
- Book archiving cascade and deletion constraints.
- Persistent S3/R2 Storage Client and URL formatting.
- `DatabaseSessionHandler` lifecycle (write, read, regenerate, destroy).
- Vercel routing rules and private file blocking.

---

## ☁️ Vercel & Cloud Deployment Guide

### Vercel Deployment (Serverless PHP)

Deploying a multi-page PHP/MySQL application on Vercel uses the community runtime `vercel-php@0.8.0`.

#### 1. Requirements for Vercel:
- **External MySQL Database:** Vercel does not host databases. You must use a cloud MySQL provider such as PlanetScale, AWS RDS, Aiven, or Railway.
- **Shared Session Storage:** Serverless functions are stateless. Enable database sessions in your Vercel project environment variables:
  ```ini
  SESSION_DRIVER=database
  VERCEL=1
  ```
- **Persistent Media Storage:** The Vercel runtime filesystem is read-only (`/var/task`). Book cover uploads use Cloudflare R2 or AWS S3 (`STORAGE_DRIVER=s3`). Existing images in `uploads/book-images/` are committed and deployed as static assets.

#### 2. Vercel Configuration (`vercel.json`)
A pre-configured `vercel.json` is included in the project root:
- Static assets (`/assets/*`, `/uploads/*`) are served directly by Vercel's Edge CDN.
- Private directories (`/sql/`, `/bin/`, `/includes/`, `/tests/`, `/.env*`, `/.git*`) are blocked with HTTP 403.
- All application requests route through `/api/index.php`.

#### 3. Environment Variables in Vercel Dashboard:
Configure the following under **Project Settings > Environment Variables**:

| Variable | Recommended Production Value |
|---|---|
| `DB_HOST` | Hostname of your cloud MySQL instance |
| `DB_PORT` | `3306` |
| `DB_NAME` | `the_catalog_library` |
| `DB_USER` | Dedicated application user |
| `DB_PASS` | Strong password |
| `DB_SSL_VERIFY` | `true` |
| `SESSION_DRIVER` | `database` |
| `STORAGE_DRIVER` | `s3` (or `r2`) |
| `S3_BUCKET` | Your bucket name |
| `S3_KEY` | Storage Access Key ID |
| `S3_SECRET` | Storage Secret Access Key |
| `S3_ENDPOINT` | e.g. `https://<account_id>.r2.cloudflarestorage.com` |
| `S3_PUBLIC_URL` | e.g. `https://pub-<hash>.r2.dev` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |

---

### Cloud MySQL Configuration

1. **Provider Compatibility:**
   - Any MySQL 5.7+ or 8.0+ service supporting InnoDB, foreign keys, transactions, and triggers (e.g. AWS RDS MySQL, PlanetScale, DigitalOcean Managed MySQL, Railway, or Aiven).
2. **Schema Provisioning:**
   - Run the installer pointing to your cloud database:
     ```bash
     DB_HOST=your-cloud-db.com DB_PORT=3306 DB_USER=app_user DB_PASS=pass php bin/install_db.php
     ```
3. **Provision Initial Admin:**
   - Run the admin provisioning tool against the cloud database:
     ```bash
     DB_HOST=your-cloud-db.com DB_PORT=3306 DB_USER=app_user DB_PASS=pass php bin/create_admin.php
     ```

---

### Conventional Linux / Docker / VPS Deployment

For standard hosting (DigitalOcean, AWS EC2, Linode, Ubuntu/Debian with Apache/Nginx):
1. Install Apache2 with `mod_rewrite` and `mod_headers`:
   ```bash
   sudo a2enmod rewrite headers
   ```
2. Point Apache `DocumentRoot` to the project directory.
3. The included `.htaccess` automatically manages security headers, denies access to private files, and enforces execution protection.
4. Run `php bin/install_db.php` and `php bin/create_admin.php`.

---

## 🔑 Environment Variable Reference

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `production` | Environment mode (`production`, `development`, `testing`). |
| `APP_DEBUG` | `false` | Enables detailed error output when set to `true` (development only). |
| `APP_URL` | `http://localhost:8888` | Base URL of the application. |
| `DB_HOST` | `127.0.0.1` | MySQL server host. |
| `DB_PORT` | `3306` (or `8889` for MAMP) | MySQL port. |
| `DB_NAME` | `the-catalog-library` | Database name. |
| `DB_USER` | `root` | Database username. |
| `DB_PASS` | `root` | Database password. |
| `DB_SOCKET` | `null` | Unix socket path (e.g. `/Applications/MAMP/tmp/mysql/mysql.sock`). |
| `DB_SSL_CA` | `null` | Path to SSL Certificate Authority bundle for cloud databases. |
| `DB_SSL_VERIFY` | `true` | Verify server SSL certificate. |
| `SESSION_DRIVER` | `file` | Session handler: `file` or `database`. |
| `STORAGE_DRIVER` | `local` | Storage driver: `local`, `s3`, or `r2`. |
| `S3_BUCKET` | `null` | S3 / R2 Bucket name. |
| `S3_REGION` | `us-east-1` | S3 Region (`auto` for Cloudflare R2). |
| `S3_KEY` | `null` | S3 / R2 Access Key ID. |
| `S3_SECRET` | `null` | S3 / R2 Secret Access Key. |
| `S3_ENDPOINT` | `null` | Custom S3 / R2 endpoint URL. |
| `S3_PUBLIC_URL` | `null` | Custom CDN or public delivery URL. |
| `MAIL_ENABLED` | `false` | Enable self-service password reset emails via SMTP. |
| `MAIL_HOST` | `smtp.mailtrap.io` | SMTP server host. |
| `MAIL_PORT` | `587` | SMTP port. |
| `MAIL_USERNAME` | `null` | SMTP username. |
| `MAIL_PASSWORD` | `null` | SMTP password. |
| `MAIL_ENCRYPTION` | `tls` | SMTP encryption (`tls` or `ssl`). |
