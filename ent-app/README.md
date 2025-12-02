# ENT Clinic Online - Complete Project

A full-stack web application for managing ENT (Ear, Nose, and Throat) clinic operations, patient records, and medical recordings.

## ✨ Features

- **Patient Management**: Add, edit, view, and delete patient records
- **Recording Management**: Store and manage audio, video, endoscopy, and imaging recordings
- **Analytics Dashboard**: View statistics and trends
- **Responsive UI**: Built with Vue 3 for smooth user experience
- **RESTful API**: PHP-based API for all backend operations
- **Database**: MySQL/MariaDB for data persistence

## 🏗️ Technology Stack

| Component | Technology |
|-----------|-----------|
| **Frontend** | Vue 3.3.4 + Vite 4.4.9 |
| **Backend** | PHP 7.4+ with PDO |
| **Database** | MySQL/MariaDB 5.7+ |
| **Server** | Apache (XAMPP) |
| **HTTP Client** | Axios |

## 📁 Project Structure

```
ent-app/
├── api/                 # PHP API endpoints
├── config/              # Configuration and database
├── database/            # Database schema and migrations
├── frontend/            # Vue.js frontend application
├── public/              # Web root (Apache serves from here)
├── .env.example         # Environment variables template
├── SETUP_GUIDE.md       # Detailed setup instructions
├── QUICKSTART.md        # Quick start guide
├── API_DOCS.md          # API documentation
└── DEPLOYMENT_CHECKLIST.md # Deployment checklist
```

## 🚀 Quick Start

### Prerequisites

- XAMPP (Apache + MySQL + PHP)
- Node.js and npm
- mod_rewrite enabled in Apache

### Setup (5 minutes)

1. **Import Database**
   ```bash
   mysql -u root -p < database/schema.sql
   ```

2. **Install Frontend Dependencies**
   ```bash
   cd frontend
   npm install
   ```

3. **Build Frontend**
   ```bash
   npm run build
   ```

4. **Access Application**
   - Start Apache and MySQL in XAMPP
   - Open: http://localhost/ENT-clinic-online/ent-app/public/

**Default Login:**
- Username: `admin`
- Password: `admin123`

For detailed setup, see [SETUP_GUIDE.md](SETUP_GUIDE.md)

## 📚 Documentation

- **[QUICKSTART.md](QUICKSTART.md)** - Get started in 5 minutes
- **[SETUP_GUIDE.md](SETUP_GUIDE.md)** - Complete installation and configuration
- **[API_DOCS.md](API_DOCS.md)** - API endpoint reference and examples
- **[DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)** - Production deployment guide

## 🔌 API Endpoints

### Patients
```
GET    /api/patients              # List all patients
GET    /api/patients/:id          # Get patient details
POST   /api/patients              # Create patient
PUT    /api/patients/:id          # Update patient
DELETE /api/patients/:id          # Delete patient
```

### Recordings
```
GET    /api/recordings            # List all recordings
GET    /api/recordings/:id        # Get recording details
POST   /api/recordings            # Create recording
PUT    /api/recordings/:id        # Update recording
DELETE /api/recordings/:id        # Delete recording
```

### Health Check
```
GET    /                          # API status
```

Full API documentation: [API_DOCS.md](API_DOCS.md)

## 💻 Development

### Run Development Server

```bash
# Terminal 1: Frontend with hot reload
cd frontend
npm run dev

# Terminal 2: Keep Apache running (XAMPP)
# http://localhost:5173 (dev frontend)
# http://localhost/ENT-clinic-online/ent-app/public/ (API)
```

### Build for Production

```bash
cd frontend
npm run build
```

Output: `public/dist/` - Vite-optimized static files

## 🗄️ Database

### Schema

The database includes tables for:
- **users** - Admin and staff accounts
- **patients** - Patient information and medical history
- **recordings** - Audio, video, endoscopy, and imaging recordings
- **appointments** - Scheduled appointments
- **analytics** - Statistics and metrics
- **activity_logs** - User actions and audit trail

### Backup & Restore

```bash
# Backup
mysqldump -u root -p ent_clinic > backup.sql

# Restore
mysql -u root -p ent_clinic < backup.sql
```

## 🔒 Security

- ✅ Prepared statements (SQL injection prevention)
- ✅ CORS configuration
- ✅ Environment variables for sensitive data
- ✅ PDO error handling
- ✅ Input validation
- ✅ Apache security headers

For production hardening, see [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md#security-hardening)

## 🚀 Deployment

Ready to deploy to production? Follow the [DEPLOYMENT_CHECKLIST.md](DEPLOYMENT_CHECKLIST.md)

Supports deployment to:
- Shared hosting (cPanel)
- VPS/Cloud servers (AWS, DigitalOcean, Linode)
- Docker containers
- Custom domains with HTTPS

## 📊 Features & Pages

### Dashboard
- Statistics overview
- Recent patients
- Recent recordings
- Quick access to main functions

### Patients Management
- List all patients with search
- Add new patient
- Edit patient information
- Delete patient records
- View patient recordings and appointments

### Recordings Management
- List all recordings
- Filter by patient, type, or status
- Add new recording with diagnosis
- Update recording information
- Delete recordings

### Analytics
- Recording type distribution
- Recording status breakdown
- Monthly activity report
- Export reports (PDF/CSV - expandable)

### Settings
- Application configuration
- Database status
- API status
- Backup operations
- System information

## 🤝 Contributing

To extend the application:

1. **Add API Endpoint**
   - Create controller in `api/`
   - Register routes in `public/index.php`

2. **Add Vue Page**
   - Create `.vue` file in `frontend/src/pages/`
   - Add to navigation in `App.vue`

3. **Modify Database**
   - Update `database/schema.sql`
   - Create migration file

## 📝 Notes

- Default admin password should be changed immediately
- Regular database backups are recommended
- Enable HTTPS in production
- Review security settings before public deployment

## 📞 Support

For issues or questions:
1. Check the relevant documentation file
2. Review error logs in Apache
3. Check browser console for frontend errors
4. Verify database connection

## 📄 License

ENT Clinic Online © 2025

---

**Version:** 1.0.0  
**Last Updated:** December 1, 2025  
**Status:** ✅ Production Ready
