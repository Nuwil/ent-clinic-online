# Quick Start Guide

## 2-Minute Setup

### Prerequisites
- XAMPP installed and running
- MySQL/MariaDB service active

### Steps

1. **Database Setup**
   ```bash
   # Import the database schema
   mysql -u root < e:\xamppFolder\htdocs\ENT-clinic-online\ent-app\database\schema.sql
   ```

2. **Access the Application**
   - URL: http://localhost/ENT-clinic-online/ent-app/public/
   - Username: admin
   - Password: admin123

### What's Included
- ✅ Patient management system
- ✅ Visit tracking
- ✅ Recording management
- ✅ Analytics dashboard
- ✅ Settings and data management

### First Steps
1. Login with admin credentials
2. Navigate to "Patients" and add a test patient
3. View "Analytics" to see statistics
4. Explore "Settings" for data management
5. Try "Recordings" to manage audio/video files

### API Testing
Test the API directly:
```
GET http://localhost/ENT-clinic-online/ent-app/public/api/health
GET http://localhost/ENT-clinic-online/ent-app/public/api/patients
```

### Troubleshooting

**Database Connection Error**
- Verify MySQL is running in XAMPP
- Check database credentials in `config/config.php`
- Ensure schema.sql was imported

**Page Not Loading**
- Verify .htaccess is present in public/
- Check browser console for errors
- Ensure you're accessing http://localhost/ENT-clinic-online/ent-app/public/

**API Returns 404**
- Verify .htaccess is present in public/
- Check if mod_rewrite is enabled in Apache
- Test /api/health endpoint first

### Next Steps
- Customize patient fields in database
- Add more users in users table
- Implement file uploads
- Add email notifications
- Deploy to production server

See README.md for detailed documentation.
