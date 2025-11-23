#!/bin/bash
# Quick start script - runs PHP server from WSL with all extensions available

echo "╔════════════════════════════════════════════════════════╗"
echo "║     GUVI App - Starting Server from WSL                ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Set environment variables for local WSL connections
export MYSQL_HOST="127.0.0.1"
export MYSQL_PORT="3306"
export MYSQL_USER="guvi"
export MYSQL_PASSWORD="Guvi@2024"
export MYSQL_DB="guvi_app"

export REDIS_HOST="127.0.0.1"
export REDIS_PORT="6379"
export REDIS_DB="0"
export SESSION_TTL="604800"

export MONGO_URI="mongodb://127.0.0.1:27017"
export MONGO_DB="guvi_app"
export MONGO_COLLECTION="profiles"

echo "✓ Environment variables configured"
echo ""
echo "Database connections:"
echo "  MySQL    : $MYSQL_HOST:$MYSQL_PORT ($MYSQL_DB)"
echo "  Redis    : $REDIS_HOST:$REDIS_PORT"
echo "  MongoDB  : $MONGO_URI"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Server will be available at:"
echo "  • From WSL: http://localhost:8000"
echo "  • From Windows: http://$(hostname -I | awk '{print $1}'):8000"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Start PHP server on all interfaces so Windows can access it
# Use router `index.php` so app routing works correctly with built-in server
php -S 0.0.0.0:8000 index.php
