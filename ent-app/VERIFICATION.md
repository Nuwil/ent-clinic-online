# Verification Guide - ENT Clinic Online

## Pre-Deployment Verification Checklist

### Database Verification

#### ✅ Schema Imported
```bash
mysql -u root -e "USE ent_clinic; SHOW TABLES;"
```
Expected output:
```
analytics
appointments
patients
patient_visits
recordings
users
activity_logs
```

#### ✅ Table Structure
```bash
mysql -u root -e "USE ent_clinic; DESCRIBE patients;"
```
Should show columns: id, patient_id, first_name, last_name, email, phone, etc.

#### ✅ Default User
```bash
mysql -u root -e "USE ent_clinic; SELECT * FROM users WHERE username='admin';"
```
Should return admin user with hashed password

#### ✅ Indexes
```bash
mysql -u root -e "USE ent_clinic; SHOW INDEXES FROM patients;"
```
Should show indexes on patient_id, email, phone, created_at

### Backend Verification

#### ✅ Configuration
- [ ] `config/config.php` exists
- [ ] Database credentials correct
- [ ] `config/Database.php` has connection code

#### ✅ API Files
- [ ] `public/api.php` exists
- [ ] `api/Router.php` exists
- [ ] All controller files present:
  - [ ] `api/PatientsController.php`
  - [ ] `api/RecordingsController.php`
  - [ ] `api/VisitsController.php`
  - [ ] `api/AnalyticsController.php`
  - [ ] `api/Controller.php`

#### ✅ API Endpoints Working
Test each endpoint:

```bash
# Health check
curl http://localhost/ENT-clinic-online/ent-app/public/api/health
# Should return: {"status":"ok","message":"ENT Clinic API is running",...}

# List patients
curl http://localhost/ENT-clinic-online/ent-app/public/api/patients
# Should return: {"status":"success","data":{...},...}

# Create patient
curl -X POST http://localhost/ENT-clinic-online/ent-app/public/api/patients \
  -H "Content-Type: application/json" \
  -d '{"first_name":"John","last_name":"Doe","email":"john@example.com","phone":"555-1234","gender":"male","date_of_birth":"1990-01-01","medical_history":"None"}'
# Should return: {"status":"success"...}

# Get analytics
curl http://localhost/ENT-clinic-online/ent-app/public/api/analytics
# Should return: {"status":"success","data":{"ent_distribution":{...},"weekly_visits":{...}}}
```

#### ✅ Routing
- [ ] `.htaccess` exists in `public/`
- [ ] Contains RewriteEngine On
- [ ] Contains API routing rules
- [ ] Apache mod_rewrite enabled

### Frontend Verification

#### ✅ Build Output
- [ ] `public/dist/` folder exists
- [ ] Contains `index.html`
- [ ] Contains `assets/` folder
- [ ] Bundle size reasonable (< 500KB)

#### ✅ Frontend Files
- [ ] `frontend/src/App.vue` exists
- [ ] All page components exist:
  - [ ] `frontend/src/pages/DashboardPage.vue`
  - [ ] `frontend/src/pages/PatientsPage.vue`
  - [ ] `frontend/src/pages/RecordingsPage.vue`
  - [ ] `frontend/src/pages/AnalyticsPage.vue`
  - [ ] `frontend/src/pages/SettingsPage.vue`
- [ ] `frontend/src/css/style.css` exists

#### ✅ Dependencies
```bash
cd frontend
npm list
```
Should show:
- [ ] vue@3.3.4
- [ ] axios@1.5.0
- [ ] chart.js@4.4.0
- [ ] vite@4.4.9

#### ✅ Frontend Loading
Visit: http://localhost/ENT-clinic-online/ent-app/public/

Check:
- [ ] Page loads without blank screen
- [ ] Navigation menu visible
- [ ] Logo/title displays
- [ ] No 404 errors in console
- [ ] No API errors in console

### Application Testing

#### ✅ Login
- [ ] Can access login page
- [ ] Default user works: admin/admin123

#### ✅ Navigation
- [ ] Can click all nav items
- [ ] Pages load without errors
- [ ] Buttons are clickable

#### ✅ Patient Management
1. [ ] Can navigate to Patients page
2. [ ] Can click "Add New Patient"
3. [ ] Form displays all fields
4. [ ] Can fill and submit form
5. [ ] Patient appears in list
6. [ ] Can click patient to view details
7. [ ] Can edit patient
8. [ ] Can delete patient

#### ✅ Visit Management
1. [ ] On patient profile page
2. [ ] Can click "Add Visit"
3. [ ] Form shows visit fields
4. [ ] Can save visit
5. [ ] Visit appears in timeline
6. [ ] Can view visit details

#### ✅ Analytics Page
1. [ ] Charts render (pie chart and bar chart)
2. [ ] ENT Distribution chart shows data
3. [ ] Weekly Visits chart shows data
4. [ ] Statistics display correctly
5. [ ] Monthly table shows data

#### ✅ Settings Page
1. [ ] Settings form displays
2. [ ] Can update settings
3. [ ] Data Management section visible
4. [ ] Export/Import options available
5. [ ] Database info shows "Connected"
6. [ ] API info shows "Running"

### Performance Testing

#### ✅ Load Times
- [ ] Homepage loads in < 2 seconds
- [ ] API endpoints respond in < 500ms
- [ ] Charts render in < 1 second

#### ✅ Responsiveness
Test on different screen sizes:
- [ ] Desktop (1920x1080)
- [ ] Tablet (768x1024)
- [ ] Mobile (375x667)

#### ✅ Browser Compatibility
- [ ] Chrome/Edge
- [ ] Firefox
- [ ] Safari

### Error Handling Testing

#### ✅ Invalid Input
- [ ] Submitting blank forms shows error
- [ ] Invalid email shows validation error
- [ ] Special characters handled correctly

#### ✅ API Errors
- [ ] 404 for missing patient
- [ ] Validation error for invalid data
- [ ] Server error handling works

#### ✅ Network Issues
- [ ] Offline mode shows warning
- [ ] Retry logic works
- [ ] Error messages display

### Security Testing

#### ✅ Input Validation
- [ ] SQL injection not possible
- [ ] XSS prevention working
- [ ] CSRF protection (if implemented)

#### ✅ Authentication
- [ ] Default password is only for admin
- [ ] Sessions work correctly
- [ ] Logout clears session

#### ✅ File Permissions
- [ ] Config files not world-readable
- [ ] Database credentials protected
- [ ] API responses don't expose sensitive data

### Data Integrity

#### ✅ CRUD Operations
- [ ] Create patient: data saved correctly
- [ ] Read patient: all fields retrieved
- [ ] Update patient: changes persisted
- [ ] Delete patient: record removed

#### ✅ Relationships
- [ ] Patient visits linked to patient
- [ ] Recordings linked to patient
- [ ] Foreign keys enforced
- [ ] Cascading deletes work

#### ✅ Data Validation
- [ ] Required fields enforced
- [ ] Email format validated
- [ ] Phone format accepted
- [ ] Dates in correct format

### Final Checklist

- [ ] Database fully functional
- [ ] API all endpoints working
- [ ] Frontend loads and responsive
- [ ] CRUD operations complete
- [ ] Charts render correctly
- [ ] No console errors
- [ ] No 404s or API errors
- [ ] Settings page works
- [ ] Data exports properly
- [ ] Performance acceptable
- [ ] Security measures in place
- [ ] Backup/restore tested
- [ ] All docs present and accurate

### Sign-Off

**Ready for Deployment**: ✅
- Date: [Date]
- Tested by: [Name]
- Status: VERIFIED

---

## Post-Deployment Verification

After deploying to production, verify:

1. [ ] Database backed up
2. [ ] SSL certificate configured
3. [ ] All endpoints accessible
4. [ ] Performance acceptable
5. [ ] Error logs monitored
6. [ ] Users can login
7. [ ] Data persists correctly
8. [ ] Backups working

## Regular Monitoring

- Check API health daily: `/api/health`
- Review error logs weekly
- Backup database daily
- Monitor server resources
- Check user activity logs
