<?php
// Session is already started by index.php; user should be authenticated at this point
if (session_status() === PHP_SESSION_NONE) session_start();
$currentUser = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENT Clinic Online - <?php echo ucfirst(getCurrentPage()); ?></title>
    <link rel="stylesheet" href="<?php echo baseUrl(); ?>/assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <i class="fas fa-hospital-alt"></i>
                    <h1 class="logo-text">ENT Clinic</h1>
                </div>
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
            
            <nav class="sidebar-nav">
                <?php
                $currentUser = getCurrentUser();
                $userRole = getCurrentUserRole();
                $currentPage = getCurrentPage();
                
                // Show role-specific dashboard link
                $dashboardPage = getDashboardForRole($userRole);
                $dashboardLabel = getRoleDisplayName($userRole) . ' Dashboard';
                $dashboardIcon = $userRole === 'admin' ? 'fa-tachometer-alt' : ($userRole === 'doctor' ? 'fa-user-md' : 'fa-clipboard-list');
                ?>
                <a href="<?php echo baseUrl(); ?>/?page=<?php echo $dashboardPage; ?>" class="nav-item <?php echo $currentPage === $dashboardPage ? 'active' : ''; ?>">
                    <i class="fas <?php echo $dashboardIcon; ?>"></i>
                    <span><?php echo $dashboardLabel; ?></span>
                </a>
                
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="nav-item <?php echo $currentPage === 'patients' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                </a>
                
                <?php if (hasRole(['admin', 'doctor'])): ?>
                <a href="<?php echo baseUrl(); ?>/?page=analytics" class="nav-item <?php echo $currentPage === 'analytics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <?php endif; ?>
                
                <?php if (hasRole('admin')): ?>
                <a href="<?php echo baseUrl(); ?>/?page=settings" class="nav-item <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
                <?php endif; ?>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="sidebarUserAvatar" style="cursor:pointer;position:relative;display:flex;align-items:center;justify-content:center;width:40px;height:40px;background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);border-radius:8px;color:white;font-size:1.25rem;">
                        <i class="fas <?php echo getRoleIcon($currentUser['role'] ?? 'staff'); ?>"></i>
                        <div id="userDropdown" style="display:none;position:absolute;right:0;top:56px;background:#fff;border:1px solid var(--border);box-shadow:var(--shadow);border-radius:8px;min-width:200px;z-index:50;">
                            <div style="padding:12px;border-bottom:1px solid var(--border);">
                                <strong class="user-name" style="display:block;"><?php echo e($currentUser['full_name'] ?? 'User'); ?></strong>
                                <small class="text-muted" style="display:block;margin-top:4px;">
                                    <i class="fas <?php echo getRoleIcon($currentUser['role'] ?? 'staff'); ?>" style="margin-right:4px;"></i><?php echo getRoleDisplayName($currentUser['role'] ?? 'staff'); ?>
                                </small>
                            </div>
                            <div style="padding:8px;">
                                <form method="POST" action="<?php echo baseUrl(); ?>/">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit" class="btn btn-outline" style="width:100%;text-align:left;padding:8px 12px;border:1px solid #ddd;background:#f8f9fa;border-radius:6px;cursor:pointer;font-size:0.875rem;">
                                        <i class="fas fa-sign-out-alt" style="margin-right:8px;"></i> Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="user-details" style="flex:1;min-width:0;">
                        <span class="user-name" style="display:block;font-weight:600;color:#333;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?php echo e($currentUser['full_name'] ?? 'User'); ?></span>
                        <span class="user-role" style="display:block;font-size:0.75rem;color:#666;margin-top:2px;"><i class="fas <?php echo getRoleIcon($currentUser['role'] ?? 'staff'); ?>" style="margin-right:4px;"></i><?php echo getRoleDisplayName($currentUser['role'] ?? 'staff'); ?></span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu" style="display:none;">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="page-title"><?php 
                    $pageTitle = getCurrentPage();
                    if ($pageTitle === 'patient-profile') {
                        echo 'Patient Profile';
                    } else {
                        echo ucfirst($pageTitle);
                    }
                    ?></h2>
                </div>
                <div class="topbar-right">
                    <div class="topbar-actions">
                        <button class="action-btn" title="Search" style="display:none;">
                            <i class="fas fa-search"></i>
                        </button>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="action" value="logout">
                            <button type="submit" class="action-btn" title="Logout" style="color:#dc3545;">
                                <i class="fas fa-sign-out-alt"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="content-area" style="padding: 20px; margin: 0 10rem 0 10rem;">


