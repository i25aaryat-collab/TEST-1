#!/usr/bin/env bash
# ==========================================================================
# start-codespace.sh  —  Owner: M1 (Lead)
# Runs RoomieMatch inside GitHub Codespaces (or any Ubuntu machine):
#   1. installs PHP + MariaDB (MySQL) the first time
#   2. starts the database and loads schema.sql + seed.sql the first time
#   3. starts the website on port 8080
# Usage, in the Codespaces terminal:   bash start-codespace.sh
# ==========================================================================
set -e
cd "$(dirname "$0")"

# 1. Install PHP and MariaDB if missing
if ! command -v php >/dev/null || ! command -v mysqld >/dev/null || ! php -m | grep -qi pdo_mysql; then
    echo "Installing PHP and MySQL (first time only, about 1-2 minutes)..."
    sudo apt-get update -qq
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq php-cli php-mysql php-mbstring php-curl mariadb-server >/dev/null
fi

# 2. Start the database
if ! sudo mysqladmin ping --silent 2>/dev/null; then
    echo "Starting MySQL..."
    sudo mkdir -p /run/mysqld && sudo chown mysql:mysql /run/mysqld
    (sudo service mariadb start >/dev/null 2>&1) || (sudo mysqld_safe >/dev/null 2>&1 &)
    for i in $(seq 1 20); do sudo mysqladmin ping --silent 2>/dev/null && break; sleep 1; done
fi

# Same login as XAMPP: user root, empty password (matches config/config.php)
sudo mysql -e "CREATE USER IF NOT EXISTS 'root'@'127.0.0.1' IDENTIFIED BY '';
               GRANT ALL ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION; FLUSH PRIVILEGES;"

# Load the tables and demo data only if the database doesn't exist yet
if ! sudo mysql -e "USE roommate_match" 2>/dev/null; then
    echo "Creating database and loading demo data..."
    sudo mysql -e "CREATE DATABASE roommate_match CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"
    sudo mysql roommate_match < database/schema.sql
    sudo mysql roommate_match < database/seed.sql
fi

# 3. Start the website (stop an old copy first)
pkill -f "php -S 0.0.0.0:8080" 2>/dev/null || true
nohup php -S 0.0.0.0:8080 -t . > /tmp/roomiematch.log 2>&1 &
sleep 1
echo ""
echo "RoomieMatch is running on port 8080."
echo "Open the PORTS tab below and click the globe icon next to 8080."
echo "Log in with usera@roomiematch.test / Roomie@123"
