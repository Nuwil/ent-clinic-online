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
                <a href="<?php echo baseUrl(); ?>/?page=patients" class="nav-item <?php echo getCurrentPage() === 'patients' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                </a>
                <a href="<?php echo baseUrl(); ?>/?page=analytics" class="nav-item <?php echo getCurrentPage() === 'analytics' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i>
                    <span>Analytics</span>
                </a>
                <a href="<?php echo baseUrl(); ?>/?page=settings" class="nav-item <?php echo getCurrentPage() === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="user-details">
                        <span class="user-name">Admin User</span>
                        <span class="user-role">Administrator</span>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Bar -->
            <header class="topbar">
                <div class="topbar-left">
                    <button class="mobile-menu-toggle" id="mobileMenuToggle" aria-label="Toggle menu">
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
                        <button class="action-btn" title="Notifications">
                            <i class="fas fa-bell"></i>
                            <span class="badge">3</span>
                        </button>
                        <button class="action-btn" title="Search">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <main class="content-area">
