# ENT Clinic Appointment Workflow - Implementation Summary

## Overview
The appointment system has been fully implemented to replace manual visit entry. The workflow now follows this sequence:
1. **Book Appointment** - Secretary/Doctor schedules appointment in calendar
2. **Accept Appointment** - Doctor confirms they will see the patient
3. **Complete Appointment** - Doctor completes the visit and auto-creates a visit record in the patient timeline

## Architecture

### Database Tables
- **appointments**: Stores appointment records with status (scheduled → accepted → completed/cancelled)
- **patient_visits**: Auto-populated from completed appointments
- **prescription_items**: Linked to visits for tracking medications

### API Endpoints

#### Accept Appointment
```
POST /api/appointments/:id/accept
```
- Updates appointment status from `scheduled` to `accepted`
- Called when doctor confirms they will see the patient
- Returns: `{ "success": true, "data": { "id": <id> } }`

#### Complete Appointment
```
POST /api/appointments/:id/complete
```
- Accepts JSON input with:
  - `ent_type`: Ear/Nose/Throat classification
  - `diagnosis`: Medical diagnosis or findings
  - `treatment`: Treatment or recommendations
  - `notes`: Additional observations (optional)
  - `prescription_items`: Array of prescription details (optional)
- Updates appointment status to `completed`
- **Auto-creates visit record** in patient_visits table
- **Auto-creates prescription items** if provided
- Returns: `{ "success": true, "data": { "appointment_id": <id>, "visit_id": <visit_id> } }`

### Frontend UI

#### Appointments Page (`public/pages/appointments.php`)
Three main tabs:
1. **Calendar/Day View**
   - Shows hourly appointment slots grouped by time
   - Available slots (green) can be clicked to book
   - Booked slots show status and action buttons based on appointment state
   - Status indicators:
     - `scheduled` (yellow): Shows Accept, Reschedule, Cancel buttons
     - `accepted` (blue): Shows Complete & Record, Reschedule, Cancel buttons
     - `completed` (green): Shows "✓ Completed" badge
     - `cancelled` (red): Shows "✗ Cancelled" badge

2. **List View**
   - Displays all appointments for the selected date in a list format
   - Shows appointment status as colored badge
   - Action buttons dynamically change based on status
   - Same status-driven button logic as calendar view

3. **Waitlist Tab**
   - Manages patients on the appointment waitlist
   - Allows adding/removing patients
   - Notification system for when slots open up

#### Complete Appointment Modal
When doctor clicks "Complete & Record" button:
- Modal displays with form fields:
  - **ENT Classification** (required dropdown):
    - Ear Issues
    - Nose Issues
    - Throat Issues
    - Head/Neck Issues
    - Lifestyle Medicine
    - Other/Misc
  - **Diagnosis** (required textarea): Clinical findings
  - **Treatment/Procedure** (required textarea): Treatment details
  - **Additional Notes** (optional textarea): Extra observations
- Submit button: "Complete & Record Visit"
- On submit:
  - Appointment marked as `completed`
  - Visit record auto-created with entered details
  - Success message shown
  - View refreshes to show updated status

#### Patient Profile Page
- "Add Visit" button removed
- Info banner added directing users to Appointments page
- Old manual visit entry form no longer used in primary workflow
- Visit timeline still displays completed appointments as visit records

### Authorization
- **Admin**: Full access to all appointments
- **Doctor**: Full access to all appointments (accept, complete, reschedule)
- **Staff**: Read-only access to appointments list
- **Patient**: Cannot access appointments page

## Data Flow

### Complete Appointment Workflow
```
1. Secretary/Doctor clicks on available slot
   ↓
2. "Book" modal opens - select patient, type, notes
   ↓
3. Appointment created in DB with status='scheduled'
   ↓
4. Doctor clicks "Accept" button
   ↓
5. Appointment status updated to 'accepted'
   ↓
6. Doctor clicks "Complete & Record" button
   ↓
7. Modal opens with form for medical details
   ↓
8. Doctor fills in:
   - ENT classification (ent_type)
   - Diagnosis
   - Treatment details
   - Optional additional notes
   ↓
9. Submit sends POST request to /api/appointments/:id/complete
   ↓
10. Backend:
    - Updates appointment status to 'completed'
    - Creates new row in patient_visits table with:
      - visit_date: from appointment start_at
      - ent_type: from selected classification
      - diagnosis: entered diagnosis
      - treatment_plan: entered treatment
      - notes: from appointment notes + additional notes
    - If prescription items provided, creates prescription records
    - Returns success with appointment_id and visit_id
    ↓
11. Frontend shows success message
    ↓
12. View refreshes showing completed appointment
    ↓
13. Visit now appears in patient's profile timeline
```

## Database Changes

### Appointments Table Schema
```sql
CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  type VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'scheduled',  -- NEW: tracks workflow state
  start_at DATETIME NOT NULL,
  end_at DATETIME NOT NULL,
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (patient_id),
  INDEX (start_at),
  INDEX (status)  -- NEW: for efficient status queries
);
```

### Patient Visits Table
- Existing table reused for storing completed appointment data
- New records created automatically from completed appointments
- Fields populated:
  - `patient_id`: from appointment
  - `visit_date`: from appointment start_at
  - `ent_type`: from appointment completion form
  - `diagnosis`: from appointment completion form
  - `treatment_plan`: from appointment completion form
  - `notes`: merged from appointment notes and form

## Files Modified

1. **api/AppointmentsController.php**
   - Added `accept($id)` method - updates status to 'accepted'
   - Added `complete($id)` method - updates status and auto-creates visit
   - Added route handlers in public/api.php

2. **public/pages/appointments.php**
   - Added Complete Appointment modal with form fields
   - Updated renderListView() to show status badges and status-driven buttons
   - Updated renderDayView() to show status badges and status-driven buttons
   - Added openCompleteModal(), closeCompleteModal(), acceptAppointment() functions
   - Added form submission handler for complete form

3. **public/pages/patient-profile.php**
   - Replaced "Add Visit" button with info banner
   - Directs users to use Appointments page instead
   - Visit timeline still displays records from completed appointments

4. **public/includes/header.php**
   - Added "Appointments" navigation link for doctor/admin roles

5. **public/includes/helpers.php**
   - Added 'appointments' to allowedPages for doctor/admin access control

6. **public/api.php**
   - Registered POST routes for /api/appointments/:id/accept and /api/appointments/:id/complete

## Testing Checklist

- [ ] Can book appointment in calendar view
- [ ] Booked appointment shows status "scheduled"
- [ ] Doctor can click "Accept" button
- [ ] Appointment status changes to "accepted" after accept
- [ ] Doctor can click "Complete & Record" button
- [ ] Complete modal appears with all required form fields
- [ ] Submitting complete form creates visit record
- [ ] Visit appears in patient profile timeline with correct data
- [ ] Appointment shows status "completed" after completion
- [ ] Can reschedule appointment in both scheduled and accepted states
- [ ] Can cancel appointment in scheduled, accepted states
- [ ] List view shows status badges with correct colors
- [ ] Calendar view shows status badges and context-appropriate buttons
- [ ] Old "Add Visit" button no longer exists on patient profile
- [ ] Info banner correctly links to appointments page

## Key Features

1. **Auto-Visit Creation**: Completed appointments automatically create visit records
2. **Status Tracking**: Appointments move through states: scheduled → accepted → completed
3. **Two-Step Completion**: Doctor must accept before completing (prevents accidental completion)
4. **Visit Data Preservation**: All appointment and form data captured in visit record
5. **Audit Trail**: Timestamps recorded for all state changes
6. **User-Friendly UI**: Status badges and color-coded buttons guide users
7. **Authorization**: Proper role-based access control maintained
8. **Prescription Support**: Optional prescription items can be added during completion

## Future Enhancements

1. Email notifications when appointments are accepted/completed
2. SMS reminders to patients before appointments
3. Bulk appointment operations (reschedule multiple, cancel multiple)
4. Appointment history/archive
5. No-show tracking
6. Performance analytics (average consultation time, cancellation rates)
7. Integration with calendar applications (iCal, Google Calendar)
8. Appointment notes templates for common diagnoses
