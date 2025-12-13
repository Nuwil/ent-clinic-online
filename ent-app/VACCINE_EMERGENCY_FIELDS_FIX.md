# Patient Profile Fields Fix - Summary

## Issue Identified
When editing a patient profile and entering values for the following fields, they were not being saved:
- Vaccine History
- Emergency Contact Name
- Emergency Contact Phone

## Root Cause
The `PatientsController.php` API endpoint had an `$allowedFields` array (line 197-202) that did NOT include these three fields. When the API received the update request, it filtered out any fields not in the `$allowedFields` array using `array_intersect_key()` on line 223.

This meant these fields were being silently discarded before the database update.

## Solution Applied
Updated the `$allowedFields` array in [api/PatientsController.php](api/PatientsController.php#L197-L202) to include:
- `vaccine_history`
- `emergency_contact_name`
- `emergency_contact_phone`

### Changed Code
**File:** `ent-app/api/PatientsController.php` (lines 197-202)

**Before:**
```php
$allowedFields = [
    'first_name', 'last_name', 'gender', 'date_of_birth', 'email',
    'phone', 'occupation', 'address', 'city', 'state', 'postal_code', 'country',
    'medical_history', 'current_medications', 'allergies',
    'insurance_provider', 'insurance_id',
    'height', 'weight', 'blood_pressure', 'temperature', 'bmi'
];
```

**After:**
```php
$allowedFields = [
    'first_name', 'last_name', 'gender', 'date_of_birth', 'email',
    'phone', 'occupation', 'address', 'city', 'state', 'postal_code', 'country',
    'medical_history', 'current_medications', 'allergies',
    'insurance_provider', 'insurance_id',
    'height', 'weight', 'blood_pressure', 'temperature', 'bmi',
    'vaccine_history', 'emergency_contact_name', 'emergency_contact_phone'
];
```

## Verification
✓ Database columns confirmed to exist:
  - `vaccine_history (text)`
  - `emergency_contact_name (varchar(150))`
  - `emergency_contact_phone (varchar(20))`

✓ Form fields properly configured in patient-profile.php with correct field names

✓ Backend handler in index.php correctly collects these fields from POST data

## How to Test
1. Go to any patient's profile page
2. Click "Edit Patient Profile" button
3. Fill in the following fields:
   - **Vaccine History** (e.g., "COVID-19 Booster, Flu 2025")
   - **Emergency Contact Name** (e.g., "John Smith")
   - **Emergency Contact Phone** (e.g., "+1-555-123-4567")
4. Click "Save Changes"
5. The page will reload with a success message
6. Reopen "Edit Patient Profile" to verify the values are now saved

## Related Fixes
- **Dropdown Reset Issue:** Fixed in `js/location-loader.js` - State/Province and City dropdowns now properly retain selected values when other fields are updated (separate fix)
