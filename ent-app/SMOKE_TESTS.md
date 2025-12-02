# Smoke Test & Verification Guide

## System Status
- **Session-based Auth**: ✅ Implemented via `/api/auth/login` and `/api/auth/logout`
- **Login UI**: ✅ Created standalone login page (`public/pages/login.php`)
- **Entry Point Protection**: ✅ Unauthenticated users redirect to login
- **Demo Role Switcher**: ✅ Removed (replaced by actual login)
- **Performance Fix**: ✅ Session lock release added to prevent hangs
- **Forecasting**: ✅ Holt-Winters additive implemented with backtesting

## Pre-Test Checklist
1. XAMPP is running (Apache + MySQL)
2. Database `ent_clinic` exists with tables
3. Navigate to: `http://localhost/ENT-clinic-online/ent-app/public/`
4. You should see the login page

## Test Cases

### Test 1: Login as Admin
**Expected**: Admin can login, see all pages
```
Username: admin
Password: admin123
```
- ✓ Login form submits
- ✓ Redirected to `/patients` page
- ✓ Session cookie set (visible in browser dev tools → Storage → Cookies)
- ✓ User info shows "Admin User" / "Administrator" in sidebar
- ✓ Analytics link visible in sidebar
- ✓ Can add/edit patients
- ✓ Can add/edit visits

### Test 2: Login as Doctor
**Expected**: Doctor can login, add/edit visits
```
Username: doctor_demo
Password: password
```
- ✓ Login succeeds
- ✓ See patients and analytics
- ✓ Can add/edit visits
- ✓ Cannot manage user accounts (settings unavailable or hidden for non-admin)

### Test 3: Login as Secretary
**Expected**: Secretary can login, read-only for most, no analytics
```
Username: staff_demo
Password: password
```
- ✓ Login succeeds
- ✓ Can view patients
- ✓ **Cannot see Analytics link** (hidden for staff)
- ✓ Can view patient profiles
- ✓ **Cannot add/edit visits** (UI hidden or button disabled)
- ✓ Cannot manage users (settings unavailable)

### Test 4: Timezone & Analytics
**Expected**: Visits show Manila time, analytics correct bucketing
- ✓ Add a visit with datetime (e.g., "2025-12-02 2:00 PM")
- ✓ Verify time displays correctly on patient profile in Manila timezone
- ✓ Go to Analytics page
- ✓ Verify daily counts for today appear correctly (no off-by-one date issues)
- ✓ Forecast shows next 14 days
- ✓ Seasonality percentages are reasonable (0.25–4.0 range, mean ≈ 1.0)

### Test 5: Logout & Session Cleanup
**Expected**: Logout clears session and redirects to login
- ✓ Click user avatar in sidebar
- ✓ Click Logout button
- ✓ Redirected to login page
- ✓ Session cookie cleared (check dev tools)
- ✓ Trying to go back to `/patients` redirects to login

### Test 6: Session Persistence Across Pages
**Expected**: Session remains across navigation
- ✓ Login as admin
- ✓ Go to Patients → Patient Profile → Analytics → Settings → back to Patients
- ✓ User remains logged in throughout
- ✓ No session timeouts or forced logouts

### Test 7: Invalid Login
**Expected**: Error message displayed
```
Username: admin
Password: wrongpassword
```
- ✓ Error message appears: "Invalid username or password"
- ✓ Page remains on login form
- ✓ Clicking login again attempts login

### Test 8: Direct Page Access Without Login
**Expected**: Redirect to login
- ✓ Logout
- ✓ Navigate directly to `http://localhost/ENT-clinic-online/ent-app/public/?page=patients`
- ✓ Redirected to login page

### Test 9: API Endpoints Require Authentication
**Expected**: API calls without session fail with 401/403
```bash
# This should fail (no session):
curl -X GET "http://localhost/ENT-clinic-online/ent-app/public/api/api/patients"

# This should succeed (requires login session):
# (Use browser or REST client that persists cookies)
```

### Test 10: Role-Based API Enforcement
**Expected**: Secretary cannot create visits via API
- Login as Secretary
- Try to add a visit (form should be hidden or error shown)
- Check browser network tab to verify API call returns 403

## Performance Verification

### Before Fix
- Site took ~5–30 seconds to load (PHP session lock deadlock)

### After Fix
- Site should load in <1 second
- Verify in browser: F12 → Network tab → Measure initial page load time

### If Still Slow
1. Check XAMPP Apache error log: `C:\xampp\apache\logs\error.log`
2. Check MySQL is running and responsive:
   ```bash
   mysql -u root -e "SELECT 1" ent_clinic
   ```
3. Restart Apache/MySQL in XAMPP Control Panel

## Database Demo Users

If demo users do not exist, create them (replace `/path/to` with your XAMPP path):

```sql
-- Hash for 'password': $2y$10$... (use PHP password_hash)
-- Hash for 'admin123': $2y$10$... (use PHP password_hash)

INSERT INTO users (username, email, password_hash, full_name, role, is_active) VALUES
('admin', 'admin@entclinic.com', '$2y$10$...HASH_FOR_admin123...', 'Administrator', 'admin', 1),
('doctor_demo', 'doctor@entclinic.local', '$2y$10$...HASH_FOR_password...', 'Doctor Demo', 'doctor', 1),
('staff_demo', 'staff@entclinic.local', '$2y$10$...HASH_FOR_password...', 'Secretary Demo', 'staff', 1);
```

Generate hashes in PHP:
```php
<?php
echo "admin123: " . password_hash('admin123', PASSWORD_DEFAULT) . "\n";
echo "password: " . password_hash('password', PASSWORD_DEFAULT) . "\n";
```

## Known Limitations & Next Steps

### Current State
- ✅ Session-based authentication fully implemented
- ✅ Login page created and functional
- ✅ Role-based access control enforced at API level
- ✅ Performance fix (session lock release)
- ✅ Holt-Winters forecasting implemented

### Optional Enhancements (Future)
- Password reset flow
- Two-factor authentication
- Session timeout (currently set to 1 hour in `config.php`)
- Audit logging for all user actions
- Admin account recovery

## Testing Tools

### Browser DevTools Network Tab
1. F12 → Network tab
2. Filter by "XHR" to see API calls only
3. Look for:
   - `POST /api/auth/login` → 200 (success)
   - API calls with `PHPSESSID` cookie in headers
   - `GET /api/patients` → 200 (with auth) or 401 (without auth)

### REST Client (Postman, Insomnia, Thunder Client)
```bash
POST http://localhost/ENT-clinic-online/ent-app/public/api/api/auth/login
Content-Type: application/json

{
  "username": "admin",
  "password": "admin123"
}
```

Response (success):
```json
{
  "success": true,
  "message": "Logged in",
  "data": {
    "id": 1,
    "username": "admin",
    "email": "admin@entclinic.com",
    "name": "Administrator",
    "role": "admin"
  }
}
```

## Summary of Changes (This Session)

### Files Created
- `public/pages/login.php` — Standalone login form with styled UI

### Files Modified
- `config/config.php` — Added `ALLOW_HEADER_AUTH` flag (default false)
- `api/Controller.php` — Updated `getApiUser()` to prefer session, optional header fallback
- `api/AuthController.php` — Login/logout endpoints
- `public/api.php` — Registered auth routes
- `public/includes/helpers.php` — Fixed session lock deadlock, added timeouts
- `public/index.php` — Added auth check, redirect to login if not authenticated, removed demo role-switcher
- `public/includes/header.php` — Removed DB auto-initialization, removed role-switcher UI

### API Routes Added
- `POST /api/auth/login` → Returns user + sets session
- `POST /api/auth/logout` → Clears session
- `GET /api/auth/me` → Returns current session user

### System Flow
1. User visits site → redirects to login if not authenticated
2. User submits login form → calls `/api/auth/login`
3. API verifies password, sets `$_SESSION['user']`, returns user data
4. Frontend receives session cookie (from cURL forwarding)
5. Frontend redirects to `/patients` page
6. All subsequent API calls forward session cookie automatically
7. Logout clears session server-side and frontend

