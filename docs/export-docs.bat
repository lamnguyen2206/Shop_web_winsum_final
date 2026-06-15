@echo off
chcp 65001 >nul
cd /d "%~dp0.."
echo ========================================
echo  Xuat tai lieu do an - Winsum Home
echo ========================================
echo.

echo [1/4] Bao cao kiem thu (HTML, MD, Excel, PDF, Test case xlsx)...
"C:\xampp\php\php.exe" docs\testing\generate-test-report.php
if errorlevel 1 echo CANH BAO: generate-test-report co loi.
echo.

echo [2/4] Mo ta CSDL (Word)...
python docs\database\generate-database-doc-word.py
if errorlevel 1 echo CANH BAO: generate-database-doc-word co loi.
echo.

echo [3/4] Test case template (xlsx module rieng)...
python scripts\generate-filled-test-xlsx.py
if errorlevel 1 echo CANH BAO: generate-filled-test-xlsx co loi.
echo.

echo [4/4] Rui ro trien khai (Word)...
python docs\generate-ru-ro-doc-word.py
if errorlevel 1 echo CANH BAO: generate-ru-ro-doc-word co loi.
echo.

echo ========================================
echo  File da xuat:
echo ========================================
echo   docs\testing\bao-cao-kiem-thu.html
echo   docs\testing\bao-cao-kiem-thu.xlsx
echo   docs\testing\bao-cao-kiem-thu.pdf   (neu co Chrome/Edge)
echo   docs\testing\TEST-CASES.md
echo   docs\database\Mo-ta-CSDL-Winsum-Home.docx
echo   docs\Winsum-Test-Case-Template-Filled.xlsx
echo   docs\Ru-ro-trien-khai-Winsum-Home.docx
echo   docs\ru-ro-trien-khai.md
echo   Winsum-Test-Case-Template-Filled (1).xlsx  (thu muc goc)
echo ========================================
pause
