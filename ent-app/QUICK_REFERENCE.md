# ENT Clinic Appointment Workflow - Quick Reference Guide

## What Changed?

✅ **Old Workflow (Removed)**
- Manual "Add Visit" button on patient profile
- Doctor manually entered visit information
- Visits not tied to appointments

✅ **New Workflow (Active)**
- Appointments are now the primary workflow
- Book → Accept → Complete (auto-creates visit)
- Visit data captured during completion
- Appointment status tracked throughout

---

## How to Use the New Workflow

### Step 1: Book an Appointment
1. Go to: **Appointments** menu (new nav item)
2. Choose **Calendar/Day View** tab
3. Click on a **green (available)** slot
4. Select patient from dropdown
5. Choose appointment type
6. Click **"Book"**

**Result**: Appointment appears with status **"scheduled"** (yellow badge)

---

### Step 2: Accept the Appointment
1. Find the appointment in **List View** or **Calendar View**
2. Click **"Accept"** button
3. Confirm when prompted

**Result**: Appointment status changes to **"accepted"** (blue badge)

---

### Step 3: Complete the Appointment
1. Find the accepted appointment
2. Click **"Complete & Record"** button
3. Modal opens with form fields:
   - **ENT Classification** (required): Select Ear/Nose/Throat/etc.
   - **Diagnosis** (required): Enter clinical diagnosis
   - **Treatment** (required): Enter treatment details
   - **Notes** (optional): Add any additional observations
4. Click **"Complete & Record Visit"**

**Result**: 
- ✅ Appointment status → **"completed"** (green badge)
- ✅ Visit automatically created in patient timeline
- ✅ All entered data saved to visit record

---

## Navigation

| Location | Old Button | New Location |
|----------|-----------|--------------|
| Patient Profile | "Add Visit" (removed) | Appointments page (link in info banner) |
| Main Menu | N/A | New "Appointments" nav link for Doctor/Admin |
| Appointment Management | Manual visits | Appointments page → Calendar/List view |

---

## Key Differences

| Aspect | Old Way | New Way |
|--------|---------|---------|
| **Entry Point** | Patient profile | Appointments page |
| **Scheduling** | Manual datetime entry | Appointment slots with availability |
| **Doctor Confirmation** | None | Accept button before completion |
| **Data Capture** | All at once | At completion (doctor verified) |
| **Visit Tracking** | Disconnected | Linked to appointment |
| **Audit Trail** | Limited | Full status history |

---

## Status Badges Explained

| Badge | Meaning | Action Available |
|-------|---------|------------------|
| 🟨 scheduled | Appointment booked, not yet confirmed | Accept, Reschedule, Cancel |
| 🔵 accepted | Doctor confirmed they'll see patient | Complete & Record, Reschedule, Cancel |
| 🟢 completed | Appointment finished, visit recorded | View only |
| 🔴 cancelled | Appointment not happening | View only |

---

## Quick Actions

### Reschedule an Appointment
1. Click **"Reschedule"** button
2. Select new time slot
3. Click **"Reschedule"**

**Note**: Can reschedule before completing

### Cancel an Appointment
1. Click **"Cancel"** button
2. Confirm cancellation

**Note**: Cannot cancel completed appointments

### View Appointment Details
- **Calendar View**: Shows hourly slots, click for details
- **List View**: Shows all appointments for date with status

---

## Common Questions

**Q: Can I add notes to an appointment?**
A: Yes, when booking or completing. Notes are stored and visible in the visit record.

**Q: What if I need to change the visit details after completing?**
A: Complete appointments are locked. Create a new appointment for follow-up visit.

**Q: Can I access the old "Add Visit" form?**
A: No, it's been removed. All visits must be created through the appointment workflow.

**Q: Do I have to accept before completing?**
A: Yes, accept confirms the doctor will see the patient, completing records the visit.

**Q: Can patients access appointments?**
A: No, only doctor and admin can access the appointments page.

---

## Troubleshooting

**Appointment not showing up?**
- Refresh the page
- Check if it's on the selected date
- Verify in List View

**Complete button not showing?**
- Make sure you clicked "Accept" first
- Check appointment status is "accepted"

**Visit not appearing after completion?**
- Refresh patient profile page
- Verify appointment was completed (status = "completed")
- Check correct patient ID

**Can't find Appointments page?**
- Look for new "Appointments" menu item (with calendar icon)
- May need to log out and back in to see new menu

---

## System Requirements Met

✅ Appointment scheduling with time slots
✅ Doctor confirmation via "Accept"
✅ Auto-visit creation on completion
✅ ENT classification captured
✅ Diagnosis and treatment recorded
✅ Prescription support
✅ Appointment status tracking
✅ Authorization control (doctor/admin only)
✅ User-friendly modal interface
✅ Proper data persistence

---

## Important Notes

- **All visits must go through appointments**: Manual visit entry disabled
- **Status is immutable after completion**: Can't edit completed appointments
- **Audit trail maintained**: All status changes timestamped
- **Role-based access**: Only doctor/admin can complete appointments
- **Data integrity**: All information preserved in visit record

---

## For Administrators

**Enable/Disable Access:**
- Edit: `public/includes/helpers.php`
- Function: `canAccessPage()`
- Modify roles for 'appointments' page access

**Customize ENT Classifications:**
- Edit: `public/pages/appointments.php`
- Find: `completeEntType` select element
- Modify options as needed

**Adjust Appointment Types:**
- Edit: `database/schema.sql`
- Table: `appointment_types`
- Modify duration, buffer, daily_max

---

## Support Resources

- **Full Documentation**: `APPOINTMENT_WORKFLOW_COMPLETE.md`
- **Technical Details**: `APPOINTMENT_WORKFLOW_IMPLEMENTATION.md`
- **Test Instructions**: `TEST_APPOINTMENT_WORKFLOW.md`
- **Integration Test**: `test-appointment-workflow.php`

---

**Last Updated**: December 2025
**Version**: 1.0 - Complete Implementation
**Status**: ✅ Ready for Production Use
