#!/bin/bash
# Docker entrypoint script for WordPress plugin development
# This script handles the setup of WordPress, including database connection,
# core installation, configuration, and plugin activation.
# Optimized for development with AI Post Scheduler plugin.

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

echo "============================================================"
echo "  WordPress Plugin Development Environment"
echo "  AI Post Scheduler"
echo "============================================================"

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
  
  # Disable fatal error handler for better debugging
  wp config set WP_DISABLE_FATAL_ERROR_HANDLER true --raw --type=constant --path=/var/www/html --allow-root

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
# AI Post Scheduler Plugin Installation
#============================================================

# Ensure the plugins directory exists.
mkdir -p /var/www/html/wp-content/plugins

PLUGIN_SLUG="ai-post-scheduler"

# Check if plugin is mounted as a volume (development mode)
if [ -d "/var/www/html/wp-content/plugins/${PLUGIN_SLUG}" ]; then
  echo "[entrypoint] Plugin ${PLUGIN_SLUG} found (mounted volume for development)."
  # Set appropriate ownership for the mounted plugin
  chown -R www-data:www-data /var/www/html/wp-content/plugins/${PLUGIN_SLUG}
# Otherwise, copy from image if available and not already copied
elif [ -d "/plugin-src/${PLUGIN_SLUG}" ]; then
  echo "[entrypoint] Copying plugin ${PLUGIN_SLUG} from image into WordPress plugins..."
  cp -R /plugin-src/"${PLUGIN_SLUG}" /var/www/html/wp-content/plugins/
  chown -R www-data:www-data /var/www/html/wp-content/plugins/${PLUGIN_SLUG}
else
  echo "[entrypoint] WARNING: Plugin ${PLUGIN_SLUG} not found in /plugin-src or mounted volume!"
fi

# Set appropriate ownership for WordPress files. www-data is the user Apache runs as.
chown -R www-data:www-data /var/www/html

# Check if the plugin is active and activate if needed.
if wp plugin is-active "${PLUGIN_SLUG}" --path=/var/www/html --allow-root 2>/dev/null; then
  echo "[entrypoint] Plugin ${PLUGIN_SLUG} is already active."
else
  echo "[entrypoint] Activating plugin ${PLUGIN_SLUG}..."
  wp plugin activate "${PLUGIN_SLUG}" --path=/var/www/html --allow-root || {
    echo "[entrypoint] WARNING: Failed to activate ${PLUGIN_SLUG}. Check plugin compatibility."
  }
fi

# Install and activate Meow Apps AI Engine if not present (required dependency)
echo "[entrypoint] Checking for Meow Apps AI Engine plugin..."
if ! wp plugin is-installed ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
  echo "[entrypoint] Installing Meow Apps AI Engine (required dependency)..."
  # Note: This is a placeholder. The actual plugin might need manual installation or API key
  # wp plugin install ai-engine --activate --path=/var/www/html --allow-root || {
  echo "[entrypoint] NOTE: Meow Apps AI Engine is required for this plugin to function."
  echo "[entrypoint] Please install it manually from WordPress admin or via WP-CLI."
  # }
else
  echo "[entrypoint] Meow Apps AI Engine is already installed."
  if ! wp plugin is-active ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
    echo "[entrypoint] Activating AI Engine..."
    wp plugin activate ai-engine --path=/var/www/html --allow-root 2>/dev/null || true
  fi
fi

#============================================================
# Debug environment
#============================================================

if [ "${ENTRYPOINT_DEBUG}" = "1" ]; then
  echo ""
  echo "============================================================"
  echo "  Debug Information"
  echo "============================================================"

  echo "---- Apache Configuration Test ----"
  apache2ctl configtest || true

  echo ""
  echo "---- WordPress Installation Info ----"
  if [ -f /var/www/html/wp-config.php ]; then
    wp core version --path=/var/www/html --allow-root || true
    echo ""
    echo "Site URL: ${WP_SITE_URL}"
    echo "Admin User: ${WP_ADMIN_USER}"
    echo "Admin Password: ${WP_ADMIN_PASSWORD}"
  fi

  echo ""
  echo "---- Installed Plugins ----"
  wp plugin list --path=/var/www/html --allow-root || true

  echo ""
  echo "---- Xdebug Status ----"
  php -v | grep -i xdebug || echo "Xdebug not detected"
  echo ""
  echo "Xdebug configuration:"
  php -i | grep -i "xdebug.mode\|xdebug.client_host\|xdebug.client_port\|xdebug.start_with_request" || true

  echo ""
  echo "---- PHP Info ----"
  php -i | grep -i "memory_limit\|upload_max_filesize\|post_max_size\|max_execution_time" || true

  echo ""
  echo "============================================================"
  echo "  Development environment ready!"
  echo "  WordPress: ${WP_SITE_URL}"
  echo "  phpMyAdmin: http://localhost:8082"
  echo "  Xdebug: Listening on port 9003"
  echo "============================================================"
  echo ""

  # Tail Apache logs for real-time monitoring in background
  if [ -f /var/log/apache2/error.log ]; then
    tail -n +1 -F /var/log/apache2/error.log &
  fi
fi

# --- Start Apache ---
# Execute the original Docker entrypoint to start Apache in the foreground.
# This replaces the current shell with the Apache process.
exec docker-entrypoint.sh apache2-foreground
