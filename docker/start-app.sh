#!/bin/sh
set -eu

php bin/migrate.php
exec php-fpm
