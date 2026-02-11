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

# Configure Xdebug for development
RUN echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.log=/tmp/xdebug.log" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.log_level=7" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.discover_client_host=false" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_depth=5" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_children=128" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_data=512" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# Configure PHP for development
RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && echo "upload_max_filesize=64M" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && echo "post_max_size=64M" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && echo "max_input_time=300" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && echo "display_errors=On" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
  && echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
  && echo "log_errors=On" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
  && echo "error_log=/var/log/php_errors.log" >> /usr/local/etc/php/conf.d/docker-php-dev.ini

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
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy healthcheck script and make executable
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Define healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]