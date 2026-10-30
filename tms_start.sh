#!/bin/bash

# Target directory
PROJECT_DIR="/opt/lampp/htdocs/TMS_ERP"

# Check if target directory exists and navigate into it
if [ -d "$PROJECT_DIR" ]; then
    cd "$PROJECT_DIR" || exit 1
    echo "Navigated to $PROJECT_DIR"
else
    echo "Error: Directory $PROJECT_DIR does not exist."
    exit 1
fi

echo "Checking for services occupying ports 80, 443, and 3306..."

# 1. Stop conflicting systemd services if active
systemctl is-active --quiet apache2 && echo "Stopping system apache2..." && sudo systemctl stop apache2
systemctl is-active --quiet mysql && echo "Stopping system mysql..." && sudo systemctl stop mysql
systemctl is-active --quiet mariadb && echo "Stopping system mariadb..." && sudo systemctl stop mariadb

# 2. Kill any remaining processes occupying XAMPP ports (80, 443, 3306)
# Skip processes already running from /opt/lampp/
PORTS=(80 443 3306)

for PORT in "${PORTS[@]}"; do
    # Find PIDs on port using fuser
    PIDS=$(sudo fuser $PORT/tcp 2>/dev/null)
    
    if [ -n "$PIDS" ]; then
        for PID in $PIDS; do
            # Check if PID belongs to non-XAMPP process
            CMD=$(ps -p $PID -o comm= 2>/dev/null)
            EXE=$(readlink -f /proc/$PID/exe 2>/dev/null)
            
            if [[ "$EXE" != /opt/lampp/* ]]; then
                echo "Port $PORT is occupied by $CMD (PID: $PID). Terminating process..."
                sudo kill -9 $PID 2>/dev/null
            fi
        done
    fi
done

echo "Starting XAMPP (Apache & MySQL)..."
sudo /opt/lampp/lampp start

# Check status
echo "---------------------------------"
sudo /opt/lampp/lampp status
