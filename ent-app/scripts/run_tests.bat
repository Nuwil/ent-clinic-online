@echo off
REM Run migrations (dry run) and tests
SET baseUrl=http://localhost/ent-clinic-online/ent-app/public
php run_migrations.php --dry-run
php run_migrations.php
php tests/test_rbac.php
php tests/test_rbac_accept.php
php tests/test_rbac_complete.php
php tests/test_ui_role_flag.php "%baseUrl%"
php tests/test_integration_chief_complaint.php "%baseUrl%" 1
php tests/test_appointments_and_visits.php "%baseUrl%" 1
php tests/test_analytics_api.php "%baseUrl%"
php tests/test_analytics_ui_access.php "%baseUrl%"
php tests/test_analytics_trend_db.php "%baseUrl%"
pause
