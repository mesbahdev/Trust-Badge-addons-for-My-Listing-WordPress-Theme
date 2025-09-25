<?php
/**
 * Simple PSR-4 style autoloader for plugin classes.
 *
 * @package TrustBadge
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TB_Autoloader {

    /**
     * Namespace prefix.
     *
     * @var string
     */
    protected static $prefix = 'TB_';

    /**
     * Initialise the autoloader.
     */
    public static function init() {
        spl_autoload_register( [ __CLASS__, 'autoload' ] );
    }

    /**
     * Autoload callback.
     *
     * @param string $class Class name.
     */
    protected static function autoload( $class ) {
        if ( 0 !== strpos( $class, self::$prefix ) ) {
            return;
        }

        $filename = strtolower( str_replace( '_', '-', $class ) );
        $path     = TB_PLUGIN_DIR . 'includes/class-' . $filename . '.php';

        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}
