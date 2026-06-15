@echo off
cd /d "%~dp0.."
echo Sinh bao cao kiem thu: HTML, Markdown, Excel, PDF, Template truong...
"C:\xampp\php\php.exe" docs\generate-test-report.php
echo.
echo File ket qua:
echo   docs\Winsum-Test-Case-Template-Filled.xlsx
echo   docs\bao-cao-kiem-thu.pdf
echo   docs\bao-cao-kiem-thu.xlsx
echo   docs\bao-cao-kiem-thu.html
pause
