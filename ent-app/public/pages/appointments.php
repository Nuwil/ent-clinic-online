<?php
// Enhanced Appointments page - calendar/day view with drag-to-reschedule and waitlist management
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/helpers.php';

$userRole = getCurrentUserRole();
$isDoctorOrAdmin = in_array($userRole, ['doctor', 'admin']);

$selected = $_GET['date'] ?? date('Y-m-d');
?>
<div class="appointments-page" style="padding: 20px;">
    <div class="flex flex-between mb-3">
        <div>
            <h2>Appointments & Schedule</h2>
            <p class="text-muted">View and manage appointments, reschedule, and manage waitlist</p>
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

    <!-- Tabs -->
    <div class="tabs" style="margin-bottom:20px; border-bottom:1px solid #ddd;">
        <button class="tab-btn active" data-tab="calendar" style="padding:12px 20px; border:none; background:none; cursor:pointer; font-weight:600; border-bottom:3px solid #667eea;">Calendar/Day View</button>
        <button class="tab-btn" data-tab="list" style="padding:12px 20px; border:none; background:none; cursor:pointer;">List View</button>
        <button class="tab-btn" data-tab="waitlist" style="padding:12px 20px; border:none; background:none; cursor:pointer;">Waitlist</button>
    </div>

    <!-- Calendar/Day View Tab -->
    <div id="calendar-tab" class="tab-content" style="display:block;">
        <div id="dayViewContainer" style="background:#f9f9f9; border:1px solid #ddd; border-radius:8px; padding:20px; min-height:600px;">
            <div style="text-align:center; color:#999;">Loading schedule...</div>
        </div>
        <div id="dragHint" style="margin-top:12px; color:#666; font-size:0.9rem;">
            <i class="fas fa-info-circle"></i> Click on available slots to book; click Reschedule to move scheduled appointments
        </div>
    </div>

    <!-- List View Tab -->
    <div id="list-tab" class="tab-content" style="display:none;">
        <div id="listViewContainer" style="display:flex; flex-direction:column; gap:12px;">
            <div style="text-align:center; color:#999;">Loading...</div>
        </div>
    </div>

    <!-- Waitlist Tab -->
    <div id="waitlist-tab" class="tab-content" style="display:none;">
        <div style="margin-bottom:16px;">
            <h3>Waitlist</h3>
            <p class="text-muted">Patients waiting for appointments</p>
        </div>
        <div id="waitlistContainer" style="display:flex; flex-direction:column; gap:12px;">
            <div style="text-align:center; color:#999;">Loading waitlist...</div>
        </div>
    </div>

    <!-- Quick Add Patient Modal (for booking) -->
    <div id="bookModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%;">
            <h3>Book Appointment</h3>
            <form id="bookForm">
                <div class="form-group">
                    <label>Patient</label>
                    <select id="bookPatient" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="bookType" class="form-control">
                        <option value="new_patient">New Patient</option>
                        <option value="follow_up">Follow-up</option>
                        <option value="procedure">Procedure</option>
                        <option value="emergency">Emergency</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Time Slot *</label>
                    <div id="bookSlotContainer" style="display:flex; gap:8px; align-items:center;">
                        <select id="bookSlotSelect" class="form-control" style="display:none;">
                            <option value="">Select a time slot</option>
                        </select>
                        <input type="text" id="bookSlot" class="form-control" readonly placeholder="Click on a slot or select from dropdown" />
                    </div>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea id="bookNotes" class="form-control" rows="3"></textarea>
                </div>
                <div id="bookFormMessage" style="margin-top:8px; color:red; min-height:1.2rem;"></div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeBookModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Book</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="rescheduleModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:8px; padding:24px; max-width:500px; width:90%;">
            <h3>Reschedule Appointment</h3>
            <form id="rescheduleForm">
                <input type="hidden" id="rescheduleId" />
                <div class="form-group">
                    <label>Select New Slot</label>
                    <select id="rescheduleSlot" class="form-control" required></select>
                </div>
                <div class="form-group">
                    <label>Notes (optional)</label>
                    <textarea id="rescheduleNotes" class="form-control" rows="3"></textarea>
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeRescheduleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Reschedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Complete Appointment Modal (Doctor completes visit and creates visit record) -->
    <div id="completeModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:8px; padding:24px; max-width:600px; width:90%; max-height:80vh; overflow-y:auto;">
            <h3>Complete Appointment & Record Visit</h3>
            <p style="color:#666; font-size:0.9rem;">This will mark the appointment as completed and create a visit record in the patient's timeline.</p>
            <form id="completeForm">
                <input type="hidden" id="completeId" />
                <div class="form-group">
                    <label>ENT Classification *</label>
                    <select id="completeEntType" class="form-control" required>
                        <option value="">Select classification</option>
                        <option value="ear">Ear Issues</option>
                        <option value="nose">Nose Issues</option>
                        <option value="throat">Throat Issues</option>
                        <option value="head_neck_tumor">Head/Neck Issues</option>
                        <option value="lifestyle_medicine">Lifestyle Medicine</option>
                        <option value="misc">Other/Misc</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Diagnosis *</label>
                    <textarea id="completeDiagnosis" class="form-control" rows="3" required placeholder="Enter diagnosis or findings"></textarea>
                </div>
                <div class="form-group">
                    <label>Treatment/Procedure *</label>
                    <textarea id="completeTreatment" class="form-control" rows="3" required placeholder="Enter treatment, procedure, or recommendations"></textarea>
                </div>
                <div class="form-group">
                    <label>Additional Notes (optional)</label>
                    <textarea id="completeNotes" class="form-control" rows="2" placeholder="Any additional notes or observations"></textarea>
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeCompleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-success">Complete & Record Visit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.tab-btn { transition: all 0.3s; color: #666; }
.tab-btn.active { color: #667eea; border-bottom: 3px solid #667eea; }
.tab-btn:hover { color: #667eea; }

.appointment-slot {
    padding: 12px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
    user-select: none;
}

.appointment-slot:hover { background: #e8e8e8; }
.appointment-slot.booked { background: #667eea; color: white; }
.appointment-slot.procedure { background: #ff9800; color: white; }
.appointment-slot.emergency { background: #f44336; color: white; }
.appointment-slot.available { background: #4caf50; color: white; cursor: pointer; }

.day-hour { font-weight: 600; color: #333; margin-top: 20px; margin-bottom: 8px; }
.waitlist-item { padding: 12px; background: #f5f5f5; border-left: 4px solid #ff9800; border-radius: 4px; }
</style>

<script>
let currentDate = '<?php echo e($selected); ?>';
const isDoctorOrAdmin = <?php echo $isDoctorOrAdmin ? 'true' : 'false'; ?>;
let allSlots = [];
let allAppointments = [];
let allWaitlist = [];

function loadPatients() {
    return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/patients&limit=999')
        .then(r => r.json())
        .then(j => {
            // Support both legacy direct-response ({patients: [...]}) and
            // the API controller's success wrapper ({success: true, data: { patients: [...] }})
            const patients = j.patients || (j.data && j.data.patients) || [];
            if (!patients || patients.length === 0) {
                if (j && (j.error || (j.data && (!j.data.patients || j.data.patients.length === 0)))) {
                    console.error('API response invalid or empty:', j);
                }
                return [];
            }
            return patients;
        })
        .catch(err => {
            console.error('Error loading patients:', err);
            return [];
        });
}

function loadSlots(date) {
    return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/slots&date=' + date)
        .then(r => r.json())
        .then(j => {
            allSlots = j.slots || [];
            return allSlots;
        });
}

function loadAppointments(date) {
    return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments&start=' + date + '&end=' + date)
        .then(r => r.json())
        .then(j => {
            allAppointments = j.appointments || [];
            return allAppointments;
        });
}

function loadWaitlist() {
    return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/waitlist')
        .then(r => r.json())
        .then(j => {
            allWaitlist = j.waitlist || [];
            return allWaitlist;
        })
        .catch(() => { allWaitlist = []; return []; });
}

function renderDayView(date) {
    loadSlots(date).then(() => {
        loadAppointments(date).then(() => {
            const container = document.getElementById('dayViewContainer');
            container.innerHTML = '';

            const dateObj = new Date(date);
            const dayName = dateObj.toLocaleDateString('en-US', { weekday: 'long', month: 'short', day: 'numeric' });
            container.innerHTML += '<h3 style="margin-top:0;">' + dayName + '</h3>';

            // Group slots by hour
            const byHour = {};
            allSlots.forEach(s => {
                const start = new Date(s.start);
                const hour = start.getHours();
                if (!byHour[hour]) byHour[hour] = [];
                byHour[hour].push(s);
            });

            // Render hour blocks
            Object.keys(byHour).sort((a,b) => a-b).forEach(hour => {
                const h = parseInt(hour);
                const timeStr = (h < 10 ? '0' : '') + h + ':00';
                container.innerHTML += '<div class="day-hour">' + timeStr + '</div>';

                byHour[hour].forEach(slot => {
                    const start = new Date(slot.start);
                    const end = new Date(slot.end);
                    const label = start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + 
                                  end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' (' + slot.type + ')';

                    let css = 'appointment-slot';
                    let action = '';

                    if (slot.booked) {
                        css += ' ' + (slot.type === 'procedure' ? 'procedure' : slot.type === 'emergency' ? 'emergency' : 'booked');
                        const apt = allAppointments.find(a => a.start_at === slot.start && a.end_at === slot.end);
                        if (apt) {
                            let statusBadge = '<span style="font-size:0.8rem; margin-left:8px;">(' + apt.status + ')</span>';
                            let buttons = '';
                            if (apt.status === 'scheduled') {
                                buttons = ' <button type="button" class="btn btn-xs btn-primary" onclick="acceptAppointment(' + apt.id + ')">Accept</button>' +
                                         ' <button type="button" class="btn btn-xs" onclick="openRescheduleModal(' + apt.id + ')">Reschedule</button>' +
                                         ' <button type="button" class="btn btn-xs btn-danger" onclick="cancelAppt(' + apt.id + ')">Cancel</button>';
                            } else if (apt.status === 'accepted') {
                                buttons = ' <button type="button" class="btn btn-xs btn-success" onclick="openCompleteModal(' + apt.id + ')">Complete</button>' +
                                         ' <button type="button" class="btn btn-xs" onclick="openRescheduleModal(' + apt.id + ')">Reschedule</button>' +
                                         ' <button type="button" class="btn btn-xs btn-danger" onclick="cancelAppt(' + apt.id + ')">Cancel</button>';
                            } else if (apt.status === 'completed') {
                                statusBadge = '<span style="font-size:0.8rem; margin-left:8px; color:#0f5132; font-weight:600;">✓ Completed</span>';
                            } else if (apt.status === 'cancelled') {
                                statusBadge = '<span style="font-size:0.8rem; margin-left:8px; color:#842029; font-weight:600;">✗ Cancelled</span>';
                            }
                            action = statusBadge + buttons;
                        }
                    } else {
                        css += ' available';
                        action = ' <button type="button" class="btn btn-xs" onclick="openBookModal(\'' + slot.start + '\', \'' + slot.end + '\')">Book</button>';
                    }

                    container.innerHTML += '<div class="' + css + '">' + label + action + '</div>';
                });
            });
        });
    });
}

function renderListView(date) {
    loadAppointments(date).then(apts => {
        const container = document.getElementById('listViewContainer');
        container.innerHTML = '';

        if (apts.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#999;">No appointments scheduled for this date.</p>';
            return;
        }

        apts.forEach(apt => {
            const start = new Date(apt.start_at);
            const end = new Date(apt.end_at);
            const timeStr = start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + 
                            end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

            // Status badge colors
            let statusBgColor = '#e0e0e0';
            let statusTextColor = '#333';
            if (apt.status === 'scheduled') {
                statusBgColor = '#fff3cd';
                statusTextColor = '#856404';
            } else if (apt.status === 'accepted') {
                statusBgColor = '#cfe2ff';
                statusTextColor = '#084298';
            } else if (apt.status === 'completed') {
                statusBgColor = '#d1e7dd';
                statusTextColor = '#0f5132';
            } else if (apt.status === 'cancelled') {
                statusBgColor = '#f8d7da';
                statusTextColor = '#842029';
            }

            // Build action buttons based on status
            let actionButtons = '';
            if (apt.status === 'scheduled') {
                actionButtons = `
                    <button class="btn btn-sm btn-primary" onclick="acceptAppointment(${apt.id})">Accept</button>
                    <button class="btn btn-sm" onclick="openRescheduleModal(${apt.id})">Reschedule</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(${apt.id})">Cancel</button>
                `;
            } else if (apt.status === 'accepted') {
                actionButtons = `
                    <button class="btn btn-sm btn-success" onclick="openCompleteModal(${apt.id})">Complete & Record</button>
                    <button class="btn btn-sm" onclick="openRescheduleModal(${apt.id})">Reschedule</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(${apt.id})">Cancel</button>
                `;
            } else if (apt.status === 'completed') {
                actionButtons = '<span style="color:#0f5132; font-weight:600;">✓ Completed</span>';
            } else if (apt.status === 'cancelled') {
                actionButtons = '<span style="color:#842029; font-weight:600;">✗ Cancelled</span>';
            }

            container.innerHTML += `
                <div style="padding:12px; background:#f5f5f5; border-radius:6px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <strong>Patient #${apt.patient_id}</strong> | ${apt.type} | <strong>${timeStr}</strong>
                        <span style="background:${statusBgColor}; color:${statusTextColor}; padding:2px 8px; border-radius:3px; font-size:0.85rem; margin-left:8px;">${apt.status}</span>
                        ${apt.notes ? '<br><small>' + apt.notes + '</small>' : ''}
                    </div>
                    <div style="display:flex; gap:8px;">
                        ${actionButtons}
                    </div>
                </div>
            `;
        });
    });
}

function renderWaitlist() {
    loadWaitlist().then(list => {
        const container = document.getElementById('waitlistContainer');
        container.innerHTML = '';

        if (list.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#999;">Waitlist is empty.</p>';
            return;
        }

        list.forEach((item, idx) => {
            container.innerHTML += `
                <div class="waitlist-item">
                    <div><strong>#${idx+1} - Patient #${item.patient_id}</strong></div>
                    <div style="color:#666; font-size:0.9rem;">${item.reason || 'No reason specified'}</div>
                    <small style="color:#999;">${new Date(item.created_at).toLocaleDateString()}</small>
                    <div style="margin-top:8px;">
                        <button class="btn btn-sm" onclick="notifyWaitlistPatient(${item.patient_id})">Notify Patient</button>
                        <button class="btn btn-sm btn-danger" onclick="removeFromWaitlist(${item.id})">Remove</button>
                    </div>
                </div>
            `;
        });
    });
}

function openBookModal(start, end) {
    if (start && end) {
        document.getElementById('bookSlot').value = new Date(start).toLocaleString() + ' - ' + new Date(end).toLocaleString();
        document.getElementById('bookForm').dataset.start = start;
        document.getElementById('bookForm').dataset.end = end;
    } else {
        // If called without slot selection, user will select a slot from modal
        document.getElementById('bookSlot').value = '';
        document.getElementById('bookForm').dataset.start = '';
        document.getElementById('bookForm').dataset.end = '';
    }
    document.getElementById('bookModal').style.display = 'flex';
}

function closeBookModal() {
    document.getElementById('bookModal').style.display = 'none';
}

function openRescheduleModal(aptId) {
    document.getElementById('rescheduleId').value = aptId;
    loadSlots(currentDate).then(slots => {
        const select = document.getElementById('rescheduleSlot');
        select.innerHTML = '';
        slots.filter(s => !s.booked).forEach(s => {
            const start = new Date(s.start);
            const end = new Date(s.end);
            const label = start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + 
                          end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' (' + s.type + ')';
            const opt = document.createElement('option');
            opt.value = JSON.stringify({start: s.start, end: s.end});
            opt.textContent = label;
            select.appendChild(opt);
        });
    });
    document.getElementById('rescheduleModal').style.display = 'flex';
}

function closeRescheduleModal() {
    document.getElementById('rescheduleModal').style.display = 'none';
}

document.getElementById('bookForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const patient_id = document.getElementById('bookPatient').value;
    const type = document.getElementById('bookType').value;
    let start_at = this.dataset.start;
    let end_at = this.dataset.end;
    const notes = document.getElementById('bookNotes').value;

    // If no slot selected from click, try to get from dropdown
    if (!start_at && document.getElementById('bookSlotSelect').style.display !== 'none') {
        const slotValue = document.getElementById('bookSlotSelect').value;
        if (!slotValue) {
            alert('Please select a time slot');
            return;
        }
        try {
            const [start, end] = slotValue.split('|');
            start_at = start;
            end_at = end;
        } catch (e) {
            alert('Invalid slot selection');
            return;
        }
    }

    const messageBox = document.getElementById('bookFormMessage');
    messageBox.innerHTML = '';

    if (!start_at || !end_at) {
        messageBox.textContent = 'Please select a time slot';
        messageBox.style.color = 'red';
        return;
    }

    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    submitBtn.textContent = 'Booking...';

    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ patient_id, type, start_at, end_at, notes })
    });
    let j;
    try { j = await res.json(); } catch (ex) { j = null; }

    if (res.ok && j && (j.success || j.data)) {
        messageBox.style.color = 'green';
        messageBox.textContent = 'Appointment booked!';
        closeBookModal();
        renderDayView(currentDate);
        renderListView(currentDate);
    } else {
        // Show validation errors if provided
        messageBox.style.color = 'red';
        if (j && j.error) {
            if (typeof j.error === 'object') {
                const ul = document.createElement('ul');
                Object.entries(j.error).forEach(([k,v]) => { const li = document.createElement('li'); li.textContent = v; ul.appendChild(li); });
                messageBox.innerHTML = ''; messageBox.appendChild(ul);
            } else {
                messageBox.textContent = j.error;
            }
        } else if (j && j.message) {
            messageBox.textContent = j.message;
        } else {
            messageBox.textContent = 'Booking failed';
        }
    }

    submitBtn.disabled = false;
    submitBtn.textContent = 'Book';
});

document.getElementById('rescheduleForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const aptId = document.getElementById('rescheduleId').value;
    const newSlot = JSON.parse(document.getElementById('rescheduleSlot').value);
    const notes = document.getElementById('rescheduleNotes').value;

    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/reschedule', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ start_at: newSlot.start, end_at: newSlot.end, notes })
    });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment rescheduled!');
        closeRescheduleModal();
        renderDayView(currentDate);
        renderListView(currentDate);
    } else {
        alert(j.error || 'Reschedule failed');
    }
});

function openCompleteModal(aptId) {
    document.getElementById('completeId').value = aptId;
    document.getElementById('completeForm').reset();
    document.getElementById('completeModal').style.display = 'flex';
}

function closeCompleteModal() {
    document.getElementById('completeModal').style.display = 'none';
}

async function acceptAppointment(aptId) {
    if (!confirm('Accept this appointment?')) return;
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/accept', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment accepted!');
        renderDayView(currentDate);
        renderListView(currentDate);
    } else {
        alert(j.error || 'Failed to accept appointment');
    }
}

document.getElementById('completeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const aptId = document.getElementById('completeId').value;
    const ent_type = document.getElementById('completeEntType').value;
    const diagnosis = document.getElementById('completeDiagnosis').value;
    const treatment = document.getElementById('completeTreatment').value;
    const notes = document.getElementById('completeNotes').value;

    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ent_type, diagnosis, treatment, prescription_items: [], notes })
    });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment completed and visit record created!');
        closeCompleteModal();
        renderDayView(currentDate);
        renderListView(currentDate);
    } else {
        alert(j.error || 'Failed to complete appointment');
    }
});

async function cancelAppt(aptId) {
    if (!confirm('Cancel this appointment?')) return;
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/cancel', { method: 'POST' });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment cancelled');
        renderDayView(currentDate);
        renderListView(currentDate);
    }
}

async function notifyWaitlistPatient(patientId) {
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/waitlist/' + patientId + '/notify', { method: 'POST' });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Notification sent!');
    } else {
        alert(j.error || 'Failed to notify');
    }
}

async function removeFromWaitlist(itemId) {
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/waitlist/' + itemId, { method: 'DELETE' });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Removed from waitlist');
        renderWaitlist();
    }
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        this.classList.add('active');
        const tab = this.dataset.tab;
        document.getElementById(tab + '-tab').style.display = 'block';
        if (tab === 'list') renderListView(currentDate);
        else if (tab === 'waitlist') renderWaitlist();
    });
});

// Date navigation
document.getElementById('datePicker').addEventListener('change', function() {
    currentDate = this.value;
    renderDayView(currentDate);
    renderListView(currentDate);
});

document.getElementById('todayBtn').addEventListener('click', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('datePicker').value = today;
    currentDate = today;
    renderDayView(currentDate);
    renderListView(currentDate);
});

document.getElementById('prevDayBtn').addEventListener('click', function() {
    const d = new Date(currentDate);
    d.setDate(d.getDate() - 1);
    const prev = d.toISOString().split('T')[0];
    document.getElementById('datePicker').value = prev;
    currentDate = prev;
    renderDayView(currentDate);
    renderListView(currentDate);
});

document.getElementById('nextDayBtn').addEventListener('click', function() {
    const d = new Date(currentDate);
    d.setDate(d.getDate() + 1);
    const next = d.toISOString().split('T')[0];
    document.getElementById('datePicker').value = next;
    currentDate = next;
    renderDayView(currentDate);
    renderListView(currentDate);
});

// Load patients for booking modal
    loadPatients().then(patients => {
    const sel = document.getElementById('bookPatient');
    sel.innerHTML = '<option value="">Select a patient</option>'; // Clear and add default option
    if (!patients || patients.length === 0) {
        sel.innerHTML += '<option value="">No patients available</option>';
        return;
    }
    patients.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.id;
        opt.textContent = p.first_name + ' ' + (p.last_name || '');
        sel.appendChild(opt);
    });
});

// Populate available slots in book modal when it opens
const originalOpenBookModal = window.openBookModal;
window.openBookModal = function(start, end) {
    originalOpenBookModal.call(this, start, end);
    
    // Ensure patient dropdown is populated when modal opens
    const patientSelect = document.getElementById('bookPatient');
    if (!patientSelect) return;
    
    const optionCount = patientSelect.querySelectorAll('option').length;
    if (optionCount <= 1) {
        // Dropdown is empty or has only default option, populate it
        loadPatients().then(patients => {
            patientSelect.innerHTML = '<option value="">Select a patient</option>';
            if (patients && patients.length > 0) {
                patients.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.first_name + ' ' + (p.last_name || '');
                    patientSelect.appendChild(opt);
                });
            }
        });
    }
    
    // If no slot was pre-selected, populate dropdown with available slots
    if (!start && !end) {
                loadSlots(currentDate).then(slots => {
            const select = document.getElementById('bookSlotSelect');
            select.innerHTML = '<option value="">Select a time slot</option>';
            const avail = slots.filter(s => !s.booked);
            avail.forEach(s => {
                const startTime = new Date(s.start);
                const endTime = new Date(s.end);
                const label = startTime.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + 
                              endTime.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' (' + s.type + ')';
                const opt = document.createElement('option');
                opt.value = s.start + '|' + s.end;
                opt.textContent = label;
                select.appendChild(opt);
            });
            // Auto-select first available slot
            if (avail.length > 0) {
                select.value = avail[0].start + '|' + avail[0].end;
                // reflect into text field dataset for booking
                document.getElementById('bookForm').dataset.start = avail[0].start;
                document.getElementById('bookForm').dataset.end = avail[0].end;
                document.getElementById('bookSlot').value = new Date(avail[0].start).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + new Date(avail[0].end).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
            }
            select.style.display = 'block';
            document.getElementById('bookSlot').style.display = 'none';
            validateBookForm();
        });
    } else {
        // If slot was pre-selected, hide dropdown and show text field
        document.getElementById('bookSlotSelect').style.display = 'none';
        document.getElementById('bookSlot').style.display = 'block';
    }
};

// Initial render
// Form validation and helpers for booking modal
function validateBookForm() {
    const patient = document.getElementById('bookPatient') ? document.getElementById('bookPatient').value : '';
    const slotSelect = document.getElementById('bookSlotSelect');
    const start = document.getElementById('bookForm') ? document.getElementById('bookForm').dataset.start : '';
    const submitBtn = document.querySelector('#bookForm button[type="submit"]');
    const messageBox = document.getElementById('bookFormMessage');
    if (messageBox) messageBox.innerHTML = '';
    let hasSlot = false;
    if (slotSelect && slotSelect.style.display !== 'none' && slotSelect.value) hasSlot = true;
    if (start) hasSlot = true;
    if (submitBtn) {
        if (patient && hasSlot) submitBtn.disabled = false; else submitBtn.disabled = true;
    }
}

document.addEventListener('DOMContentLoaded', function(){
    const patientSel = document.getElementById('bookPatient');
    if (patientSel) patientSel.addEventListener('change', validateBookForm);
    const slotSel = document.getElementById('bookSlotSelect');
    if (slotSel) slotSel.addEventListener('change', function(){
        const val = this.value;
        if (val) {
            const [s,e] = val.split('|');
            const form = document.getElementById('bookForm');
            if (form) { form.dataset.start = s; form.dataset.end = e; }
            const bs = document.getElementById('bookSlot');
            if (bs) bs.value = new Date(s).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + new Date(e).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        } else {
            const form = document.getElementById('bookForm'); if (form) { form.dataset.start = ''; form.dataset.end = ''; }
            const bs = document.getElementById('bookSlot'); if (bs) bs.value = '';
        }
        validateBookForm();
    });
    // Set initial disabled state
    validateBookForm();
});
renderDayView(currentDate);
</script>