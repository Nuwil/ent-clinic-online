# ENT Clinic Appointment Workflow Test Plan

## Overview
This document outlines the complete appointment workflow that replaces manual visit entry:
1. **Book Appointment** - Secretary/Doctor schedules appointment
2. **Accept Appointment** - Doctor confirms they will see the patient
3. **Complete Appointment** - Doctor completes the visit and records visit details (auto-creates visit in timeline)

## Prerequisites
- Database: `ent_clinic` should have appointments, patients, and patient_visits tables
- Users: System should have a doctor account and at least one patient record
- Schema: Appointments table must have `status` field with values: scheduled, accepted, completed, cancelled

## Test Workflow

### Step 1: Book an Appointment
1. Navigate to: `http://localhost/ent-clinic-online/ent-app/public/pages/appointments.php`
2. Make sure you're logged in as doctor or admin
3. In the Calendar/Day View:
   - Select a date using the date picker
   - Click on an available (green) slot
   - A "Book" modal should appear
   - Select a patient from the dropdown
   - Choose appointment type (New Patient, Follow-up, Procedure, Emergency)
   - (Optional) Add notes
   - Click "Book" button
4. **Expected Result**: 
   - Appointment appears in the calendar as a "booked" (blue) slot
   - List View shows the appointment with status "scheduled"
   - Status badge shows: "scheduled"

### Step 2: Accept the Appointment
1. In the List View, find the newly booked appointment
2. The appointment should show status "scheduled"
3. Click the "Accept" button next to the appointment
4. A confirmation dialog should appear asking "Accept this appointment?"
5. Click "OK" to confirm
6. **Expected Result**:
   - Appointment status changes to "accepted" (shown in blue badge)
   - Action buttons change from "Accept, Reschedule, Cancel" to "Complete & Record, Reschedule, Cancel"
   - Success alert: "Appointment accepted!"

### Step 3: Complete the Appointment & Record Visit
1. In the List View, click on the "Complete & Record" button (only appears for accepted appointments)
2. A modal titled "Complete Appointment & Record Visit" should appear with the following fields:
   - **ENT Classification** (dropdown): Ear Issues, Nose Issues, Throat Issues, Head/Neck Issues, Lifestyle Medicine, Other/Misc
   - **Diagnosis** (textarea): Required field for the diagnosis or findings
   - **Treatment/Procedure** (textarea): Required field for treatment or recommendations
   - **Additional Notes** (textarea): Optional field for extra observations
3. Fill in the fields with appropriate information
4. Click "Complete & Record Visit" button
5. **Expected Result**:
   - Success alert: "Appointment completed and visit record created!"
   - Appointment status changes to "completed" (green badge)
   - Complete button disappears, replaced with "✓ Completed" text
   - **Visit automatically created** in patient's timeline with:
     - visit_date: from appointment start_at
     - ent_type: from selected classification
     - diagnosis: from entered diagnosis
     - treatment: from entered treatment
     - notes: from appointment notes and additional notes

### Step 4: Verify Visit in Patient Timeline
1. Navigate to patient profile: `http://localhost/ent-clinic-online/ent-app/public/pages/patient-profile.php?id=<patient_id>`
2. Scroll to "Visit Timeline" section
3. **Expected Result**: 
   - Newly created visit should appear at the top of the timeline with:
     - Visit date
     - ENT classification (Ear/Nose/Throat/etc.)
     - Diagnosis and treatment information
     - Timestamp showing "just now" or current time

## API Endpoints Tested

### Accept Appointment
- **Endpoint**: `POST /api/appointments/:id/accept`
- **Method**: POST
- **Headers**: Content-Type: application/json
- **Response**: `{ "success": true, "data": { "id": <id> }, "message": "Appointment accepted" }`

### Complete Appointment
- **Endpoint**: `POST /api/appointments/:id/complete`
- **Method**: POST
- **Headers**: Content-Type: application/json
- **Body**:
```json
{
  "ent_type": "ear|nose|throat|head_neck_tumor|lifestyle_medicine|misc",
  "diagnosis": "string",
  "treatment": "string",
  "notes": "optional string",
  "prescription_items": []
}
```
- **Response**: `{ "success": true, "data": { "appointment_id": <id>, "visit_id": <visit_id> }, "message": "Appointment completed and visit record created" }`

## Database Verification Queries

After completing the workflow, verify the database:

```sql
-- Check appointment status changed to completed
SELECT id, patient_id, status, start_at FROM appointments ORDER BY id DESC LIMIT 5;

-- Check visit was created with correct ent_type
SELECT id, patient_id, visit_date, ent_type, diagnosis FROM patient_visits ORDER BY id DESC LIMIT 5;

-- Verify prescription items linked (if any)
SELECT pi.*, pv.ent_type FROM prescription_items pi 
JOIN patient_visits pv ON pi.visit_id = pv.id 
ORDER BY pi.id DESC LIMIT 5;
```

## UI Elements Checklist

- [ ] Complete Appointment modal displays with all required fields
- [ ] ENT Classification dropdown shows all 6 options
- [ ] Diagnosis field is required and shows validation
- [ ] Treatment field is required and shows validation
- [ ] Additional Notes field is optional
- [ ] Submit button says "Complete & Record Visit"
- [ ] List view shows appointment status (scheduled/accepted/completed/cancelled)
- [ ] Action buttons change based on appointment status
- [ ] Calendar/Day view shows appointment status badge
- [ ] Completed appointments show "✓ Completed" with green color
- [ ] Cancelled appointments show "✗ Cancelled" with red color

## Troubleshooting

### Appointment not appearing after booking
- Check browser console for JavaScript errors
- Verify API response in Network tab shows `{ "success": true }`
- Check that appointments table exists in database

### Complete button not appearing
- Verify appointment status is "accepted"
- Check that user is doctor or admin role
- Clear browser cache and reload page

### Visit not appearing in patient timeline
- Check patient_visits table has entry with correct patient_id
- Verify visit_date is correct
- Check patient profile page loads without errors

### API errors
- Check api.php routes are registered correctly
- Verify AppointmentsController.php has accept() and complete() methods
- Check database connection in Database.php

## Notes
- Appointments can be rescheduled even after being accepted (before completion)
- Appointments can be cancelled at any stage except completed
- Completed appointments cannot be edited
- Each completed appointment creates exactly one visit record
- Visit inherits appointment time as visit_date
