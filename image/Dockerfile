# Base image shared by every wp-dev project.
#
# Built once and referenced by tag from each project's compose file, so starting a
# new project never waits on a WordPress image build.
#
# Two deliberate choices, both documented in ~/wp-kit/docs/README.md:
#
# 1. No Composer / no PHPUnit. The stock wp-env image runs
#    `composer global require phpunit/phpunit`, and on this host containers resolve
#    Packagist to an IPv6 address they have no route to, so cURL hangs until it times
#    out. Nothing here needs PHP packages.
# 2. WP-CLI and the plugin/theme archives are COPYed from the kit cache rather than
#    downloaded, so the build needs no network at all.

FROM wordpress:php8.3-apache

# Prefer IPv4 over IPv6 when resolving names. Fixes the build and every runtime
# request (WP-CLI, the WordPress HTTP API, Elementor's asset fetches).
RUN printf 'precedence ::ffff:0:0/96  100\n' >> /etc/gai.conf

RUN a2enmod rewrite

# Map www-data onto the host developer's ids. Containers run as www-data, so files
# they create inside bind-mounted project directories end up owned by you rather
# than by root or by uid 33.
ARG HOST_UID=1000
ARG HOST_GID=1000
RUN groupmod -o -g "${HOST_GID}" www-data \
	&& usermod -o -u "${HOST_UID}" -g "${HOST_GID}" www-data

# A fresh named volume inherits ownership from the image path, which is what lets a
# non-root container write core files and install plugins into it.
RUN chown -R www-data:www-data /var/www/html

COPY cache/bin/wp /usr/local/bin/wp
RUN chmod +x /usr/local/bin/wp

COPY cache/packages/ /opt/packages/

RUN { \
		echo 'upload_max_filesize = 256M'; \
		echo 'post_max_size = 256M'; \
		echo 'memory_limit = 512M'; \
		echo 'max_execution_time = 300'; \
	} > /usr/local/etc/php/conf.d/zz-local-dev.ini
