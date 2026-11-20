#!/bin/sh
# norewp setup: download and install WordPress on SQLite. Runs as the `prepare` step of the
# `wp` service (noredock.compose.toml) before every start; every step is skipped when done.
set -e
cd "$(dirname "$0")/.."

# bin/wp = WP-CLI with more memory, without PHP 8.5 deprecation noise, path preset
WP="bin/wp --quiet"
PLUGINS=wordpress/wp-content/plugins
SQLITE=sqlite-database-integration

# 1. WordPress core in the wanted language; every file is checked against wordpress.org's checksums
if [ ! -f wordpress/wp-load.php ]; then
    rm -rf wordpress
    php bin/fetch-core.php "${WP_LOCALE:-de_DE}" "${WP_VERSION:-latest}" wordpress
    if ! $WP core verify-checksums; then
        rm -rf wordpress
        echo "norewp: WordPress files do not match wordpress.org's checksums, removed them" >&2
        exit 1
    fi
fi

# 2. SQLite database integration (official WordPress plugin) + its db.php drop-in
if [ ! -f "$PLUGINS/$SQLITE/load.php" ]; then
    php bin/fetch-plugin.php "$SQLITE" "$PLUGINS"
fi
if [ ! -f wordpress/wp-content/db.php ]; then
    php -r '
        $src = file_get_contents($argv[1]);
        $src = str_replace("'"'"'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'"'"'", "WP_CONTENT_DIR . '"'"'/plugins/sqlite-database-integration'"'"'", $src);
        $src = str_replace("{SQLITE_PLUGIN}", "sqlite-database-integration/load.php", $src);
        file_put_contents($argv[2], $src);
    ' "$PLUGINS/$SQLITE/db.copy" wordpress/wp-content/db.php
fi

# 3. wp-config.php: database file in the noredock volume, URL follows the request
if [ ! -f wordpress/wp-config.php ]; then
    $WP config create --skip-check --locale="${WP_LOCALE:-de_DE}" \
        --dbname=wordpress --dbuser=unused --dbpass=unused --dbhost=localhost \
        --extra-php < config/wp-config-noredock.php
fi
mkdir -p "$NOREDOCK_VOLUME/database"

# 4. Mail to Mailpit (must-use plugin, always kept up to date from config/)
mkdir -p wordpress/wp-content/mu-plugins
cp config/noredock-mail.php wordpress/wp-content/mu-plugins/noredock-mail.php

# 5. Install the site once
if ! $WP core is-installed 2>/dev/null; then
    echo "norewp: installing the site"
    $WP core install --url="http://localhost:$PORT" --title="${WP_TITLE:-norewp}" \
        --admin_user="${WP_ADMIN_USER:-admin}" --admin_password="${WP_ADMIN_PASSWORD:?set WP_ADMIN_PASSWORD in .env}" \
        --admin_email="${WP_ADMIN_EMAIL:-admin@norewp.local}" --locale="${WP_LOCALE:-de_DE}" --skip-email
    $WP plugin activate "$SQLITE"
    $WP rewrite structure '/%postname%/'
    $WP option update timezone_string 'Europe/Berlin'
    $WP plugin verify-checksums "$SQLITE"
    echo "norewp: installed. Admin: ${WP_ADMIN_USER:-admin} (password in .env)"
fi
