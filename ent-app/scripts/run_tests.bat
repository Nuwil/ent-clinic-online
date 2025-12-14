@echo off
REM Run migrations (dry run) and tests
SET baseUrl=http://localhost/ent-clinic-online/ent-app/public
php run_migrations.php --dry-run
php run_migrations.php
for %%f in (tests\*.php) do (
	echo Running %%f
	php %%f "%baseUrl%"
	if errorlevel 1 (
		echo Test failed: %%f
		exit /b 1
	)
)
echo All tests passed.
pause
