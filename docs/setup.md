# Development Setup

## 1. Hosts File

Add to `C:\Windows\System32\drivers\etc\hosts` (run Notepad as Administrator):

```
127.0.0.1 local.ds3.com
```

## 2. Apache VHost

The vhost is already added to `C:\xampp\apache\conf\extra\httpd-vhosts.conf`. Confirm it looks like:

```apache
<VirtualHost *:80>
    DocumentRoot "C:/xampp/htdocs/ds3"
    ServerName local.ds3.com
    <Directory "C:/xampp/htdocs/ds3">
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## 3. Environment Variables

Add `SetEnv` directives **inside the VirtualHost block** in `httpd-vhosts.conf`, or in a separate
`C:\xampp\apache\conf\extra\httpd-ds3-env.conf` included from `httpd.conf`.

Minimum required for local dev:

```apache
SetEnv ENVIRONMENT local
SetEnv DB_HOST_NAME localhost
SetEnv DB_NAME ds3
SetEnv DB_USER_NAME root
SetEnv DB_PASSWORD 
SetEnv ADMIN_DB_USER_NAME root
SetEnv ADMIN_DB_PASSWORD 
SetEnv AUDIT_DB_HOST_NAME localhost
SetEnv AUDIT_DB_NAME ds3_audit
SetEnv AUDIT_DB_USER_NAME root
SetEnv AUDIT_DB_PASSWORD 
SetEnv LOG_LEVEL 7
SetEnv LOG_DIR C:/xampp/htdocs/ds3/storage/logs
SetEnv DB_SSL false
```

Restart Apache after any change to vhost config.

## 4. Database

```sql
CREATE DATABASE ds3     CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE ds3_audit CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Import the initial schema:

```bash
mysql -u root ds3      < C:/xampp/htdocs/ds3/database-migrate/001_initial_schema.sql
mysql -u root ds3_audit < C:/xampp/htdocs/ds3/database-migrate/002_audit_schema.sql
```

## 5. Composer

```bash
cd C:/xampp/htdocs/ds3
composer install
```

This installs the Reunite framework packages from GitHub and third-party dependencies (AWS SDK, mPDF, etc.).

## 6. Verify

| URL | Expected result |
|-----|----------------|
| `http://local.ds3.com/` | Redirect to login page |
| `http://local.ds3.com/api/v2/admin/health` | `{"code":200,"message":"DS3 API is running"}` |
| `http://local.ds3.com/v2/portals/admin/pages/login.php` | Login page |

## Troubleshooting

| Problem | Check |
|---------|-------|
| Blank page on API | PHP error log at `storage/logs/app.log`; run `php -l api/v2/bootstrap.php` |
| 503 "Database scope not configured" | Env vars not set; confirm `SetEnv` in vhost and restart Apache |
| Login page shows "DS3 Admin" but form doesn't post | Check browser Network tab for `/api/v2/admin/user/login` response |
| `vendor/` missing | Run `composer install` |
