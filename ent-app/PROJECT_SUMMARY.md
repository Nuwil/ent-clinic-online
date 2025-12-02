# Project Summary - ENT Clinic Online

## Overview
ENT Clinic Online is a comprehensive web-based application for managing ENT (Ear, Nose, Throat) patient records, visits, and recordings. Built with PHP backend running on XAMPP with MySQL/MariaDB.

## Tech Stack
- **Frontend**: PHP Server-Side Rendering
- **Backend**: PHP 7.4+, PDO
- **Database**: MySQL 5.7+ / MariaDB
- **Server**: Apache 2.4+ with mod_rewrite

## Key Features
1. **Patient Management** - Complete CRUD operations for patient records
2. **Visit Tracking** - Record and manage patient visits with detailed information
3. **Recording Management** - Track audio/video/endoscopy/imaging recordings
4. **Analytics Dashboard** - Statistics for ENT cases and visit data
5. **Settings & Configuration** - Application settings and data management
6. **Data Management** - Export/import patient and visit data
7. **Responsive Design** - Works on desktop and mobile devices

## Architecture

### Frontend (PHP Server-Side Rendering)
- PHP pages served directly from server
- 4 main pages: Patients, Recordings, Analytics, Settings
- Form-based data submission
- Built-in analytics visualization

### Backend (PHP REST API)
- RESTful API with proper HTTP methods
- Router class for URL handling
- Controller classes for business logic
- PDO for database abstraction
- Validation and error handling
- CORS support

### Database (MySQL/MariaDB)
- 7 tables: users, patients, recordings, appointments, analytics, activity_logs, patient_visits
- Proper relationships and constraints
- Indexes for performance
- Default admin user

## File Structure
```
ent-app/
├── api/                 # REST API endpoints
├── config/              # Configuration files
├── database/            # Database schema and migrations
├── public/              # Web root directory
│   ├── pages/          # PHP pages (patients, recordings, analytics, settings)
│   ├── assets/         # CSS, JS, images
│   └── includes/       # PHP helpers and utilities
└── [Config & docs]
```

## Development Workflow

### Setup
1. Database: Import `database/schema.sql`
2. Access: http://localhost/ENT-clinic-online/ent-app/public/

### Accessing Application
- Patients: http://localhost/ENT-clinic-online/ent-app/public/?page=patients
- Recordings: http://localhost/ENT-clinic-online/ent-app/public/?page=recordings
- Analytics: http://localhost/ENT-clinic-online/ent-app/public/?page=analytics
- Settings: http://localhost/ENT-clinic-online/ent-app/public/?page=settings

## API Overview

### Authentication
- Session-based (current implementation)
- Default admin: admin/admin123

### Endpoints
- **Patients**: GET/POST/PUT/DELETE /api/patients[/:id]
- **Visits**: GET/POST/PUT/DELETE /api/visits[/:id]
- **Recordings**: GET/POST/PUT/DELETE /api/recordings[/:id]
- **Analytics**: GET /api/analytics
- **Health**: GET /api/health

### Response Format
```json
{
  "status": "success|error",
  "data": { /* response data */ },
  "message": "Response message",
  "code": 200
}
```

## Database Schema

### Tables (7 total)
1. **users** - System users (admin, doctor, staff)
2. **patients** - Patient information (40+ fields)
3. **recordings** - Audio/video/endoscopy recordings
4. **appointments** - Patient appointments
5. **analytics** - Metrics and statistics
6. **activity_logs** - Audit trail
7. **patient_visits** - Detailed visit records

## Performance
- Optimized database queries with indexes
- GZIP compression enabled
- Browser caching configured
- Direct PHP rendering (no build step required)

## Security
- PDO prepared statements (SQL injection protection)
- Input validation on all forms
- CORS headers configured
- Sensitive files protected (.env)
- Session-based authentication

## Browser Support
- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers

## Future Enhancements
- User authentication UI
- File upload for recordings
- Email notifications
- Advanced reporting
- PDF export
- Mobile app
- Real-time notifications
