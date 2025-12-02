# Quick Index - ENT Clinic Online

## Quick Links
- **Frontend**: http://localhost/ENT-clinic-online/ent-app/public/
- **API**: http://localhost/ENT-clinic-online/ent-app/public/api/
- **API Health**: http://localhost/ENT-clinic-online/ent-app/public/api/health

## Key Files
- **Main DB Schema**: `database/schema.sql`
- **Frontend Config**: `frontend/vite.config.js`
- **Backend Config**: `config/config.php`
- **Routes**: `public/api.php` (API) and `public/index.php` (Frontend)
- **Styles**: `frontend/src/css/style.css`

## Main Pages
1. **Dashboard** - Overview and statistics
2. **Patients** - Patient management (CRUD)
3. **Recordings** - Recording management
4. **Analytics** - Charts and reports
5. **Settings** - Configuration and data management

## API Endpoints Summary
- `/api/patients` - Patient CRUD (5 methods)
- `/api/recordings` - Recording CRUD (5 methods)
- `/api/visits` - Visit CRUD (5 methods)
- `/api/analytics` - Analytics data
- `/api/health` - Health check

## Build Commands
```bash
# Frontend build
cd frontend
npm install
npm run build

# Development server
npm run dev
```

## Database Setup
```sql
-- Import schema
mysql -u root < database/schema.sql

-- Tables created:
-- users, patients, recordings, appointments, analytics, activity_logs, patient_visits
```

## Default Credentials
- **Username**: admin
- **Password**: admin123
- **Role**: admin

## Important Notes
- API runs on `/api/` path
- Frontend built to `public/dist/`
- Database: ent_clinic
- All routes: PHP backend via .htaccess
- Charts: Chart.js v4.4.0
