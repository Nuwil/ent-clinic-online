# File Manifest

## Project Structure
```
ent-app/
├── api/                          # REST API controllers
│   ├── AnalyticsController.php    # Analytics data endpoint
│   ├── Controller.php             # Base controller class
│   ├── PatientsController.php     # Patient CRUD operations
│   ├── RecordingsController.php   # Recording management
│   ├── Router.php                 # URL routing
│   └── VisitsController.php       # Visit management
├── config/                        # Configuration files
│   ├── config.php                 # App configuration
│   └── Database.php               # Database connection
├── database/                      # Database schema and migrations
│   ├── schema.sql                 # Main database schema
│   ├── add_occupation_field.sql   # Migration: add occupation
│   ├── add_visit_ent_type.sql     # Migration: add ent_type
│   └── patient_visits_table.sql   # Migration: create visits table
├── public/                        # Public directory (Apache root)
│   ├── .htaccess                  # Apache rewrite rules
│   ├── api.php                    # API entry point
│   ├── data-tools.php             # Data import/export tools
│   ├── index.php                  # Main PHP entry point
│   ├── dist/                      # Built frontend (generated)
│   ├── includes/
│   │   ├── header.php             # Page header
│   │   ├── footer.php             # Page footer
│   │   └── helpers.php            # Helper functions
│   └── pages/
│       ├── analytics.php          # Analytics page
│       ├── patient-profile.php    # Patient detail page
│       ├── patients.php           # Patients list page
│       └── settings.php           # Settings page
├── .env.example                   # Example environment file
├── .env.production                # Production config
├── .gitignore                     # Git ignore rules
├── README.md                      # Project documentation
└── Other docs (QUICKSTART, SETUP, etc.)
```

## File Purposes

### API Layer (api/)
- **Controller.php** - Base class with common database/validation methods
- **Router.php** - Simple URL router for API requests
- **PatientsController.php** - CRUD for patients (5 endpoints)
- **RecordingsController.php** - CRUD for recordings (5 endpoints)
- **VisitsController.php** - CRUD for patient visits (5 endpoints)
- **AnalyticsController.php** - Analytics aggregation (ENT distribution, weekly visits)

### Frontend (public/pages/)
- **patients.php** - Patient list and management page
- **patient-profile.php** - Patient detail view with visit history
- **recordings.php** - Recording management page
- **analytics.php** - Analytics and statistics page
- **settings.php** - Application settings page

### Database (database/)
- **schema.sql** - Complete database schema (6 tables)
- Migration files track schema changes

### Configuration
- **.env.example** - Template for environment variables
- **.env.production** - Production configuration
- **config/config.php** - Application settings loader
- **config/Database.php** - PDO database singleton

### Public Directory
- **index.php** - PHP frontend entry point
- **api.php** - API entry point
- **pages/\*.php** - Traditional PHP pages
- **includes/\*.php** - PHP includes and helpers
- **.htaccess** - Apache URL rewriting

## Build Artifacts
- **public/dist/** - Generated minified Vue app
- Automatically created by `npm run build`
