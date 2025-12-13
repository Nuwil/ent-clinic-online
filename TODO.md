1. Role-Based Access Control (RBAC) Definition [COMPLETED]
Admin: Access to all system features.
Doctor: Access to Dashboard, Patient List → Patient Profile/Timeline, Appointments, and Settings (User Management & Data Backup).
Secretary: Access to Dashboard, Patient List → Patient Profile/Timeline, and Appointments page.

2. Add a Chief Complaint input field in the Add Appointment modal [COMPLETED]
This field is connected and synchronized with the Visit Chief Complaint across:
- Appointments page (Book modal)
- Secretary Appointments page
- Patient Profile Add Appointment modal
- The Visit/Complete modal is prefilled from the appointment's chief_complaint on accept.

3. Follow-ups / Next Steps
- Run DB migrations on your environment (backup DB first): execute `ent-app/database/migrations/*.sql` or apply via your tooling.
- Verify `chief_complaint` persistence: run `ent-app/tests/test_appointments_and_visits.php` after migrations.
- Verify RBAC behavior across roles (admin/doctor/secretary) and confirm `Settings` visibility for doctors.
- Add server-side validation or length limit for `chief_complaint` if required.
	- You can run the included migration runner: `php ent-app/scripts/run_migrations.php` (or `--dry-run`).
	- Run the integration test with: `php ent-app/tests/test_integration_chief_complaint.php "http://localhost/ent-clinic-online/ent-app/public" 1` (adjust base URL and patient id as needed).

4. Bugfixes Applied [COMPLETED]
- Secretary could not access the Appointments page: added `secretary-appointments` to allowed pages and page access mappings; navigation now links to the secretary-specific page.
- Doctor access to Settings removed: `view_settings/manage_users/export_data` permissions cleared for `doctor`, and the Settings page now requires `admin` role.
 - Added `ent-app/tests/test_rbac.php` to assert RBAC behavior for Secretary/Doctor/Admin on relevant pages.
 - Hidden the 'Accept' appointment button for Secretary/Staff users in the Appointments page UI; visible only to Admin/Doctor.
 - Updated `appointments.php` and `patient-profile.php` to display Accept only when `isDoctorOrAdmin` is true.
	 - Manual verification: log in as a Secretary → Appointments page should not show 'Accept' buttons; log in as Doctor/Admin → 'Accept' available.
 - Hidden the 'Complete & Record' button for Secretary/Staff users in the Appointments page UI; visible only to Admin/Doctor.
 - Updated `appointments.php` to display 'Complete & Record' only when `isDoctorOrAdmin` true, and protected the `complete` API endpoint as `doctor/admin` only.
 	 - Manual verification: log in as a Secretary → Appointments page should not show 'Complete & Record' button on accepted appointments; log in as Doctor/Admin → 'Complete & Record' available and functional.