FROM wordpress:6.4-php8.2-apache

# Install utilities, mysql client, curl and Xdebug (PECL)
RUN apt-get update \
  && apt-get install -y --no-install-recommends \
     less \
     default-mysql-client \
     curl \
     unzip \
  && pecl install xdebug \
  && docker-php-ext-enable xdebug \
  && echo "xdebug.mode=develop,debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.start_with_request=no" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.idekey=VSCODE" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_depth=4" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_children=6" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "xdebug.var_display_max_data=4" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
  && echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/docker-php-mem.ini \
  && rm -rf /var/lib/apt/lists/*

# Install WP-CLI (phar)
RUN curl -o /usr/local/bin/wp https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
  && chmod +x /usr/local/bin/wp

# Copy plugin source into image so entrypoint can copy it into the mounted wp-content/plugins
# Expecting plugin folder in build context at ./ai-post-scheduler
COPY ai-post-scheduler /plugin-src/ai-post-scheduler

# Copy custom entrypoint and make executable
COPY docker-entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Copy healthcheck script and make executable
COPY tools/healthcheck.sh /usr/local/bin/healthcheck.sh
RUN chmod +x /usr/local/bin/healthcheck.sh

# Define healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
  CMD /usr/local/bin/healthcheck.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["apache2-foreground"]