@echo off
title Ledge - Demarrage Docker
cd /d "%~dp0"
echo ==============================================
echo   Ledge - demarrage de la stack Docker
echo   Premiere initialisation : ~2 minutes
echo   Application : http://localhost:5173
echo ==============================================
echo.
docker compose up --build
pause
