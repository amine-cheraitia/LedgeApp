#!/bin/sh
# Ledge — demarrage de la stack Docker de demonstration.
# Application : http://localhost:5173 (initialisation 2 a 5 min au premier lancement)
cd "$(dirname "$0")"
docker compose up --build
