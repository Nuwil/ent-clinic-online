<?php
// Enhanced Appointments page - calendar/day view with drag-to-reschedule
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/helpers.php';

// Verify user is admin or doctor (secretaries have their own page)
requireRole(['admin', 'doctor']);

$userRole = getCurrentUserRole();
$isDoctorOrAdmin = in_array($userRole, ['doctor', 'admin']);

$selected = $_GET['date'] ?? date('Y-m-d');
?>
<div class="appointments-page" style="padding: 20px;">
    <div class="flex flex-between mb-3">
        <div>
            <h2>Appointments & Schedule</h2>
            <p class="text-muted">View and manage appointments and reschedule</p>
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

    <!-- Stacked Layout: Calendar on top, List below -->
    <div style="display: flex; flex-direction: column; gap: 20px;">
        <!-- Calendar/Day View (Top) -->
        <div>
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem;">Calendar View</h3>
            <div id="dayViewContainer" style="background:#f9f9f9; border:1px solid #ddd; border-radius:8px; padding:20px; min-height:500px;">
                <div style="text-align:center; color:#999;">Loading schedule...</div>
            </div>
            <div id="dragHint" style="margin-top:12px; color:#666; font-size:0.9rem;">
                <i class="fas fa-info-circle"></i> Click on available slots to book
            </div>
        </div>

        <!-- List View (Below) -->
        <div>
            <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 1.1rem;">Appointments List</h3>
            <div id="listViewContainer" style="display:flex; flex-direction:column; gap:12px; max-height:400px; overflow-y:auto; border: 1px solid #ddd; border-radius: 8px; padding: 15px; background: #f9f9f9;">
                <div style="text-align:center; color:#999;">Loading appointments...</div>
            </div>
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
                    <label>Time Slot (optional)</label>
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
        <div style="background:#fff; border-radius:8px; padding:24px; max-width:700px; width:90%; max-height:85vh; overflow-y:auto;">
            <h3>Complete Appointment & Record Visit</h3>
            <p style="color:#666; font-size:0.9rem;">This will mark the appointment as completed and create a visit record in the patient's timeline.</p>
            <form id="completeForm">
                <input type="hidden" id="completeId" />
                
                <div class="form-group">
                    <label>Chief Complaint *</label>
                    <textarea id="completeChiefComplaint" class="form-control" rows="2" required placeholder="What is the patient's main complaint?"></textarea>
                </div>

                <div class="form-group">
                    <label>Type of ENT *</label>
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
                    <label>Plan *</label>
                    <textarea id="completePlan" class="form-control" rows="2" required placeholder="Follow-up plan, next steps, or referrals"></textarea>
                </div>

                <div class="form-group">
                    <label>Prescription (optional)</label>
                    <textarea id="completePrescription" class="form-control" rows="3" placeholder="Medicine name | Dosage | Frequency | Duration&#10;e.g., Amoxicillin | 500mg | 3x daily | 7 days"></textarea>
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
</style>

<script>
let currentDate = '<?php echo e($selected); ?>';
const isDoctorOrAdmin = <?php echo $isDoctorOrAdmin ? 'true' : 'false'; ?>;
let allSlots = [];
let allAppointments = [];

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

function loadAppointmentsRange(startDate, endDate) {
    return fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments&start=' + startDate + '&end=' + endDate)
        .then(r => r.json())
        .then(j => {
            return j.appointments || [];
        });
}

function renderMonthView(date) {
    // Render a month calendar grid and populate appointments
    const container = document.getElementById('dayViewContainer');
    container.innerHTML = '';

    // parse date as YYYY-MM-DD
    const parts = (date || currentDate).split('-');
    const year = parseInt(parts[0], 10);
    const month = parseInt(parts[1], 10) - 1;
    const firstOfMonth = new Date(year, month, 1);

    // start at Sunday of the week containing the 1st
    const start = new Date(firstOfMonth);
    start.setDate(1 - start.getDay());

    // build header
    const monthName = firstOfMonth.toLocaleString(undefined, { month: 'long', year: 'numeric' });
    const header = document.createElement('div');
    header.style.display = 'flex';
    header.style.justifyContent = 'space-between';
    header.style.alignItems = 'center';
    header.style.marginBottom = '12px';
    header.innerHTML = '<h3 style="margin:0;">' + monthName + '</h3>' +
        '<div><button class="btn btn-sm btn-outline" id="prevMonthBtn">&larr; Prev</button> <button class="btn btn-sm btn-outline" id="nextMonthBtn">Next &rarr;</button></div>';
    container.appendChild(header);

    // calendar grid
    const grid = document.createElement('div');
    grid.style.display = 'grid';
    grid.style.gridTemplateColumns = 'repeat(7, 1fr)';
    grid.style.gap = '8px';

    // weekday headers
    const weekdays = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    weekdays.forEach(w => {
        const el = document.createElement('div');
        el.style.fontWeight = '600';
        el.style.textAlign = 'center';
        el.textContent = w;
        grid.appendChild(el);
    });

    // create 6 weeks x 7 days = 42 cells
    const cells = [];
    const iter = new Date(start);
    for (let i = 0; i < 42; i++) {
        const cell = document.createElement('div');
        cell.style.minHeight = '90px';
        cell.style.border = '1px solid #e6e6e6';
        cell.style.borderRadius = '6px';
        cell.style.padding = '8px';
        cell.style.background = '#fff';
        cell.dataset.date = iter.toISOString().slice(0,10);
        const dayNum = document.createElement('div');
        dayNum.style.fontSize = '0.9rem';
        dayNum.style.marginBottom = '6px';
        dayNum.style.fontWeight = '600';
        dayNum.textContent = iter.getDate();
        cell.appendChild(dayNum);
        const list = document.createElement('div');
        list.className = 'day-appointments';
        cell.appendChild(list);
        // click on day to book
        cell.addEventListener('click', function(e) {
            // avoid click when clicking an appointment item
            if (e.target.closest('.apt-item')) return;
            const d = this.dataset.date;
            document.getElementById('datePicker').value = d;
            currentDate = d;
            openBookModal();
        });
        grid.appendChild(cell);
        cells.push(cell);
        iter.setDate(iter.getDate() + 1);
    }

    container.appendChild(grid);

    // fetch appointments for the visible range
    const startDate = cells[0].dataset.date;
    const endDate = cells[cells.length-1].dataset.date;
    loadAppointmentsRange(startDate, endDate).then(apts => {
        // clear previous
        cells.forEach(c => c.querySelector('.day-appointments').innerHTML = '');
        apts.forEach(apt => {
            const aptDate = (apt.start_at || apt.appointment_date || '').slice(0,10);
            const cell = document.querySelector('[data-date="' + aptDate + '"]');
            if (!cell) return;
            const list = cell.querySelector('.day-appointments');
            const time = apt.start_at ? new Date(apt.start_at).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '';
            const div = document.createElement('div');
            div.className = 'apt-item';
            div.style.padding = '4px 6px';
            div.style.marginBottom = '4px';
            div.style.borderRadius = '4px';
            div.style.background = apt.status && apt.status.toLowerCase() === 'pending' ? '#fff3cd' : '#cfe2ff';
            div.style.cursor = 'pointer';
            div.innerHTML = '<strong style="font-size:0.85rem;">' + (time ? time + ' ' : '') + (apt.type || '') + '</strong><div style="font-size:0.75rem; color:#333;">Patient #' + apt.patient_id + '</div>';
            // clicking appointment opens actions
            div.addEventListener('click', function(ev) {
                ev.stopPropagation();
                // show quick actions
                const id = apt.id;
                if ((apt.status || '').toLowerCase() === 'pending') {
                    if (confirm('Accept this appointment?')) {
                        acceptAndRedirect(id, apt.patient_id);
                    }
                } else if ((apt.status || '').toLowerCase() === 'accepted') {
                    openCompleteModal(id);
                }
            });
            list.appendChild(div);
        });
    });

    // nav handlers
    document.getElementById('prevMonthBtn').addEventListener('click', function() {
        const d = new Date(currentDate);
        d.setDate(1);
        d.setMonth(d.getMonth() - 1);
        const m = d.toISOString().slice(0,10);
        currentDate = m;
        document.getElementById('datePicker').value = currentDate;
        renderMonthView(currentDate);
        renderListView(currentDate);
    });
    document.getElementById('nextMonthBtn').addEventListener('click', function() {
        const d = new Date(currentDate);
        d.setDate(1);
        d.setMonth(d.getMonth() + 1);
        const m = d.toISOString().slice(0,10);
        currentDate = m;
        document.getElementById('datePicker').value = currentDate;
        renderMonthView(currentDate);
        renderListView(currentDate);
    });
}

function renderListView(date) {
    // show appointments for the month of `date`
    const parts = (date || currentDate).split('-');
    const year = parseInt(parts[0],10);
    const month = parseInt(parts[1],10) - 1;
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);
    const startDate = first.toISOString().slice(0,10);
    const endDate = last.toISOString().slice(0,10);

    loadAppointmentsRange(startDate, endDate).then(apts => {
        const container = document.getElementById('listViewContainer');
        container.innerHTML = '';

        if (!apts || apts.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#999;">No appointments scheduled for this month.</p>';
            return;
        }

        apts.sort((a,b) => new Date(a.start_at || a.appointment_date) - new Date(b.start_at || b.appointment_date));
        apts.forEach(apt => {
            const start = new Date(apt.start_at || apt.appointment_date);
            const end = apt.end_at ? new Date(apt.end_at) : new Date(start.getTime() + 60*60*1000);
            const timeStr = start.toLocaleDateString() + ' ' + start.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) + ' - ' + 
                            end.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});

            let statusBgColor = '#e0e0e0';
            let statusTextColor = '#333';
            const st = (apt.status || 'Pending').toString().toLowerCase();
            if (st === 'pending') {
                statusBgColor = '#fff3cd';
                statusTextColor = '#856404';
            } else if (st === 'accepted') {
                statusBgColor = '#cfe2ff';
                statusTextColor = '#084298';
            } else if (st === 'completed') {
                statusBgColor = '#d1e7dd';
                statusTextColor = '#0f5132';
            } else if (st === 'cancelled') {
                statusBgColor = '#f8d7da';
                statusTextColor = '#842029';
            }

            let actionButtons = '';
            if (st === 'pending') {
                actionButtons = `
                    <button class="btn btn-sm btn-success" onclick="acceptAndRedirect(${apt.id}, ${apt.patient_id})">Accept</button>
                    <button class="btn btn-sm" onclick="openRescheduleModal(${apt.id})">Reschedule</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(${apt.id})">Cancel</button>
                `;
            } else if (st === 'accepted') {
                actionButtons = `
                    <button class="btn btn-sm btn-success" onclick="openCompleteModal(${apt.id})">Complete & Record</button>
                    <button class="btn btn-sm" onclick="openRescheduleModal(${apt.id})">Reschedule</button>
                    <button class="btn btn-sm btn-danger" onclick="cancelAppt(${apt.id})">Cancel</button>
                `;
            } else if (st === 'completed') {
                actionButtons = '<span style="color:#0f5132; font-weight:600;">✓ Completed</span>';
            } else if (st === 'cancelled') {
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
        if (slotValue) {
            try {
                const [start, end] = slotValue.split('|');
                start_at = start;
                end_at = end;
            } catch (e) {
                alert('Invalid slot selection');
                return;
            }
        }
        // If no slot selected, continue without time slot (optional)
    }

    const messageBox = document.getElementById('bookFormMessage');
    messageBox.innerHTML = '';

    // If no time slot provided, use default time or mark as unscheduled
    if (!start_at || !end_at) {
        // Create unscheduled appointment with placeholder time (timezone-aware)
        const dateInput = document.getElementById('datePicker');
        const selectedDate = dateInput ? dateInput.value : new Date().toISOString().split('T')[0];
        const localStart = new Date(selectedDate + 'T09:00:00');
        const localEnd = new Date(localStart.getTime() + 60 * 60 * 1000);
        start_at = localStart.toISOString();
        end_at = localEnd.toISOString();
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
        renderMonthView(currentDate);
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

// Helper to format API error responses into readable strings
function formatApiError(resp) {
    if (!resp) return null;
    if (resp.error) {
        if (typeof resp.error === 'object') {
            try {
                if (Array.isArray(resp.error)) return resp.error.join('\n');
                const values = Object.values(resp.error).flat();
                return values.join('\n') || JSON.stringify(resp.error);
            } catch (ex) {
                return JSON.stringify(resp.error);
            }
        }
        return String(resp.error);
    }
    if (resp.message) return String(resp.message);
    try { return JSON.stringify(resp); } catch (ex) { return String(resp); }
}

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
        renderMonthView(currentDate);
        renderListView(currentDate);
    } else {
        alert(formatApiError(j) || 'Reschedule failed');
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
        // Open the Visit Modal to record the visit
        openCompleteModal(aptId);
        renderMonthView(currentDate);
        renderListView(currentDate);
    } else {
        alert(formatApiError(j) || 'Failed to accept appointment');
    }
}

// Accept appointment and redirect to patient's profile, opening the Visit modal there
async function acceptAndRedirect(aptId, patientId) {
    if (!confirm('Accept this appointment and open patient profile?')) return;
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/accept', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    });
    let j = null;
    try { j = await res.json(); } catch (e) { j = null; }
    if (j && (j.success || res.ok)) {
        // Redirect to patient profile and let patient-profile page open Visit modal for this appointment
        window.location.href = '<?php echo baseUrl(); ?>/?page=patient-profile&id=' + encodeURIComponent(patientId) + '&accepted_apt=' + encodeURIComponent(aptId);
    } else {
        alert(formatApiError(j) || 'Failed to accept appointment');
    }
}

document.getElementById('completeForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const aptId = document.getElementById('completeId').value;
    const chiefComplaint = document.getElementById('completeChiefComplaint').value;
    const ent_type = document.getElementById('completeEntType').value;
    const diagnosis = document.getElementById('completeDiagnosis').value;
    const treatment = document.getElementById('completeTreatment').value;
    const plan = document.getElementById('completePlan').value;
    const prescription = document.getElementById('completePrescription').value;
    const notes = document.getElementById('completeNotes').value;

    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            chief_complaint: chiefComplaint,
            ent_type, 
            diagnosis, 
            treatment, 
            plan,
            prescription,
            prescription_items: [], 
            notes 
        })
    });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment completed and visit record created!');
        closeCompleteModal();
        renderMonthView(currentDate);
        renderListView(currentDate);
    } else {
        alert(formatApiError(j) || 'Failed to complete appointment');
    }
});

async function cancelAppt(aptId) {
    if (!confirm('Cancel this appointment?')) return;
    const res = await fetch('<?php echo baseUrl(); ?>/api.php?route=/api/appointments/' + aptId + '/cancel', { method: 'POST' });
    const j = await res.json();
    if (j.success || res.ok) {
        alert('Appointment cancelled');
        renderMonthView(currentDate);
        renderListView(currentDate);
    }
}

// Date navigation - Load both views when date changes
// Date navigation - switch months and reload views
document.getElementById('datePicker').addEventListener('change', function() {
    currentDate = this.value;
    // ensure datePicker reflects first of month for month navigation
    const p = currentDate.split('-');
    const d = new Date(p[0], parseInt(p[1],10)-1, 1);
    currentDate = d.toISOString().slice(0,10);
    document.getElementById('datePicker').value = currentDate;
    renderMonthView(currentDate);
    renderListView(currentDate);
});

document.getElementById('todayBtn').addEventListener('click', function() {
    const today = new Date();
    const first = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().slice(0,10);
    document.getElementById('datePicker').value = first;
    currentDate = first;
    renderMonthView(currentDate);
    renderListView(currentDate);
});

document.getElementById('prevDayBtn').addEventListener('click', function() {
    const d = new Date(currentDate);
    d.setDate(1);
    d.setMonth(d.getMonth() - 1);
    const prev = d.toISOString().slice(0,10);
    document.getElementById('datePicker').value = prev;
    currentDate = prev;
    renderMonthView(currentDate);
    renderListView(currentDate);
});

document.getElementById('nextDayBtn').addEventListener('click', function() {
    const d = new Date(currentDate);
    d.setDate(1);
    d.setMonth(d.getMonth() + 1);
    const next = d.toISOString().slice(0,10);
    document.getElementById('datePicker').value = next;
    currentDate = next;
    renderMonthView(currentDate);
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
    // Time slot is now optional, only patient is required
    if (submitBtn) {
        if (patient) submitBtn.disabled = false; else submitBtn.disabled = true;
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
// Load both views on page load
    renderMonthView(currentDate);
renderListView(currentDate);
</script>