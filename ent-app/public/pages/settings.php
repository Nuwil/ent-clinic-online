<?php
/**
 * Settings Page - Access restricted to Admin role only
 */
requireRole('admin');
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../../config/Database.php';

$currentUser = getCurrentUser();

?>

<div class="settings-page">
    <div class="mb-3">
        <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Settings</h2>
        <p class="text-muted" style="margin-top: 0.5rem;">Manage data and application configuration</p>
    </div>

    <?php if ($currentUser && $currentUser['role'] === 'admin'): ?>
    <div class="card mb-3">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
            <h3 class="card-title"><i class="fas fa-users-cog"></i> Account Management</h3>
            <div style="width: 14rem;">
                    <button class="btn btn-primary btn-lg" id="openUserModal"><i class="fas fa-user-plus"></i> Create New User</button>
            </div>
        </div>
        <div class="card-body">
            <?php
                $db = Database::getInstance();
                $users = $db->fetchAll('SELECT id, username, email, full_name, role, is_active, created_at FROM users ORDER BY created_at DESC');
            ?>
            <div style="display:flex;gap:2rem;align-items:flex-start;">
                <!-- <div style="flex: 0.5;">
                    <button class="btn btn-primary btn-lg" id="openUserModal"><i class="fas fa-user-plus"></i> Create New User</button>
                </div> -->
                <div style="flex:2;">
                    <h4>Existing Accounts</h4>
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Active</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td><?php echo e($u['username']); ?></td>
                                    <td><?php echo e($u['full_name']); ?></td>
                                    <td><?php echo e($u['email']); ?></td>
                                    <td><?php echo e($u['role']); ?></td>
                                    <td><?php echo $u['is_active'] ? 'Yes' : 'No'; ?></td>
                                    <td><?php echo e($u['created_at']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo baseUrl(); ?>/" style="display:inline-block;">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="id" value="<?php echo $u['id']; ?>">
                                            <button class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?');">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- User Management Modal -->
    <div class="modal" id="userModal" hidden>
        <div class="modal-backdrop"></div>
        <div class="modal-dialog form-modal">
            <div class="modal-header">
                <h2 class="modal-title">Create New User</h2>
                <button type="button" class="modal-close" id="closeUserModal" aria-label="Close modal">&times;</button>
            </div>
            <form id="userForm" method="POST" action="<?php echo baseUrl(); ?>/">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_user">
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <select name="role" class="form-control">
                            <option value="admin">Admin</option>
                            <option value="doctor">Doctor</option>
                            <option value="staff">Secretary</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password (optional)</label>
                        <input type="password" name="password" class="form-control" />
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="cancelUserBtn">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-lg">Create User</button>
                </div>
            </form>
        </div>
        </div>

    <div class="grid grid-2">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-database"></i>
                    Data Management
                </h3>
            </div>
            <div class="grid grid-2">
                <div>
                    <p class="text-muted mb-1">Create a JSON backup of all patients and visits.</p>
                    <a href="<?php echo baseUrl(); ?>/data-tools.php?action=export" class="btn btn-primary">
                        <i class="fas fa-download"></i>
                        Export JSON
                    </a>
                </div>
                <div>
                    <p class="text-muted mb-1">Restore data from a JSON file.</p>
                    <form method="post" action="<?php echo baseUrl(); ?>/data-tools.php?action=import" enctype="multipart/form-data" class="flex gap-1">
                        <input type="file" name="import_file" accept=".json" class="form-control" required>
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-upload"></i>
                            Import JSON
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const userModal = document.getElementById('userModal');
    const openUserModalBtn = document.getElementById('openUserModal');
    const closeUserModalBtn = document.getElementById('closeUserModal');
    const cancelUserBtn = document.getElementById('cancelUserBtn');
    const userForm = document.getElementById('userForm');

    function setupFocusTrap(el) {
        const focusable = Array.from(el.querySelectorAll('a[href], button:not([disabled]), textarea, input:not([type="hidden"]), select'));
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const handler = function(e) {
            if (e.key !== 'Tab') return;
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault(); last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            }
        };
        el._focusTrap = handler;
        document.addEventListener('keydown', handler);
    }

    function removeFocusTrap(el) {
        if (el._focusTrap) {
            document.removeEventListener('keydown', el._focusTrap);
            delete el._focusTrap;
        }
    }

    function openUserModal() {
        userModal.removeAttribute('hidden');
        userModal.classList.add('open');
        document.body.style.overflow = 'hidden';
        document.body.classList.add('modal-open');
        const main = document.querySelector('.main-content');
        if (main) main.setAttribute('aria-hidden', 'true');
        setupFocusTrap(userModal);
        userForm.reset();
        userForm.querySelector('input[name="username"]').focus();
    }

    function closeUserModal() {
        userModal.classList.remove('open');
        userModal.setAttribute('hidden', '');
        document.body.style.overflow = '';
        document.body.classList.remove('modal-open');
        const main = document.querySelector('.main-content');
        if (main) main.removeAttribute('aria-hidden');
        removeFocusTrap(userModal);
    }

    openUserModalBtn.addEventListener('click', openUserModal);
    closeUserModalBtn.addEventListener('click', closeUserModal);
    cancelUserBtn.addEventListener('click', closeUserModal);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && userModal.classList.contains('open')) {
            closeUserModal();
        }
    });

    userForm.addEventListener('submit', function(e) {
        openUserModalBtn.disabled = true;
        openUserModalBtn.textContent = 'Creating...';
    });
</script>
