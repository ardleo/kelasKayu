<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'DToZ`Z;6fL]:(f$,3}rttg~ti.Qw X3`wl9bAsN(69#aeF}|LeX0`PfNA^+SctP=');
define('SECURE_AUTH_KEY',  'c>Jw@Q%efy>gU<A7XBkj ,SmMVM=7Syc@4#;B@=m#N$4?GrbF{WIiA8@zL8!i+H#');
define('LOGGED_IN_KEY',    'j(b5b S-8c$<9# {on+pyqg@S>uLuB MpPs 6+|*KXN-p{I{uKIr;~82V0vj4.cS');
define('NONCE_KEY',        '3%*WEuf-i^qHmC/[x`vj[p o>E~+D7F/.ls(0U$JY8;+IHihU035s]~g+QWZ,W>S');
define('AUTH_SALT',        'fGAPF|}>q(U whF-`l,^,PT<w*8|z|5xb`k)m&rBWt)(|l^;9tn%Y{6hBy}ig0SU');
define('SECURE_AUTH_SALT', ';V|JZ%GU=c!XH8xH|7=j[Z_A+S1e%vq#xtgs{JumwY:V6n*y)<;9<w3FZN5>(Qzj');
define('LOGGED_IN_SALT',   'mTLWh+>[p3+-D~%b={<,?u4n)Fj._M|l= q<k/|^l``knO~1h$H#x%6 !cy|MXe-');
define('NONCE_SALT',       'i F4*N6<nDNmXdt>TP4Wmf#t[@sneC,SZ2SpBBQ-4 |USv[,zLv nq6h.w?DwC-.');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the Codex.
 *
 * @link https://codex.wordpress.org/Debugging_in_WordPress
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
