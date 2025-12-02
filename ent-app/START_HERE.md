# Start Here - ENT Clinic Online

## Welcome! 👋

This is the **ENT Clinic Online** application - a comprehensive web system for managing ENT (Ear, Nose, Throat) patient records, visits, and medical information.

## What This App Does

1. **📋 Patient Management** - Add, edit, view, and delete patient records
2. **🏥 Visit Tracking** - Record detailed patient visits with diagnosis and treatment
3. **🎬 Recording Management** - Track audio, video, endoscopy, and imaging recordings
4. **📊 Analytics** - View charts and statistics about visits and ENT cases
5. **⚙️ Settings** - Manage application configuration and data

## Quick Start (1 minute)

### 1. Setup Database
```bash
# Use phpMyAdmin or command line
mysql -u root < database/schema.sql
```

### 2. Open Application
```
http://localhost/ENT-clinic-online/ent-app/public/
```

### 3. Login
- **Username**: admin
- **Password**: admin123

## Application Structure

### Frontend (what you see)
- Located in: `public/pages/`
- Built with PHP Server-Side Rendering
- Pages: Patients, Recordings, Analytics, Settings

### Backend (what handles data)
- Located in: `api/`
- PHP REST API
- Endpoints for patients, visits, recordings, analytics

### Database (where data lives)
- MySQL/MariaDB
- Database: `ent_clinic`
- 7 tables with all patient and medical information

## File Locations

| Purpose | Location |
|---------|----------|
| View/Access App | `http://localhost/ENT-clinic-online/ent-app/public/` |
| API Endpoints | `/api/` (e.g., `/api/patients`) |
| Frontend Code | `public/pages/*.php` |
| Backend Code | `api/*.php` |
| Database Schema | `database/schema.sql` |
| Configuration | `config/config.php` |

## Using the Application

### Patient Management
1. Click "Patients" in navigation
2. Click "Add New Patient"
3. Fill in patient details
4. Click "Save Patient"
5. View, edit, or delete patient from list

### Visit Tracking
1. Click "Patients"
2. Click on a patient name
3. Click "Add Visit"
4. Fill visit details (diagnosis, treatment, etc.)
5. Click "Save Visit"
6. View visit history in timeline

### Analytics
1. Click "Analytics"
2. View ENT Distribution (pie chart)
3. View Weekly Visits (bar chart)
4. See monthly statistics

### Settings
1. Click "Settings"
2. Update application information
3. Manage database backups
4. Export/import patient data

## Technology Stack

| Component | Technology |
|-----------|-----------|
| Frontend | PHP Server-Side Rendering |
| Backend | PHP 7.4+ |
| Database | MySQL 5.7+ / MariaDB |
| Server | Apache 2.4+ |

## API Reference

### Get All Patients
```
GET /api/patients
Response: { status: "success", data: { patients: [...], total: 5 } }
```

### Create Patient
```
POST /api/patients
Body: { first_name, last_name, email, phone, gender, ... }
```

### Get Patient
```
GET /api/patients/{id}
```

### Add Visit
```
POST /api/visits
Body: { patient_id, visit_date, visit_type, chief_complaint, diagnosis, ... }
```

### Get Analytics
```
GET /api/analytics
Response: { ent_distribution: {...}, weekly_visits: {...} }
```

## Common Tasks

### Add a Patient
1. Navigate to Patients page
2. Click "Add New Patient"
3. Enter name, DOB, contact info
4. Click "Save Patient"

### Record a Visit
1. Go to Patients page
2. Click on patient name
3. Click "Add Visit"
4. Fill in visit details
5. Save

### View Analytics
1. Click "Analytics" tab
2. Charts show ENT distribution and weekly visits
3. See monthly summary below

### Export Data
1. Go to Settings
2. Click "Export JSON"
3. Saves patient and visit data

### Backup Database
1. Go to Settings
2. Click "Create Database Backup"
3. Backup created with timestamp

## Troubleshooting

### App won't load
- Check: http://localhost/ENT-clinic-online/ent-app/public/api/health
- If error: run `npm run build` again
- Clear browser cache (Ctrl+Shift+Delete)

### Can't see data
- Verify database: `mysql -u root -e "USE ent_clinic; SELECT * FROM patients;"`
- Check network tab in browser DevTools
- Look at API response in Network tab

### Charts not showing
- Check browser console for errors
- Verify API returns data: `/api/analytics`
- Run `npm run build` to rebuild with Chart.js

### Login issues
- Default credentials: admin/admin123
- Check database has users table
- Verify database connection in config/config.php

## Documentation

- **README.md** - Full project documentation
- **QUICKSTART.md** - 5-minute setup guide
- **SETUP_GUIDE.md** - Detailed installation instructions
- **API_DOCS.md** - API endpoint reference
- **PROJECT_SUMMARY.md** - Architecture and features
- **FILE_MANIFEST.md** - File structure and purposes

## Next Steps

1. ✅ Database imported
2. ✅ Frontend built
3. ✅ Application running
4. 👉 Add some test patients
5. 👉 Record some visits
6. 👉 View analytics
7. 👉 Customize as needed

## Need Help?

1. Check the relevant documentation file above
2. Review error messages in browser console
3. Check API responses in Network tab
4. Verify database connection
5. Review server error logs

## Important Files

- **Database**: `database/schema.sql` - Complete schema
- **Config**: `config/config.php` - Database settings
- **API Entry**: `public/api.php` - REST API router
- **Frontend Entry**: `public/index.php` - PHP frontend router
- **Navigation**: `frontend/src/App.vue` - Main Vue component

---

**Happy coding! 🎉 Start by exploring the Patients page.**
