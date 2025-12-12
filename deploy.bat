@echo off
setlocal
chcp 65001 > nul

echo ========================================
echo   PTGates Git Automation (Deploy)
echo ========================================

:: 1. Check Status
echo.
echo [Checking Status...]
git status
echo.

:: 2. Add All Changes
echo [Adding Files...]
git add .
if %errorlevel% neq 0 (
    echo [Error] Failed to add files.
    pause
    exit /b %errorlevel%
)

:: 3. Prompt for Commit Message
set /p "msg=Enter commit message (Press Enter for Auto Update): "
if "%msg%"=="" (
    set "msg=Auto Update %date% %time%"
)

:: 4. Commit
echo.
echo [Committing...]
git commit -m "%msg%"
if %errorlevel% neq 0 (
    echo [Warning] Nothing to commit or error. continuing to push...
)

:: 5. Push to Main
echo.
echo [Pushing to Origin Main...]
git push origin main
if %errorlevel% neq 0 (
    echo [Error] Push failed. Please check your network or conflict status.
    pause
    exit /b %errorlevel%
)

echo.
echo [Success] All changes have been pushed to remote.
echo ========================================
pause
