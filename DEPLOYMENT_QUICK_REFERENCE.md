# Deployment Quick Reference

Quick checklist for deploying to `kawan.safwanhakim.com`

## 🚀 Quick Start

### 1. Prepare Locally
```bash
# Linux/Mac
chmod +x deploy-prepare.sh
./deploy-prepare.sh

# Windows
deploy-prepare.bat
```

### 2. Upload to Server
- Upload all files from `deployment/` to `public_html/` via FTP/SFTP

### 3. Set Permissions (SSH)
```bash
cd ~/public_html
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R 775 api/storage api/bootstrap/cache
chmod 775 api/database
```

### 4. Configure Environment
```bash
cd api
cp env.template .env
# Edit .env with production settings
```

**For No SSH Access:**
1. Open `api/public/setup.php` and set a strong password
2. Access: `https://kawan.safwanhakim.com/api/setup.php`
3. Use the web interface to run commands
4. **Delete setup.php after setup!**

**For SSH Access:**
```bash
php artisan key:generate
```

### 5. Install Dependencies
```bash
cd api
composer install --no-dev --optimize-autoloader
```

### 6. Run Migrations
```bash
cd api
php artisan migrate --force
```

### 7. Test
- Frontend: https://kawan.safwanhakim.com
- API: https://kawan.safwanhakim.com/api/health

## 📝 Essential .env Settings

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://kawan.safwanhakim.com

# Database (choose one)
DB_CONNECTION=sqlite
DB_DATABASE=/full/path/to/api/database/database.sqlite

# OR MySQL
# DB_CONNECTION=mysql
# DB_HOST=localhost
# DB_DATABASE=your_db
# DB_USERNAME=your_user
# DB_PASSWORD=your_pass

# Mail
MAIL_MAILER=smtp
MAIL_HOST=mail.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=your-email@yourdomain.com
MAIL_PASSWORD=your-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@kawan.safwanhakim.com
```

## 🔧 Common Commands

```bash
# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# View logs
tail -f api/storage/logs/laravel.log

# Run migrations
php artisan migrate

# Generate key
php artisan key:generate
```

## 🔐 Important: Update CORS Configuration

Before deploying, update these files:

**`api/config/cors.php`:**
```php
'allowed_origins' => [
    'https://kawan.safwanhakim.com',
],
```

**`api/config/sanctum.php` or `.env`:**
```env
SANCTUM_STATEFUL_DOMAINS=kawan.safwanhakim.com
```

## 🐛 Quick Troubleshooting

| Issue | Solution |
|-------|----------|
| 500 Error | Check permissions: `chmod -R 775 api/storage` |
| 404 on API | Check `.htaccess` files exist |
| CORS Error | Update `api/config/cors.php` |
| Database Error | Check `.env` DB credentials |
| Email Not Sending | Verify SMTP settings in `.env` |

## 📞 Need Help?

See `DEPLOYMENT_GUIDE.md` for detailed instructions.

