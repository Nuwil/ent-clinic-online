# Setup Guide - ENT Clinic Online

## Complete Installation Guide

### Prerequisites
- Windows/Mac/Linux with XAMPP installed
- MySQL 5.7+ or MariaDB
- Apache 2.4+ with mod_rewrite enabled
- PHP 7.4+

### Step 1: Database Configuration

#### Option A: MySQL Command Line
```bash
# Navigate to XAMPP directory
cd C:\xampp\mysql\bin

# Import the schema
mysql -u root < "path\to\database\schema.sql"
```

#### Option B: phpMyAdmin
1. Open http://localhost/phpmyadmin
2. Create new database: `ent_clinic`
3. Import `database/schema.sql` using the import tool

### Step 2: Web Root Configuration

Ensure the public directory is the web root:
- Copy entire `ent-app` folder to `htdocs/ENT-clinic-online/`
- Access via: http://localhost/ENT-clinic-online/ent-app/public/

### Step 3: Configuration Files

#### Edit config/config.php
```php
<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ent_clinic');
define('DB_USER', 'root');
define('DB_PASS', '');
?>
```

### Step 4: Verify Installation

1. **Check Database**
   ```bash
   mysql -u root -e "USE ent_clinic; SHOW TABLES;"
   ```
   Should show 7 tables

2. **Test API**
   - Visit: http://localhost/ENT-clinic-online/ent-app/public/api/health
   - Should return JSON response

3. **Test Application**
   - Visit: http://localhost/ENT-clinic-online/ent-app/public/
   - Login with admin/admin123

### Step 5: Enable Apache Modules

Ensure mod_rewrite is enabled in Apache:

1. Edit `C:\xampp\apache\conf\httpd.conf`
2. Uncomment (remove #): `LoadModule rewrite_module modules/mod_rewrite.so`
3. Restart Apache

Verify in XAMPP Control Panel:
- Click Config > Apache (httpd.conf)
- Search for "LoadModule rewrite_module"
- Ensure not commented out

### Step 7: File Permissions

Ensure proper write permissions:
```bash
# On Windows, right-click folder > Properties > Security
# Grant Read/Write to SYSTEM and Administrators

# Public directory should be writable for caching
# Log files (if any) should be writable
```

### Step 8: Testing Each Component

#### Test Database Connection
```php
<?php
require_once 'config/Database.php';
$db = Database::getInstance();
echo "Connected successfully";
?>
```

#### Test API Endpoints
```bash
# List patients
curl http://localhost/ENT-clinic-online/ent-app/public/api/patients

# Create patient
curl -X POST http://localhost/ENT-clinic-online/ent-app/public/api/patients \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe",...}'
```

#### Test Frontend
- Load http://localhost/ENT-clinic-online/ent-app/public/
- Navigate through pages
- Verify charts render on Analytics page

### Troubleshooting

#### "Database connection failed"
- Check MySQL is running
- Verify credentials in config.php
- Ensure ent_clinic database exists

#### "404 Not Found" on API calls
- Enable mod_rewrite in Apache
- Check .htaccess exists in public/
- Verify RewriteBase in .htaccess matches your path

#### "npm: command not found"
- Install Node.js from nodejs.org
- Add npm to system PATH
- Restart terminal/command prompt

#### "Vue app not loading"
- Run `npm run build` again
- Clear browser cache (Ctrl+Shift+Delete)
- Check browser console for errors

#### "Blank page"
- Check browser console for JavaScript errors
- Verify index.html loads
- Check network tab in DevTools

### Production Deployment

#### Security Checklist
- [ ] Change default admin password
- [ ] Disable debug mode (APP_DEBUG=false)
- [ ] Use HTTPS/SSL
- [ ] Hide sensitive files (.env, config)
- [ ] Set proper file permissions
- [ ] Enable database backups
- [ ] Configure firewall rules

#### Performance Tuning
- Enable GZIP compression in Apache
- Set cache headers for static assets
- Use CDN for dependencies
- Optimize database queries
- Monitor server resources

### Backup and Restore

#### Backup Database
```bash
mysqldump -u root ent_clinic > backup_ent_clinic.sql
```

#### Restore Database
```bash
mysql -u root < backup_ent_clinic.sql
```

#### Backup Application Files
```bash
# Backup entire application directory
xcopy "ent-app" "ent-app-backup" /E /I
```

### Maintenance

#### Regular Tasks
- Monitor database size
- Clean old activity logs
- Review error logs
- Update dependencies: `npm audit fix`
- Backup patient data monthly

#### Health Checks
- Verify API health: `/api/health`
- Check database backups
- Review system logs
- Test disaster recovery plan

### Support Resources
- README.md - Full documentation
- API_DOCS.md - API reference
- Check browser console for errors
- Review server error logs
