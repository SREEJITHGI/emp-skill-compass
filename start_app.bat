@echo off
title Employee Skill Compass (ESTS)
echo ========================================================
echo       Employee Skill Compass (ESTS) - Local Server
echo ========================================================
echo.
echo Launching application at http://localhost:8080/index.php ...
start http://localhost:8080/index.php
echo.
echo PHP Web Server running on http://localhost:8080
echo Press Ctrl+C at any time to stop the server.
echo.
php -S localhost:8080
pause
