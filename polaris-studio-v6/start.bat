@echo off
cd /d "%~dp0"
set POLARIS_HOST=127.0.0.1
where py >nul 2>&1 && py -3 start.py && goto :eof
python start.py
