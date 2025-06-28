@echo off
echo YT-DLP Setup for PHP Web Interface
echo ===================================
echo.

REM Check if yt-dlp.exe exists in current directory
if exist "yt-dlp.exe" (
    echo Found yt-dlp.exe in current directory!
    echo.
    echo Option 1: Use yt-dlp.exe from current directory
    echo Option 2: Copy yt-dlp.exe to a dedicated folder
    echo Option 3: Use yt-dlp.exe from PATH (if installed)
    echo.
    set /p choice="Choose option (1-3): "
    
    if "%choice%"=="1" (
        echo Using yt-dlp.exe from current directory...
        echo Update config.php: define('YT_DLP_PATH', 'yt-dlp.exe');
        goto :end
    ) else if "%choice%"=="2" (
        if not exist "tools" mkdir tools
        copy "yt-dlp.exe" "tools\yt-dlp.exe"
        echo Copied yt-dlp.exe to tools\ folder
        echo Update config.php: define('YT_DLP_PATH', 'tools\\yt-dlp.exe');
        goto :end
    ) else if "%choice%"=="3" (
        echo Using yt-dlp.exe from PATH...
        echo Update config.php: define('YT_DLP_PATH', 'yt-dlp.exe');
        goto :end
    )
) else (
    echo yt-dlp.exe not found in current directory.
    echo.
    echo Please download yt-dlp.exe from:
    echo https://github.com/yt-dlp/yt-dlp/releases/latest
    echo.
    echo Then place it in one of these locations:
    echo 1. Current directory (same as this script)
    echo 2. Create a 'tools' folder and place it there
    echo 3. Add it to your system PATH
    echo.
    echo After placing yt-dlp.exe, run this script again.
    goto :end
)

:end
echo.
echo Configuration steps:
echo 1. Open config.php in a text editor
echo 2. Find the line: define('YT_DLP_PATH', 'yt-dlp.exe');
echo 3. Update the path to match your yt-dlp.exe location
echo 4. Save the file
echo.
echo Example paths:
echo - define('YT_DLP_PATH', 'yt-dlp.exe');                    (current directory)
echo - define('YT_DLP_PATH', 'tools\\yt-dlp.exe');            (tools folder)
echo - define('YT_DLP_PATH', 'C:\\path\\to\\yt-dlp.exe');     (full path)
echo.
echo Test your setup by running: start-local.bat
echo.
pause 