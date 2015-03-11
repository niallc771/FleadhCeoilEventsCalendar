<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'wordpress_b');

/** MySQL database username */
define('DB_USER', 'root');

/** MySQL database password */
define('DB_PASSWORD', '');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

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
define('AUTH_KEY',         'J6R,|nSO$k>sx6goKD|n;{+|{6GF`BW9RN0a/F8Wzj96qS0-4v7.t#RB%U[)4[wT');
define('SECURE_AUTH_KEY',  'hE26S4L=}ae}7mh~Ul?pA;Kw(_wm(JHs:R<rS-}-oS_M5r,yeX(]A}^L(3?%eL+3');
define('LOGGED_IN_KEY',    '#<3GLAlE du*]Q100I8$=:LG@y7U}`cEiDk0anO*Y|2k*C$WQP8F-rqn2.R9-}Kd');
define('NONCE_KEY',        'z;}&S..1Bb-ySXUP|~kN(y2vc7O4d(!3vq&54:UTy:7%c[P`Lx~[7x%xWS{a}6{&');
define('AUTH_SALT',        '&+pAi^@@){^MCAk!g;uvejZ((-4/1$3+-%6Ue=k{[ k$yw@L_C+FcB$:ywu}&?6a');
define('SECURE_AUTH_SALT', '(@KvsG+UZ:;Gn-jw>P_dPGEY>P~u T$+U:(?@I0A5_q0XzU2q38Nh:KJLehVgSE9');
define('LOGGED_IN_SALT',   '9R..=x+@tDIc+23w<G`4)!(1m{>8 !,clM>X<quvxOBl.R|qpX7?~qBqXS3)J,X`');
define('NONCE_SALT',       'QV5]M4sEdi:r=+x.?4+m5RD}cp]jGT@i N <d5-3XW*SP/3ux8^R:YF!fats9}1T');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');
