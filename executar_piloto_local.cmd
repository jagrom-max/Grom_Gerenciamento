@echo off
setlocal
powershell -ExecutionPolicy Bypass -File "%~dp0scripts\Start-GromWebPilot.ps1" %*
