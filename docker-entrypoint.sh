#!/bin/bash
# Docker entrypoint: adjusts config.php for Docker networking
if [ -f /var/www/html/config.php ]; then
    sed -i "s/define('DB_HOST', 'localhost')/define('DB_HOST', 'db')/" /var/www/html/config.php
fi
exec apache2-foreground
