@echo off
set "LMS_ROOT=C:\AppServ\www\lms"
set "PYTHON=%LMS_ROOT%\.venv\Scripts\python.exe"
if not exist "%PYTHON%" (
    echo Python environment not found: %PYTHON%
    exit /b 1
)
start "LMS ML Risk Service" /min "%PYTHON%" "%LMS_ROOT%\ml\service.py"
echo ML Risk Service started at http://127.0.0.1:8091
