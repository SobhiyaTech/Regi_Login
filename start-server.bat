@echo off
echo ============================================
echo GUVI Login/Register App - Starting Server
echo ============================================
echo.
echo Configuring WSL Database Connections...
echo.

REM MySQL Configuration (WSL)
set MYSQL_HOST=172.28.244.138
set MYSQL_PORT=3306
set MYSQL_USER=guvi
set MYSQL_PASSWORD=Guvi@2024
set MYSQL_DB=guvi_app

REM Redis Configuration (WSL)
set REDIS_HOST=172.28.244.138
set REDIS_PORT=6379
set REDIS_DB=0
set SESSION_TTL=604800

REM MongoDB Configuration (WSL)
set MONGO_URI=mongodb://172.28.244.138:27017
set MONGO_DB=guvi_app
set MONGO_COLLECTION=profiles

echo MySQL  : %MYSQL_HOST%:%MYSQL_PORT% (Database: %MYSQL_DB%)
echo Redis  : %REDIS_HOST%:%REDIS_PORT% (Session TTL: %SESSION_TTL%s)
echo MongoDB: %MONGO_URI% (Collection: %MONGO_DB%.%MONGO_COLLECTION%)
echo.
echo Starting PHP Development Server on http://localhost:8000
echo ============================================
echo.

php -S localhost:8000
