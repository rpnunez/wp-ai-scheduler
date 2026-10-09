ARG WP_IMAGE_TAG=6.7-php8.3-apache
FROM wordpress:${WP_IMAGE_TAG}

# Xdebug version to install via PECL. Leave empty to install the latest
# release compatible with the base image's PHP version.
ARG XDEBUG_VERSION=3.4.0
ARG WP_CLI_VERSION=2.12.0

# Install utilities, mysql client, curl, git, zip tools and Xdebug (PECL)
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
     less \
     vim \
     nano \
     default-mysql-client \
     curl \
     wget \
     git \
     unzip \
     zip \
  && pecl install "xdebug${XDEBUG_VERSION:+-${XDEBUG_VERSION}}" \
  && docker-php-ext-enable xdebug \
  && rm -rf /var/lib/apt/lists/* /tmp/pear

# Copy a dedicated development PHP/Xdebug configuration file. This is also
# bind-mounted by docker-compose during local development so it can be edited
# without rebuilding the image.
COPY dev-php.ini /usr/local/etc/php/conf.d/zz-dev-php.ini
RUN sed -i 's/\r$//' /usr/local/etc/php/conf.d/zz-dev-php.ini && chmod 644 /usr/local/etc/php/conf.d/zz-dev-php.ini

# Install a pinned WP-CLI release (retry on transient network failures)
RUN curl --fail --silent --show-error --location --retry 5 --retry-all-errors \
     -o /usr/local/bin/wp \
     "https://github.com/wp-cli/wp-cli/releases/download/v${WP_CLI_VERSION}/wp-cli-${WP_CLI_VERSION}.phar" \
  && chmod +x /usr/local/bin/wp \
  && wp --allow-root --version

# Install Composer for PHP dependency management
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Enable Apache modules required for WordPress
RUN a2enmod rewrite expires headers

# Copy plugin source into image
COPY ai-post-scheduler /plugin-src/ai-post-scheduler

# Copy custom entrypoint and make executable
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Copy healthcheck script and make executable
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN sed -i 's/\r$//' /usr/local/bin/healthcheck.sh && chmod +x /usr/local/bin/healthcheck.sh

# Define healthcheck (first boot installs WP + plugins, hence the long start period)
HEALTHCHECK --interval=30s --timeout=10s --start-period=180s --retries=3 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
