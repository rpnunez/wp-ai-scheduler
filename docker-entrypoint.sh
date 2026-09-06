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
: "${AIPS_AI_PROVIDER:=wp_ai_client}"    # Active AI Provider for AIPS (wp_ai_client or meow)
: "${DEFAULT_AI_CONNECTOR_PLUGIN:=ai-provider-for-google}" # Default AI connector plugin
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
  echo "[entrypoint] WordPress not found in /var/www/html — cloning official WordPress core..."

  # Clone pristine official WordPress core repository to ensure untruncated php-ai-client files
  git clone --depth 1 https://github.com/WordPress/WordPress.git /tmp/wpcore
  cp -r /tmp/wpcore/. /var/www/html/
  rm -rf /tmp/wpcore

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
  
  # Configure fatal error handler
  wp config set WP_DISABLE_FATAL_ERROR_HANDLER false --raw --type=constant --path=/var/www/html --allow-root

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
# WordPress AI Infrastructure & Connectors Setup
#============================================================

# Ensure the plugins directory exists.
mkdir -p /var/www/html/wp-content/plugins

# Step 1: Install and activate the official WordPress AI plugin (ai)
echo "[entrypoint] Checking for official WordPress AI plugin (ai)..."
if ! wp plugin is-installed ai --path=/var/www/html --allow-root 2>/dev/null; then
  echo "[entrypoint] Installing official WordPress AI plugin (ai)..."
  wp plugin install ai --activate --path=/var/www/html --allow-root || {
    echo "[entrypoint] WARNING: Failed to install official WordPress AI plugin (ai)."
  }
else
  echo "[entrypoint] Official WordPress AI plugin (ai) is already installed."
  if ! wp plugin is-active ai --path=/var/www/html --allow-root 2>/dev/null; then
    echo "[entrypoint] Activating official WordPress AI plugin (ai)..."
    wp plugin activate ai --path=/var/www/html --allow-root 2>/dev/null || true
  fi
fi

# Step 2: Install and activate default AI connector plugin
CONNECTOR_PLUGIN="${DEFAULT_AI_CONNECTOR_PLUGIN:-ai-provider-for-google}"
if [ -n "${CONNECTOR_PLUGIN}" ]; then
  echo "[entrypoint] Checking for AI connector plugin (${CONNECTOR_PLUGIN})..."
  if ! wp plugin is-installed "${CONNECTOR_PLUGIN}" --path=/var/www/html --allow-root 2>/dev/null; then
    echo "[entrypoint] Installing AI connector plugin (${CONNECTOR_PLUGIN})..."
    wp plugin install "${CONNECTOR_PLUGIN}" --activate --path=/var/www/html --allow-root || {
      echo "[entrypoint] WARNING: Failed to install connector plugin ${CONNECTOR_PLUGIN}."
    }
  else
    echo "[entrypoint] AI connector plugin (${CONNECTOR_PLUGIN}) is already installed."
    if ! wp plugin is-active "${CONNECTOR_PLUGIN}" --path=/var/www/html --allow-root 2>/dev/null; then
      echo "[entrypoint] Activating AI connector plugin (${CONNECTOR_PLUGIN})..."
      wp plugin activate "${CONNECTOR_PLUGIN}" --path=/var/www/html --allow-root 2>/dev/null || true
    fi
  fi
fi

# Step 3: Configure AI connector credentials in WordPress (only if corresponding connector plugin is installed)
if wp plugin is-installed ai-provider-for-google --path=/var/www/html --allow-root 2>/dev/null; then
  if [ -n "${GOOGLE_API_KEY}" ]; then
    echo "[entrypoint] Configuring GOOGLE_API_KEY constant and connector settings..."
    wp config set GOOGLE_API_KEY "${GOOGLE_API_KEY}" --type=constant --path=/var/www/html --allow-root 2>/dev/null || true
    wp option update connectors_ai_google_api_key "${GOOGLE_API_KEY}" --path=/var/www/html --allow-root 2>/dev/null || true
    wp option update connectors_ai_provider_google_api_key "${GOOGLE_API_KEY}" --path=/var/www/html --allow-root 2>/dev/null || true
  fi
fi

if wp plugin is-installed ai-provider-for-openai --path=/var/www/html --allow-root 2>/dev/null; then
  if [ -n "${OPENAI_API_KEY}" ]; then
    echo "[entrypoint] Configuring OPENAI_API_KEY constant..."
    wp config set OPENAI_API_KEY "${OPENAI_API_KEY}" --type=constant --path=/var/www/html --allow-root 2>/dev/null || true
  fi
fi

if wp plugin is-installed ai-provider-for-anthropic --path=/var/www/html --allow-root 2>/dev/null; then
  if [ -n "${ANTHROPIC_API_KEY}" ]; then
    echo "[entrypoint] Configuring ANTHROPIC_API_KEY constant..."
    wp config set ANTHROPIC_API_KEY "${ANTHROPIC_API_KEY}" --type=constant --path=/var/www/html --allow-root 2>/dev/null || true
  fi
fi

# Step 4: Configure active AI provider for AIPS (wp_ai_client vs meow)
NORMALIZED_PROVIDER="$(echo "${AIPS_AI_PROVIDER}" | tr '[:upper:]' '[:lower:]')"
if [[ "${NORMALIZED_PROVIDER}" == *"meow"* ]]; then
  TARGET_AIPS_PROVIDER="meow"
else
  TARGET_AIPS_PROVIDER="wp_ai_client"
fi

if [ "${TARGET_AIPS_PROVIDER}" = "meow" ]; then
  echo "[entrypoint] AIPS_AI_PROVIDER is set to meow. Checking for Meow Apps AI Engine plugin..."
  if ! wp plugin is-installed ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
    echo "[entrypoint] Installing Meow Apps AI Engine plugin..."
    wp plugin install ai-engine --activate --path=/var/www/html --allow-root || {
      echo "[entrypoint] WARNING: Failed to install Meow Apps AI Engine via WP-CLI."
    }
  else
    echo "[entrypoint] Meow Apps AI Engine is already installed."
    if ! wp plugin is-active ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
      echo "[entrypoint] Activating Meow Apps AI Engine..."
      wp plugin activate ai-engine --path=/var/www/html --allow-root 2>/dev/null || true
    fi
  fi
  wp option update aips_ai_provider meow --path=/var/www/html --allow-root 2>/dev/null || true
else
  echo "[entrypoint] Setting AIPS active provider to wp_ai_client..."
  wp option update aips_ai_provider wp_ai_client --path=/var/www/html --allow-root 2>/dev/null || true

  # Still activate Meow Apps AI Engine if present
  if wp plugin is-installed ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
    if ! wp plugin is-active ai-engine --path=/var/www/html --allow-root 2>/dev/null; then
      echo "[entrypoint] Activating installed AI Engine..."
      wp plugin activate ai-engine --path=/var/www/html --allow-root 2>/dev/null || true
    fi
  fi
fi


#============================================================
# AI Post Scheduler Plugin Installation & Activation
#============================================================

PLUGIN_SLUG="ai-post-scheduler"

# Check if plugin is mounted as a volume (development mode)
if [ -d "/var/www/html/wp-content/plugins/${PLUGIN_SLUG}" ]; then
  echo "[entrypoint] Plugin ${PLUGIN_SLUG} found (mounted volume for development)."
  chown -R www-data:www-data /var/www/html/wp-content/plugins/${PLUGIN_SLUG}
elif [ -d "/plugin-src/${PLUGIN_SLUG}" ]; then
  echo "[entrypoint] Copying plugin ${PLUGIN_SLUG} from image into WordPress plugins..."
  cp -R /plugin-src/"${PLUGIN_SLUG}" /var/www/html/wp-content/plugins/
  chown -R www-data:www-data /var/www/html/wp-content/plugins/${PLUGIN_SLUG}
else
  echo "[entrypoint] WARNING: Plugin ${PLUGIN_SLUG} not found in /plugin-src or mounted volume!"
fi

# Ensure Composer autoloader is present in plugin
if [ -d "/var/www/html/wp-content/plugins/${PLUGIN_SLUG}" ]; then
  if [ ! -f "/var/www/html/wp-content/plugins/${PLUGIN_SLUG}/vendor/autoload.php" ]; then
    echo "[entrypoint] Installing composer dependencies for ${PLUGIN_SLUG}..."
    composer install --working-dir="/var/www/html/wp-content/plugins/${PLUGIN_SLUG}" --no-dev --no-interaction || true
  fi
fi

# Check if the plugin is active and activate if needed (after AI prerequisites are ready).
if wp plugin is-active "${PLUGIN_SLUG}" --path=/var/www/html --allow-root 2>/dev/null; then
  echo "[entrypoint] Plugin ${PLUGIN_SLUG} is already active."
else
  echo "[entrypoint] Activating plugin ${PLUGIN_SLUG}..."
  wp plugin activate "${PLUGIN_SLUG}" --path=/var/www/html --allow-root || {
    echo "[entrypoint] WARNING: Failed to activate ${PLUGIN_SLUG}. Check plugin compatibility."
  }
fi


#============================================================
# Xdebug runtime configuration
#============================================================
#
# Xdebug is opt-in via .env. `xdebug.start_with_request=yes` (the previous
# hardcoded default) imposes a substantial per-request cost even with no IDE
# attached, so we default XDEBUG_MODE to `off` and generate the ini here at
# boot from environment variables rather than baking values into dev-php.ini.

XDEBUG_MODE="${XDEBUG_MODE:-off}"
XDEBUG_RUNTIME_INI="/usr/local/etc/php/conf.d/zz-xdebug-runtime.ini"

if [ "$XDEBUG_MODE" = "off" ] || [ -z "$XDEBUG_MODE" ]; then
  # Write an explicit `xdebug.mode = off` ini. Deleting the file is NOT enough:
  # Xdebug's compiled defaults are `mode = develop` and `start_with_request =
  # default` (which resolves to "yes"), so with no ini Xdebug would still run
  # develop-mode instrumentation on every request. `mode = off` disables all
  # Xdebug features (Xdebug 3 semantics) with negligible overhead.
  if php -m | grep -qi '^xdebug$'; then
    cat > "$XDEBUG_RUNTIME_INI" <<EOF
[xdebug]
xdebug.mode = off
EOF
  else
    rm -f "$XDEBUG_RUNTIME_INI"
  fi
  echo "[entrypoint] Xdebug: disabled (XDEBUG_MODE=off)"
elif php -m | grep -qi '^xdebug$'; then
  XDEBUG_LOG_FILE="${XDEBUG_LOG:-/tmp/xdebug.log}"
  mkdir -p "$(dirname "$XDEBUG_LOG_FILE")"
  touch "$XDEBUG_LOG_FILE"
  chown www-data:www-data "$XDEBUG_LOG_FILE"
  chmod 664 "$XDEBUG_LOG_FILE"

  cat > "$XDEBUG_RUNTIME_INI" <<EOF
[xdebug]
xdebug.mode = ${XDEBUG_MODE}
xdebug.start_with_request = ${XDEBUG_START_WITH_REQUEST:-trigger}
xdebug.client_host = ${XDEBUG_CLIENT_HOST:-host.docker.internal}
xdebug.client_port = ${XDEBUG_CLIENT_PORT:-9003}
xdebug.idekey = ${XDEBUG_IDEKEY:-PHPSTORM}
xdebug.log = ${XDEBUG_LOG_FILE}
xdebug.log_level = ${XDEBUG_LOG_LEVEL:-7}
xdebug.discover_client_host = false
xdebug.var_display_max_depth = 5
xdebug.var_display_max_children = 128
xdebug.var_display_max_data = 512
EOF
  echo "[entrypoint] Xdebug: enabled (mode=${XDEBUG_MODE}, start_with_request=${XDEBUG_START_WITH_REQUEST:-trigger})"
else
  echo "[entrypoint] Xdebug: XDEBUG_MODE=${XDEBUG_MODE} requested but xdebug extension is not loaded; skipping."
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
  echo "XDEBUG_MODE (env): ${XDEBUG_MODE:-off}"
  if [ "${XDEBUG_MODE:-off}" = "off" ]; then
    echo "Xdebug is DISABLED. Set XDEBUG_MODE in .env (e.g. debug) and restart the web container to enable."
  else
    php -v | grep -i xdebug || echo "Xdebug extension not detected"
    echo ""
    echo "Xdebug configuration:"
    php -i | grep -i "xdebug.mode\|xdebug.client_host\|xdebug.client_port\|xdebug.start_with_request" || true
  fi

  echo ""
  echo "---- PHP Info ----"
  php -i | grep -i "memory_limit\|upload_max_filesize\|post_max_size\|max_execution_time" || true

  echo ""
  echo "============================================================"
  echo "  Development environment ready!"
  echo "  WordPress: ${WP_SITE_URL}"
  echo "  phpMyAdmin: http://localhost:8082"
  if [ "${XDEBUG_MODE:-off}" = "off" ]; then
    echo "  Xdebug: disabled (set XDEBUG_MODE in .env to enable)"
  else
    echo "  Xdebug: mode=${XDEBUG_MODE}, port ${XDEBUG_CLIENT_PORT:-9003}, start_with_request=${XDEBUG_START_WITH_REQUEST:-trigger}"
  fi
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
