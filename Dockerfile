FROM wordpress:6.4-php8.2-apache

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
  && pecl install xdebug-3.3.1 \
  && docker-php-ext-enable xdebug \
  && rm -rf /var/lib/apt/lists/*

# Copy a dedicated development PHP/Xdebug configuration file. This is also
# bind-mounted by docker-compose during local development so it can be edited
# without rebuilding the image.
COPY dev-php.ini /usr/local/etc/php/conf.d/zz-dev-php.ini
RUN sed -i 's/\r$//' /usr/local/etc/php/conf.d/zz-dev-php.ini && chmod 644 /usr/local/etc/php/conf.d/zz-dev-php.ini

# Install WP-CLI (latest version)
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
  && chmod +x /usr/local/bin/wp

# Install Composer for PHP dependency management
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Enable Apache modules required for WordPress
RUN a2enmod rewrite expires headers

# Copy plugin source into image so entrypoint can copy it into the mounted wp-content/plugins
# Expecting plugin folder in build context at ./ai-post-scheduler
COPY ai-post-scheduler /plugin-src/ai-post-scheduler

# Copy custom entrypoint and make executable
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Copy healthcheck script and make executable
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN sed -i 's/\r$//' /usr/local/bin/healthcheck.sh && chmod +x /usr/local/bin/healthcheck.sh

# Define healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]
