<?php
// Settings page - only Data Management section
?>

<div class="settings-page">
    <div class="mb-3">
        <h2 style="margin: 0; font-size: 1.75rem; font-weight: 700;">Settings</h2>
        <p class="text-muted" style="margin-top: 0.5rem;">Manage data and application configuration</p>
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
