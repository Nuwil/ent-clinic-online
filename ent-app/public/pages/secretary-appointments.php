<?php
// Redirect legacy secretary appointments page to unified appointments view
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/helpers.php';

// Ensure the user has access as staff/secretary before redirecting
requireRole(['secretary','staff']);

// Redirect to the unified appointments page where staff can book/manage
header('Location: ' . baseUrl() . '/?page=appointments');
exit;
?>
    <div class="flex flex-between mb-3">
        <div>
            <h2>Appointments Management</h2>
            <p class="text-muted">View, schedule and manage patient appointments</p>
        </div>
        <div style="display:flex; gap:12px; align-items:center;">
            <button id="addAppointmentBtn" class="btn btn-primary" onclick="openBookModal()">
                <i class="fas fa-calendar-plus"></i>
                Add Appointment
            </button>
            <input type="date" id="datePicker" value="<?php echo e($selected); ?>" />
            <button id="todayBtn" class="btn btn-sm btn-outline">Today</button>
            <button id="prevDayBtn" class="btn btn-sm btn-outline">&larr; Prev</button>
            <button id="nextDayBtn" class="btn btn-sm btn-outline">Next &rarr;</button>
        </div>
    </div>

    <!-- Main Container -->
    <div id="appointmentsContainer" style="display: flex; gap: 20px; margin-top: 20px;">
        <!-- Calendar -->
        <div style="flex: 1; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <div id="calendar" style="min-height: 400px;"></div>
        </div>

        <!-- Appointments List -->
        <div style="flex: 1;">
            <div style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <h3 style="margin-top: 0;">Appointments for <span id="selectedDateDisplay"><?php echo date('M d, Y', strtotime($selected)); ?></span></h3>
                <div id="appointmentsList" style="min-height: 300px;">
                    <p style="color: #999;">Loading appointments...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Book Appointment Modal -->
    <div class="modal" id="bookModal" hidden aria-hidden="true" role="dialog" aria-modal="true">
        <div class="modal-backdrop" data-modal-dismiss="bookModal"></div>
        <div class="modal-dialog form-modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-calendar-plus"></i>
                    Book New Appointment
                </h3>
                <button type="button" class="modal-close" data-modal-dismiss="bookModal" aria-label="Close">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="bookForm">
                    <div class="form-group">
                        <label class="form-label">Patient *</label>
                        <select id="patientId" class="form-control" required>
                            <option value="">Select patient</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Appointment Type *</label>
                        <select id="appointmentType" class="form-control" required>
                            <option value="">Select type</option>
                            <option value="new_patient">New Patient</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="procedure">Procedure</option>

                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date *</label>
                        <input type="date" id="appointmentDate" class="form-control" required />
                    </div>

                    <!-- Time slot removed: bookings are now date-only (default 09:00 server local) -->
                    <div class="form-group" style="display:none;">
                        <label class="form-label">Time Slot (optional)</label>
                        <select id="appointmentSlot" class="form-control" style="display:none;">
                            <option value="">Select a time slot</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Chief Complaint</label>
                        <textarea id="appointmentChiefComplaint" class="form-control" rows="2" placeholder="Short chief complaint or reason for visit"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason/Notes</label>
                        <textarea id="appointmentNotes" class="form-control" rows="3" placeholder="Add relevant notes"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-dismiss="bookModal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitBookForm()">
                    <i class="fas fa-calendar-check"></i>
                    Book Appointment
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .flex {
        display: flex;
    }
    .flex-between {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .mb-3 {
        margin-bottom: 20px;
    }
    .text-muted {
        color: #6c757d;
        font-size: 0.95rem;
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const datePicker = document.getElementById('datePicker');
    const selectedDateDisplay = document.getElementById('selectedDateDisplay');
    const appointmentsList = document.getElementById('appointmentsList');
    const bookModal = document.getElementById('bookModal');

    // Load patients for dropdown
    loadPatients();

    // Load appointments for selected date
    loadAppointmentsForDate(datePicker.value);

    // Handle date picker change
    datePicker.addEventListener('change', function() {
        loadAppointmentsForDate(this.value);
        updateDateDisplay(this.value);
    });

    // Navigation buttons
    document.getElementById('todayBtn').addEventListener('click', function() {
        const today = new Date().toISOString().split('T')[0];
        datePicker.value = today;
        loadAppointmentsForDate(today);
        updateDateDisplay(today);
    });

    document.getElementById('prevDayBtn').addEventListener('click', function() {
        const date = new Date(datePicker.value);
        date.setDate(date.getDate() - 1);
        const newDate = date.toISOString().split('T')[0];
        datePicker.value = newDate;
        loadAppointmentsForDate(newDate);
        updateDateDisplay(newDate);
    });

    document.getElementById('nextDayBtn').addEventListener('click', function() {
        const date = new Date(datePicker.value);
        date.setDate(date.getDate() + 1);
        const newDate = date.toISOString().split('T')[0];
        datePicker.value = newDate;
        loadAppointmentsForDate(newDate);
        updateDateDisplay(newDate);
    });

    // Modal handlers
    document.querySelectorAll('[data-modal-dismiss="bookModal"]').forEach(btn => {
        btn.addEventListener('click', function() {
            bookModal.classList.remove('open');
            bookModal.setAttribute('hidden', '');
        });
    });

    function updateDateDisplay(dateStr) {
        const date = new Date(dateStr + 'T00:00:00');
        const options = { year: 'numeric', month: 'short', day: 'numeric' };
        selectedDateDisplay.textContent = date.toLocaleDateString('en-US', options);
    }

    function loadPatients() {
        fetch('<?php echo baseUrl(); ?>/api.php?route=/api/patients')
            .then(r => r.json())
            .then(data => {
                const select = document.getElementById('patientId');
                const patients = data.patients || [];
                patients.forEach(p => {
                    const option = document.createElement('option');
                    option.value = p.id;
                    option.textContent = (p.full_name || p.first_name + ' ' + p.last_name) + ' (' + p.patient_id + ')';
                    select.appendChild(option);
                });
            })
            .catch(err => console.error('Error loading patients:', err));
    }

    function loadAppointmentsForDate(dateStr) {
        fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments&start=' + dateStr + '&end=' + dateStr)
            .then(r => r.json())
            .then(data => {
                const apts = data.appointments || [];
                if (apts.length === 0) {
                    appointmentsList.innerHTML = '<p style="color: #999; padding: 20px; text-align: center;">No appointments scheduled</p>';
                    return;
                }

                appointmentsList.innerHTML = apts.map(apt => {
                    const time = new Date(apt.appointment_date).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'});
                    const statusColor = apt.status === 'Pending' ? '#fff3cd' : apt.status === 'Accepted' ? '#cfe2ff' : '#e8f5e9';
                    const statusText = apt.status || 'Pending';
                    
                    return `
                        <div style="padding: 12px; border: 1px solid #ddd; border-radius: 6px; margin-bottom: 12px; background: #f9f9f9;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <strong>Patient #${apt.patient_id}</strong> | ${apt.appointment_type || '-'}<br>
                                    <small style="color: #666;">${time}</small>
                                </div>
                                <span style="background: ${statusColor}; padding: 4px 8px; border-radius: 3px; font-size: 0.85rem;">${statusText}</span>
                            </div>
                            ${apt.chief_complaint ? '<div style="margin-top: 8px; font-size: 0.9rem; color: #333;">' + '<strong>Chief complaint:</strong> ' + apt.chief_complaint + '</div>' : ''}
                            ${apt.notes ? '<div style="margin-top: 8px; font-size: 0.9rem; color: #666;">' + apt.notes + '</div>' : ''}
                        </div>
                    `;
                }).join('');
            })
            .catch(err => console.error('Error loading appointments:', err));
    }

    window.openBookModal = function() {
        bookModal.classList.add('open');
        bookModal.removeAttribute('hidden');
        // default appointment date to the currently selected day
        const dp = document.getElementById('datePicker');
        const apptDate = document.getElementById('appointmentDate');
        if (dp && apptDate && !apptDate.value) {
            apptDate.value = dp.value || new Date().toISOString().split('T')[0];
        }
    };

    window.submitBookForm = function() {
        const patientId = document.getElementById('patientId').value;
        const type = document.getElementById('appointmentType').value;
        const date = document.getElementById('appointmentDate').value;
        const notes = document.getElementById('appointmentNotes').value;

        if (!patientId || !type || !date) {
            alert('Please fill in required fields');
            return;
        }

        // Create local datetime and format as local SQL datetime to avoid timezone shifts
        const localStart = new Date(date + 'T09:00:00');
        const localEnd = new Date(localStart.getTime() + 60 * 60 * 1000);
        const pad = (n) => String(n).padStart(2, '0');
        const formatLocalSQL = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
        const start_at = formatLocalSQL(localStart);
        const end_at = formatLocalSQL(localEnd);

        const chiefComplaint = document.getElementById('appointmentChiefComplaint') ? document.getElementById('appointmentChiefComplaint').value : '';
        fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                patient_id: parseInt(patientId),
                type: type,
                start_at: start_at,
                end_at: end_at,
                notes: notes,
                chief_complaint: chiefComplaint
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success || data.id) {
                alert('Appointment scheduled successfully!');
                document.getElementById('bookForm').reset();
                bookModal.classList.remove('open');
                bookModal.setAttribute('hidden', '');
                // update date picker to booked date (use start_at from request)
                try {
                    const d = start_at.split(' ')[0];
                    if (d) {
                        document.getElementById('datePicker').value = d;
                        updateDateDisplay(d);
                    }
                } catch (e) {}
                loadAppointmentsForDate(document.getElementById('datePicker').value);
            } else {
                alert('Error scheduling appointment: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Error scheduling appointment');
        });
    };
});
</script>
