@echo off
title CollagenDirect - Local Test Site (live codebase)
echo ==================================================
echo   CollagenDirect - local test site
echo   (running the live codebase: collagendirect-current)
echo ==================================================
echo.

REM --- Database connection (production-matched PostgreSQL 18 on port 5433) ---
set DB_HOST=127.0.0.1
set DB_NAME=collagen_db
set DB_USER=postgres
set DB_PASS=postgres
set DB_PORT=5433

REM --- Make sure PostgreSQL 18 is running (it normally auto-starts) ---
sc query postgresql-x64-18 | find "RUNNING" >nul
if errorlevel 1 (
  echo   PostgreSQL 18 is NOT running. Trying to start it...
  net start postgresql-x64-18 >nul 2>&1
  sc query postgresql-x64-18 | find "RUNNING" >nul
  if errorlevel 1 (
    echo   Could not start it automatically. Open "Services", start
    echo   "postgresql-x64-18", then run this file again.
    echo.
    pause
    exit /b 1
  )
)
echo   PostgreSQL 18: running.

REM --- Ensure PHP is on PATH (winget install location as fallback) ---
where php >nul 2>&1 || set "PATH=%PATH%;%LOCALAPPDATA%\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe"

cd /d "C:\Users\randy\collagendirect-current"

echo.
echo   Site:  http://localhost:8000/login
echo   Login: test@local.dev  /  test1234
echo.
echo   A browser tab will open shortly.
echo   Leave this window open while you work; CLOSE it to stop the server.
echo ==================================================
echo.

REM Open the browser a couple seconds after the server starts
start "" /min cmd /c "timeout /t 2 >nul & start "" http://localhost:8000/login"

php -S localhost:8000 _local_router.php
