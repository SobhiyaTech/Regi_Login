#!/bin/bash
# WSL Database Services Setup and Verification Script
# This script sets up and verifies all database services for the GUVI app

echo "╔════════════════════════════════════════════════════════╗"
echo "║  GUVI App - WSL Database Services Setup & Verification ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to check if a service is running
check_service() {
    local service=$1
    if systemctl is-active --quiet "$service"; then
        echo -e "${GREEN}✓${NC} $service is running"
        return 0
    else
        echo -e "${RED}✗${NC} $service is NOT running"
        return 1
    fi
}

# Function to check if a port is listening
check_port() {
    local port=$1
    local service=$2
    if ss -tlnp 2>/dev/null | grep -q ":$port "; then
        echo -e "${GREEN}✓${NC} $service listening on port $port"
        return 0
    else
        echo -e "${RED}✗${NC} $service NOT listening on port $port"
        return 1
    fi
}

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "1. Service Status Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check_service mysql
MYSQL_OK=$?

check_service redis-server
REDIS_OK=$?

check_service mongod
MONGO_OK=$?

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "2. Port Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

check_port 3306 "MySQL"
check_port 6379 "Redis"
check_port 27017 "MongoDB"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "3. Network Configuration"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

WSL_IP=$(hostname -I | awk '{print $1}')
echo -e "${GREEN}WSL IP Address:${NC} $WSL_IP"
echo ""
echo -e "${YELLOW}Windows PHP should use this IP to connect:${NC}"
echo "  MYSQL_HOST=$WSL_IP"
echo "  REDIS_HOST=$WSL_IP"
echo "  MONGO_URI=mongodb://$WSL_IP:27017"

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "4. MySQL Database Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $MYSQL_OK -eq 0 ]; then
    # Check if guvi_app database exists
    if sudo mysql -e "USE guvi_app;" 2>/dev/null; then
        echo -e "${GREEN}✓${NC} Database 'guvi_app' exists"
        
        # Check users table
        TABLE_COUNT=$(sudo mysql -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='guvi_app' AND table_name='users';")
        if [ "$TABLE_COUNT" = "1" ]; then
            echo -e "${GREEN}✓${NC} Table 'users' exists"
            USER_COUNT=$(sudo mysql -N guvi_app -e "SELECT COUNT(*) FROM users;")
            echo -e "   Users in database: $USER_COUNT"
        else
            echo -e "${RED}✗${NC} Table 'users' does NOT exist"
            echo -e "${YELLOW}   Run: sudo mysql guvi_app < php/db/schema.sql${NC}"
        fi
        
        # Check guvi user
        GUVI_USER_COUNT=$(sudo mysql -N -e "SELECT COUNT(*) FROM mysql.user WHERE User='guvi';")
        if [ "$GUVI_USER_COUNT" -gt "0" ]; then
            echo -e "${GREEN}✓${NC} MySQL user 'guvi' exists"
            sudo mysql -e "SELECT CONCAT('   - guvi@', Host) as 'User Hosts' FROM mysql.user WHERE User='guvi';"
        else
            echo -e "${RED}✗${NC} MySQL user 'guvi' does NOT exist"
            echo -e "${YELLOW}   Create with:${NC}"
            echo "   sudo mysql -e \"CREATE USER 'guvi'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'Guvi@2024';\""
            echo "   sudo mysql -e \"GRANT ALL ON guvi_app.* TO 'guvi'@'127.0.0.1'; FLUSH PRIVILEGES;\""
        fi
    else
        echo -e "${RED}✗${NC} Database 'guvi_app' does NOT exist"
        echo -e "${YELLOW}   Create with:${NC}"
        echo "   sudo mysql -e 'CREATE DATABASE guvi_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'"
        echo "   sudo mysql guvi_app < php/db/schema.sql"
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "5. Redis Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $REDIS_OK -eq 0 ]; then
    if command -v redis-cli &> /dev/null; then
        REDIS_VERSION=$(redis-cli --version | awk '{print $2}')
        echo -e "${GREEN}✓${NC} Redis CLI available: $REDIS_VERSION"
        
        if redis-cli ping &>/dev/null; then
            echo -e "${GREEN}✓${NC} Redis PING successful"
            
            # Check for session keys
            SESSION_COUNT=$(redis-cli --scan --pattern "session:*" 2>/dev/null | wc -l)
            echo -e "   Active sessions: $SESSION_COUNT"
        else
            echo -e "${RED}✗${NC} Redis PING failed"
        fi
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "6. MongoDB Check"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

if [ $MONGO_OK -eq 0 ]; then
    if command -v mongosh &> /dev/null; then
        echo -e "${GREEN}✓${NC} MongoDB Shell (mongosh) available"
        
        # Check if guvi_app database exists
        DB_EXISTS=$(mongosh --quiet --eval "db.getMongo().getDBNames().includes('guvi_app')" 2>/dev/null)
        if [ "$DB_EXISTS" = "true" ]; then
            echo -e "${GREEN}✓${NC} Database 'guvi_app' exists"
            
            # Check profiles collection
            PROFILE_COUNT=$(mongosh --quiet guvi_app --eval "db.profiles.countDocuments()" 2>/dev/null)
            echo -e "   Profile documents: $PROFILE_COUNT"
            
            # Check indexes
            INDEX_COUNT=$(mongosh --quiet guvi_app --eval "db.profiles.getIndexes().length" 2>/dev/null)
            echo -e "   Indexes configured: $INDEX_COUNT"
        else
            echo -e "${YELLOW}⚠${NC} Database 'guvi_app' not initialized"
            echo -e "${YELLOW}   Run: ./php/db/mongo-init.sh${NC}"
        fi
    fi
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "7. Summary"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

ALL_OK=0
if [ $MYSQL_OK -eq 0 ] && [ $REDIS_OK -eq 0 ] && [ $MONGO_OK -eq 0 ]; then
    echo -e "${GREEN}✅ All services are running!${NC}"
    ALL_OK=1
    echo ""
    echo "Next steps:"
    echo "  1. From Windows: Run start-server.bat"
    echo "  2. Test diagnostics: http://localhost:8000/php/db/diagnostics.php"
    echo "  3. Open app: http://localhost:8000"
else
    echo -e "${RED}❌ Some services are not running${NC}"
    echo ""
    echo "To start services:"
    [ $MYSQL_OK -ne 0 ] && echo "  sudo systemctl start mysql"
    [ $REDIS_OK -ne 0 ] && echo "  sudo systemctl start redis-server"
    [ $MONGO_OK -ne 0 ] && echo "  sudo systemctl start mongod"
fi

echo "════════════════════════════════════════════════════════════"