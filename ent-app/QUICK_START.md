# Quick Reference Card

## Getting Started (First Time)

```powershell
# 1. Start XAMPP services
#    Open XAMPP Control Panel → Start Apache & MySQL

# 2. Create demo users
#    Visit: http://localhost/ENT-clinic-online/ent-app/public/setup-demo-users.php
#    You should see: ✅ Demo users setup complete!

# 3. Access the app
#    Visit: http://localhost/ENT-clinic-online/ent-app/public/
#    You should see: Login page
```

---

## Test Accounts

| Role | Username | Password | Access |
|------|----------|----------|--------|
| Admin | `admin` | `admin123` | Everything (patients, visits, analytics, settings) |
| Doctor | `doctor_demo` | `password` | Patients, visits, analytics |
| Secretary | `staff_demo` | `password` | Patients only (read-only) |

---

## Quick Tests

### Test 1: Can I login?
```
Visit: http://localhost/ENT-clinic-online/ent-app/public/
Username: admin
Password: admin123
Expected: Redirected to /patients page
```

### Test 2: Can I add a patient?
```
On Patients page → Click "Add New Patient"
Fill form → Submit
Expected: Patient appears in list
```

### Test 3: Can I add a visit?
```
Click on patient → "Add Visit" button
Select datetime, visit type, ENT classification
Submit → Saves visit
Expected: Visit appears in table with Manila time
```

### Test 4: Can I view forecasts?
```
Click Analytics link (top sidebar)
Expected: 
  - ENT distribution chart
  - Weekly visits (last 7 days)
  - 14-day forecast (Holt-Winters method)
  - Seasonality factors (should be ~0.5–1.5 each day)
```

### Test 5: Can secretary see visits?
```
Logout → Login as staff_demo / password
Click patient → Visit Timeline
Expected: Message "Visit timeline is not available for Secretary accounts"
```

---

## File Locations

```
ent-app/
├── public/
│   ├── index.php ...................... Main entry (auth check here)
│   ├── pages/
│   │   ├── login.php .................. 👈 NEW: Login form
│   │   ├── patients.php
│   │   ├── patient-profile.php
│   │   ├── analytics.php
│   │   └── settings.php
│   ├── api.php ........................ Router (now has /auth routes)
│   ├── setup-demo-users.php ........... 👈 NEW: Creates test accounts
│   └── includes/
│       ├── helpers.php ............... 👈 UPDATED: Session + timeout fixes
│       ├── header.php ................ 👈 UPDATED: Removed role-switcher
│       └── footer.php
│
├── api/
│   ├── Controller.php ................ 👈 UPDATED: Prefer session auth
│   ├── AuthController.php ............ 👈 NEW: Login/logout
│   ├── PatientsController.php ........ (has role checks)
│   ├── VisitsController.php .......... (has role checks)
│   ├── AnalyticsController.php ....... (Holt-Winters forecasting)
│   └── Router.php
│
├── config/
│   ├── config.php .................... 👈 UPDATED: Added ALLOW_HEADER_AUTH flag
│   └── Database.php .................. (PDO wrapper)
│
└── database/
    └── schema.sql .................... (users table has role ENUM)
```

---

## API Endpoints (For REST Client Testing)

### Login
```bash
POST http://localhost/ENT-clinic-online/ent-app/public/api/api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}

# Response:
# {"success":true,"message":"Logged in","data":{...user...}}
```

### Check Session User
```bash
GET http://localhost/ENT-clinic-online/ent-app/public/api/api/auth/me

# Response (with valid session cookie):
# {"success":true,"data":{...user...}}

# Response (no session):
# {"error":"Not authenticated"} [401]
```

### Logout
```bash
POST http://localhost/ENT-clinic-online/ent-app/public/api/api/logout
```

### Get Patients
```bash
GET http://localhost/ENT-clinic-online/ent-app/public/api/api/patients

# Response:
# {"success":true,"message":"Success","data":{"patients":[...],"pages":1}}
```

### Get Analytics
```bash
GET http://localhost/ENT-clinic-online/ent-app/public/api/api/analytics?trend_days=90&horizon=14

# Response includes:
# {
#   "ent_distribution": {"ear":5,"nose":3,"throat":2},
#   "weekly_visits": {"2025-12-02":1,...},
#   "forecast_rows": [{"date":"2025-12-03","value":2.5,"method":"holt_winters"},...],
#   "forecast_stats": {"method":"holt_winters","mae":1.2,"rmse":1.5,...}
# }
```

---

## MySQL Useful Commands

```bash
# Connect to database
mysql -u root ent_clinic

# List users
SELECT username, email, role, is_active FROM users;

# Check patients
SELECT id, first_name, last_name, phone FROM patients LIMIT 5;

# Check visits
SELECT id, patient_id, visit_date, ent_type FROM patient_visits ORDER BY visit_date DESC LIMIT 5;

# Check visit times (stored as UTC, display shows Manila-local):
SELECT DATE_FORMAT(visit_date, '%Y-%m-%d %H:%i:%s') as stored_time FROM patient_visits LIMIT 3;

# Create admin account manually
INSERT INTO users (username, email, password_hash, full_name, role, is_active) 
VALUES ('admin', 'admin@entclinic.com', '$2y$10$...', 'Administrator', 'admin', 1);
```

---

## Common Issues

| Issue | Solution |
|-------|----------|
| "Site takes forever to load" | Restart Apache/MySQL in XAMPP Control Panel |
| "Login page stuck" | Check browser console (F12) for JS errors |
| "Can't add patient" | Ensure you're logged in as admin or doctor |
| "Can't see Analytics" | You're logged in as Secretary (hidden for that role) |
| "Wrong time shown" | Times stored as UTC, displayed as Asia/Manila — this is correct |
| "Forecast looks flat" | Only shows forecast if you have ≥14 days of data |

---

## Performance Check

### Measure page load time
```bash
# In PowerShell:
Measure-Command { Invoke-WebRequest "http://localhost/ENT-clinic-online/ent-app/public/" -UseBasicParsing } | Select TotalSeconds

# Should be <1 second
```

### Check API response time
```bash
# In PowerShell:
Measure-Command { Invoke-RestMethod "http://localhost/ENT-clinic-online/ent-app/public/api/api/health" } | Select TotalSeconds

# Should be <100ms
```

---

## Key Changes Made (This Session)

✅ **Session-based auth** (replaced headers)  
✅ **Login page** (new UI)  
✅ **Entry point protection** (auto-redirect to login)  
✅ **Performance fix** (session lock deadlock resolved)  
✅ **Role-based enforcement** (API level)  
✅ **Holt-Winters forecasting** (with backtesting)  
✅ **Demo users** (seeder script)  
✅ **Documentation** (smoke tests + setup guide)  

---

## Need Help?

1. **Read**: `SMOKE_TESTS.md` (detailed test cases)
2. **Read**: `SESSION_IMPLEMENTATION.md` (architecture + configs)
3. **Check**: `api/Controller.php` lines 40–85 (auth logic)
4. **Check**: `public/pages/login.php` (login form implementation)

---

**Last Updated**: December 2, 2025  
**Version**: 1.0 (Production Ready)  
**Status**: ✅ All features implemented and tested
