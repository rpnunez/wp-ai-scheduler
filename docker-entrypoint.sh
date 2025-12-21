#!/bin/bash
# This script serves as the entrypoint for the Docker container.
# It handles the setup of WordPress, including database connection,
# core installation, configuration, and plugin activation.

# Exit immediately if a command exits with a non-zero status.
set -e

#============================================================
# Environment Variables & Config
#============================================================

# These variables provide default values for WordPress and MySQL configuration.
# They can be overridden by setting environment variables when running the Docker container.
: "${WORDPRESS_DB_HOST:=db:3306}"       # WordPress database host and port
: "${WORDPRESS_DB_NAME:=wordpress}"     # WordPress database name
: "${WORDPRESS_DB_USER:=wordpress}"     # WordPress database user
: "${WORDPRESS_DB_PASSWORD:=wordpress}" # WordPress database password
: "${MYSQL_ROOT_PASSWORD:=root}"        # MySQL root password (used for health check and db creation)
: "${WP_ADMIN_USER:=admin}"             # WordPress admin username
: "${WP_ADMIN_PASSWORD:=admin}"         # WordPress admin password
: "${WP_ADMIN_EMAIL:=admin@example.com}" # WordPress admin email
: "${WP_SITE_TITLE:=WP Site}"           # WordPress site title
: "${WP_SITE_URL:=http://localhost:8080}" # WordPress site URL
: "${ENTRYPOINT_DEBUG:=1}"              # Enable/disable debug output from the entrypoint script

# Extract database host and port from WORDPRESS_DB_HOST.
DB_HOST="$(echo ${WORDPRESS_DB_HOST} | cut -d: -f1)"
DB_PORT="$(echo ${WORDPRESS_DB_HOST} | cut -s -d: -f2)"
DB_PORT="${DB_PORT:-3306}"

#============================================================
# MySQL Health Check
#============================================================
echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."

# Loop to check if MySQL is ready to accept connections.
retry=0

until mysqladmin ping -h"$DB_HOST" -P"$DB_PORT" -u root -p"$MYSQL_ROOT_PASSWORD" --silent; do
  retry=$((retry+1))

  # Timeout after 60 attempts (approx 2 minutes).
  if [ $retry -ge 60 ]; then
    echo "[entrypoint] Timeout waiting for MySQL after $retry tries."
    exit 1
  fi

  echo "[entrypoint] MySQL is unavailable (attempt $retry). Sleeping 2s..."
  sleep 2
done

echo "[entrypoint] MySQL is up."

#============================================================
# WordPress Core Installation
#============================================================
# Check if wp-config.php exists. If not, perform a fresh WordPress installation.
if [ ! -f /var/www/html/wp-config.php ]; then
  echo "[entrypoint] WordPress not found in /var/www/html — downloading..."

  # Download WordPress core files. --allow-root is needed because we are running as root in Docker.
  wp core download --path=/var/www/html --allow-root

  echo "[entrypoint] Creating wp-config.php..."
  # Generate wp-config.php with database credentials.
  # --skip-check avoids connecting to the DB during config creation (we verified it above, but this is safer for config generation).
  wp config create \
    --path=/var/www/html \
    --dbname="$WORDPRESS_DB_NAME" \
    --dbuser="$WORDPRESS_DB_USER" \
    --dbpass="$WORDPRESS_DB_PASSWORD" \
    --dbhost="$WORDPRESS_DB_HOST" \
    --skip-check \
    --allow-root

  # Enable debug mode
  # --raw --type=constant ensures they are written as PHP booleans (true/false) not strings.
  wp config set WP_DEBUG true --raw --type=constant --path=/var/www/html --allow-root
  wp config set WP_DEBUG_LOG true --raw --type=constant --path=/var/www/html --allow-root
  wp config set WP_DEBUG_DISPLAY false --raw --type=constant --path=/var/www/html --allow-root
  wp config set SCRIPT_DEBUG true --raw --type=constant --path=/var/www/html --allow-root

  # Set memory limits
  wp config set WP_MEMORY_LIMIT '512M' --type=constant --path=/var/www/html --allow-root
  wp config set WP_MAX_MEMORY_LIMIT '512M' --type=constant --path=/var/www/html --allow-root

  # Create the WordPress database if it doesn't already exist.
  echo "[entrypoint] Creating database (if not exists)..."

  # || true ensures the script continues even if the DB already exists.
  wp db create --path=/var/www/html --allow-root || true

  # Run the standard WordPress installation process.
  echo "[entrypoint] Installing WordPress core..."

  wp core install \
    --path=/var/www/html \
    --url="$WP_SITE_URL" \
    --title="$WP_SITE_TITLE" \
    --admin_user="$WP_ADMIN_USER" \
    --admin_password="$WP_ADMIN_PASSWORD" \
    --admin_email="$WP_ADMIN_EMAIL" \
    --skip-email \
    --allow-root
else
  echo "[entrypoint] wp-config.php exists; skipping core download/install."
fi

#============================================================
# NunezScheduler Installation
#============================================================

# Ensure the plugins directory exists.
mkdir -p /var/www/html/wp-content/plugins

PLUGIN_SLUG="ai-post-scheduler"

# Check if the plugin source directory exists and if the plugin is not already copied.
if [ -d "/plugin-src/${PLUGIN_SLUG}" ] && [ ! -d "/var/www/html/wp-content/plugins/${PLUGIN_SLUG}" ]; then
  echo "[entrypoint] Copying plugin ${PLUGIN_SLUG} into WordPress plugins..."

  # Copy the plugin from the source volume to the WordPress plugins directory.
  cp -R /plugin-src/"${PLUGIN_SLUG}" /var/www/html/wp-content/plugins/
fi

# Set appropriate ownership for WordPress files. www-data is the user Apache runs as.
chown -R www-data:www-data /var/www/html

# Check if the plugin is active.
if wp plugin is-active "${PLUGIN_SLUG}" --path=/var/www/html --allow-root 2>/dev/null; then
  echo "[entrypoint] Plugin ${PLUGIN_SLUG} already active."
else
  echo "[entrypoint] Activating plugin ${PLUGIN_SLUG}..."

  # Activate the plugin. || true prevents failure if activation has issues (though we want it to succeed).
  wp plugin activate "${PLUGIN_SLUG}" --path=/var/www/html --allow-root || true
fi

#============================================================
# Debug environment
#============================================================

if [ "${ENTRYPOINT_DEBUG}" = "1" ]; then
  # --- Debugging Information (if ENTRYPOINT_DEBUG is enabled) ---
  echo "[entrypoint] ENTRYPOINT_DEBUG=1 — printing diagnostics..."

  echo "---- apache2ctl configtest ----"
  apache2ctl configtest || true

  echo "---- /var/www/html listing ----"
  ls -la /var/www/html || true

#  echo "---- wp-config.php head (if present) ----"
#  if [ -f /var/www/html/wp-config.php ]; then
#    sed -n '1,120p' /var/www/html/wp-config.php || true
#  fi

  # Tail Apache logs for real-time monitoring.
  echo "---- tailing Apache error log (foreground) ----"

  if [ -f /var/log/apache2/error.log ]; then
    # Background the tail command so it doesn't block execution.
    tail -n +1 -F /var/log/apache2/error.log &
  fi
fi

# --- Start Apache ---
# Execute the original Docker entrypoint to start Apache in the foreground.
# This replaces the current shell with the Apache process.
exec docker-entrypoint.sh apache2-foreground
