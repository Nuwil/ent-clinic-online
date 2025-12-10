Please remove the entire Analytics module — all files and functions — since it is not working.
Next, update the appointment system with the following requirements:"

✅ 1. Add Appointment Modal (Updated Requirements)

The Add Appointment modal must no longer require selecting a doctor.

Remove:

❌ Doctor selection field

Add Appointment Modal Fields:
A. Appointment Details

Patient

Appointment Date

Appointment Time

B. Vitals (must be recorded when appointment is created)

Height

Weight

Blood Pressure

Temperature

Pulse Rate

Respiratory Rate

Oxygen Saturation

Anyone who has access (Admin or Doctor) can create appointments.

✅ 2. Access Control Change

The Appointment Page should be visible to:

Admin

Doctor

There is no doctor assignment per appointment anymore.

Any doctor can open or accept any appointment.

✅ 3. Remove the Waitlist System Completely

Since the waitlist is not functioning and unnecessary:

Remove the Waitlist tab

Remove all waitlist logic from frontend & backend

All appointments simply appear in the main Appointment list

✅ 4. Appointment Actions

Each appointment should have 3 action buttons:

✔ Accept Appointment

When a doctor clicks Accept:

The appointment status becomes Accepted

The Visit Modal must immediately appear

✔ Reschedule Appointment

Allows changing the appointment date & time

✔ Cancel Appointment

Sets status to Cancelled

Removes from active appointments list

✅ 5. Visit Modal (Displays only after Accepting an Appointment)

When accepted, the Visit Modal must load with:

Autofilled From Appointment:

Patient

Appointment Date

Appointment Time

All Vitals

Fields Doctor Will Fill:

Chief Complaint

Type of ENT (dropdown)

Diagnosis

Treatment

Prescription

Plan

✅ 6. Final System Flow
Admin / Doctor:

Open Add Appointment

Enter patient + date + time + vitals

Save appointment

Appointment appears in main Appointment list (no waitlist)

Doctor chooses:

Accept → Visit Modal appears

Resched → Change date/time

Cancel → Remove/cancel

Doctor fills out Visit Modal and saves the visit

🎯 Developer Summary

Remove Analytics

Remove Waitlist

Remove Doctor assignment from appointments

Appointment page only for Admin + Doctors

Add vitals to appointment creation

Accept → automatically opens Visit Modal with complete ENT fields