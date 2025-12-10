# Appointment to Visit Conversion Fix

## Problem
When a patient's appointment was accepted and converted to a visit, the original appointment entry remained visible in the 'Appointments' section above the Visit Timeline, causing duplication.

## Solution
Implemented a complete appointment-to-visit tracking system with proper status management.

## Changes Made

### 1. Database Schema Update
**File**: `database/schema.sql`
- Added `appointment_id` column to `patient_visits` table
- Added foreign key constraint linking appointments to visits
- Added index on `appointment_id` for efficient lookups
- This allows tracking which appointment was converted to a visit

**Migration**: `database/migrations/add_appointment_id_to_visits.sql`
- Provided a migration script for existing databases to add the column

### 2. API Updates

#### VisitsController.php
- Added `appointment_id` to allowed fields for both staff and admin/doctor roles
- The field can now be passed when creating a visit and will be stored in the database
- Enables the API to track the relationship between visits and appointments

### 3. Backend Logic Updates

#### index.php (Public Form Handler)
- Extract `appointment_id` from POST data: `$appointmentId = isset($_POST['appointment_id']) && $_POST['appointment_id'] ? $_POST['appointment_id'] : null;`
- Include `appointment_id` in the data payload when creating a visit
- **Key feature**: After a visit is successfully created and `appointment_id` is provided, automatically mark the appointment as 'Completed' by calling: `apiCall('POST', '/api/appointments/' . $appointmentId . '/complete', []);`
- This applies to both staff and admin/doctor visit creation flows

### 4. Frontend Updates

#### patient-profile.php

**Appointment Display Logic** (`loadAppointments()` function):
- Filter appointments to hide 'Completed', 'Cancelled', and 'No-Show' status (existing logic preserved)
- Updated action button display logic:
  - **Pending appointments**: Show "Accept", "Reschedule", and "Cancel" buttons
  - **Accepted appointments**: Show a disabled "Pending" button indicating the appointment is waiting for visit creation
  - This prevents users from trying to accept an already-accepted appointment
  
**Visit Modal Integration** (`acceptAppointmentAction()` function):
- Already correctly populates the `appointment_id` hidden field with the appointment ID
- When the visit form is submitted, the appointment_id is passed to the backend
- The backend then marks the appointment as 'Completed'

## Workflow After Fix

1. **User clicks "Accept" on a Pending appointment**
   - Appointment status changes from 'Pending' to 'Accepted'
   - Visit modal opens with pre-filled appointment data including appointment_id

2. **User fills out visit details and submits**
   - Visit is created in the database with the appointment_id field populated
   - Backend automatically calls the `/complete` endpoint for the appointment
   - Appointment status changes from 'Accepted' to 'Completed'

3. **Appointments list is reloaded**
   - The completed appointment is now filtered out and no longer displays
   - Visit appears only in the Visit Timeline section below
   - No more duplication

## Benefits

✅ **Eliminates Duplication**: Accepted/completed appointments no longer appear in the pending list
✅ **Tracks Relationships**: Database maintains the link between appointments and visits
✅ **Clear Status Management**: Appointments progress through: Pending → Accepted → Completed (or Cancelled)
✅ **UI Clarity**: Action buttons reflect the actual state of each appointment
✅ **Data Integrity**: Foreign key constraint ensures referential integrity

## Files Modified
1. `database/schema.sql` - Added appointment_id column to patient_visits
2. `database/migrations/add_appointment_id_to_visits.sql` - Migration script for existing databases
3. `api/VisitsController.php` - Added appointment_id to allowed fields
4. `public/index.php` - Added appointment status completion logic
5. `public/pages/patient-profile.php` - Updated loadAppointments() to handle Accepted status with proper UI

## Testing Checklist
- [ ] New patient_visits table includes appointment_id column (or run migration on existing DB)
- [ ] Accept an appointment → Visit modal opens with appointment_id populated
- [ ] Create a visit → Appointment disappears from pending list and shows in Visit Timeline only
- [ ] Check database: appointment.status = 'Completed' and patient_visits.appointment_id = appointment.id
- [ ] Accepted appointments show "Pending" button (disabled) instead of action buttons
- [ ] Visit Timeline shows appointment_id relationship in database records
