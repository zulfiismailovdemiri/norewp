// ---- noredock (added by bin/setup.sh) ----
// Database: SQLite file in this service's noredock volume (~/.noredock/volumes/norewp-wp/database).
define( 'DB_DIR', getenv( 'NOREDOCK_VOLUME' ) . '/database/' );
define( 'DB_FILE', 'wordpress.sqlite' );

// The URL follows the request, so the site works on any port noredock picks
// and through the proxy (http://wp.norewp.localhost:7080).
if ( ! empty( $_SERVER['HTTP_HOST'] ) ) {
	define( 'WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] );
	define( 'WP_SITEURL', WP_HOME );
}

define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', ! defined( 'WP_CLI' ) );  // log PHP notices from the site, keep WP-CLI quiet
define( 'WP_DEBUG_LOG', getenv( 'NOREDOCK_VOLUME' ) . '/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
