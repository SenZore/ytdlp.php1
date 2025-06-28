@echo off
echo Starting YT-DLP PHP Web Interface...
echo.

REM Set PHP path
set PHP_PATH=C:\php-8.4.8-nts-Win32-vs17-x64\php.exe

REM Check if PHP exists
if not exist "%PHP_PATH%" (
    echo Error: PHP not found at %PHP_PATH%
    echo Please update the PHP_PATH in this file to match your installation
    pause
    exit /b 1
)

REM Create necessary directories
if not exist "downloads" mkdir downloads
if not exist "temp" mkdir temp
if not exist "logs" mkdir logs

echo PHP found at: %PHP_PATH%
echo Starting PHP development server...
echo.
echo Access the web interface at: http://localhost:8000
echo Press Ctrl+C to stop the server
echo.

REM Start PHP development server
"%PHP_PATH%" -S localhost:8000

pause 