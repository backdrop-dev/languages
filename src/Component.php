<?php
/**
 * Language component class.
 *
 * This file holds the `Lang` class, which deals with loading textdomains and
 * locale-specific function files.
 *
 * @package   Backdrop
 * @author    Benjamin Lu <benlumia007@gmail.com>
 * @copyright 2019-2023. Benjamin Lu
 * @link      https://github.com/benlumia007/backdrop
 * @license   https://www.gnu.org/licenses/gpl-2.0.html
 */

namespace Backdrop\Languages;

use Backdrop\Contracts\Bootable;

/**
 * Language class.
 *
 * @since  1.0.0
 *
 * @access public
 */
class Component implements Bootable {

    /**
     * The parent theme's textdomain. Gets set to the value of the `Text
     * Domain` header in `style.css`.
     *
     * @since  1.0.0
     * @var    string
     *
     * @access protected
     */
    protected $parent_textdomain = '';

    /**
     * The child theme's textdomain. Gets set to the value of the `Text
     * Domain` header in `style.css`.
     *
     * @since  1.0.0
     * @var    string
     *
     * @access protected
     */
    protected $child_textdomain = '';

    /**
     * Absolute path to the parent theme's language folder. Theme authors
     * should set the relative path via the `Domain Path` header in `style.css`.
     *
     * @since  1.0.0
     * @var    string
     *
     * @access protected
     */
    protected $parent_path = '';

    /**
     * Absolute path to the child theme's language folder. Theme authors
     * should set the relative path via the `Domain Path` header in `style.css`.
     *
     * @since  1.0.0
     * @var    string|null
     *
     * @access protected
     */
    protected $child_path = '';

    /**
     * Stores the language-related theme info into class properties.
     *
     * @since  1.0.0
     * @return void
     *
     * @access public
     */
    public function __construct() {

        $theme = wp_get_theme( get_template() );

        $this->parent_textdomain = $theme->get( 'TextDomain' );

        $parent_domain = trim( $theme->get( 'DomainPath' ), '/' );
        $template_dir  = get_template_directory();

        // Fall back to the conventional `languages` folder when the
        // theme does not set a `Domain Path` header.
        $this->parent_path = $parent_domain ? $template_dir . '/' . $parent_domain : ( $template_dir ? $template_dir . '/languages' : '' );

        if ( ! is_child_theme() ) {
            return;
        }

        $child = wp_get_theme();

        $this->child_textdomain = $child->get( 'TextDomain' );

        $child_domain  = trim( $child->get( 'DomainPath' ), '/' );
        $stylesheet_dir = get_stylesheet_directory();
        $this->child_path = $child_domain ? $stylesheet_dir . '/' . $child_domain : ( $stylesheet_dir ? $stylesheet_dir . '/languages' : '' );
    }

    /**
     * Adds the class' actions and filters.
     *
     * @since  1.0.0
     * @return void
     *
     * @access public
     */
    public function boot(): void {

        // Load the locale functions files. This must run before any other
        // `after_setup_theme` callback, so it uses the lowest possible
        // priority (PHP_INT_MIN, the same value `~PHP_INT_MAX` used to be).
        add_action( 'after_setup_theme', [ $this, 'loadLocaleFunctions' ], PHP_INT_MIN );

        // Load framework textdomain.
        add_action( 'after_setup_theme', [ $this, 'loadTextdomain' ], 95 );

        // Re-sync the framework textdomain for themes that load their own
        // textdomain after the framework (priority 95) or early on `init`.
        add_action( 'after_setup_theme', [ $this, 'syncTextdomain' ], 1000 );
        add_action( 'init', [ $this, 'syncTextdomain' ], 0 );

        // Overrides the load textdomain function for the 'backdrop' domain.
        add_filter( 'override_load_textdomain', [ $this, 'overrideLoadTextdomain' ], 5, 3 );

        // Filter the textdomain mofile to allow child themes to load the parent theme translation.
        add_filter( 'load_textdomain_mofile', [ $this, 'loadTextdomainMofile' ], 10, 2 );
    }

    /**
     * Gets the parent theme textdomain. This allows the framework to
     * recognize the proper textdomain of the parent theme.
     *
     * @since  1.0.0
     * @return string
     *
     * @access public
     */
    public function parentTextdomain() {
        return $this->parent_textdomain;
    }

    /**
     * Gets the child theme textdomain. This allows the framework to
     * recognize the proper textdomain of the child theme.
     *
     * @since  1.0.0
     * @return string
     *
     * @access public
     */
    public function childTextdomain() {
        return $this->child_textdomain;
    }

    /**
     * Returns the full directory path for the parent theme's domain path set
     * in `style.css`. No trailing slash.
     *
     * @since  1.0.0
     * @param  string $file
     * @return string
     *
     * @access public
     */
    public function parentPath( $file = '' ) {

        $file = ltrim( $file, '/' );

        if ( '' === $this->parent_path ) {
            return '';
        }

        return $file ? "{$this->parent_path}/{$file}" : $this->parent_path;
    }

    /**
     * Returns the full directory path for the child theme's domain path set
     * in `style.css`. No trailing slash.
     *
     * @since  1.0.0
     * @param  string $file
     * @return string
     *
     * @access public
     */
    public function childPath( $file = '' ) {

        $file = ltrim( $file, '/' );

        if ( '' === $this->child_path ) {
            return '';
        }

        return $file ? "{$this->child_path}/{$file}" : $this->child_path;
    }

    /**
     * Loads a `/{$langpath}/{$locale}.php` file for specific locales.
     * `$locale` should be an all lowercase and hyphenated (as opposed to
     * an underscore) file name.  So, an `en_US` locale would be `en-us.php`.
     * Also note that the child theme locale file will load **before** the
     * parent theme locale file.  This is standard practice in core WP for
     * allowing pluggable functions if a theme author so desires.
     *
     * @since  1.0.0
     * @return void
     *
     * @access public
     */
    public function loadLocaleFunctions() {

        // Get the site's locale.
        $locale = is_admin() ? get_user_locale() : get_locale();
        $locale = strtolower( str_replace( '_', '-', $locale ) );

        // Define locale functions files.
        $child_func = $this->childPath( "{$locale}.php" );
        $theme_func = $this->parentPath( "{$locale}.php" );

        // If file exists in child theme.
        if ( is_child_theme() && file_exists( $child_func ) ) {
            require_once $child_func;
        }

        // If file exists in parent theme.
        if ( file_exists( $theme_func ) ) {
            require_once $theme_func;
        }
    }

    /**
     * Loads the framework textdomain. Note that we're just dropping in an
     * empty string for the MO file path. This gets overwritten by the
     * `overrideLoadTextdomain()` filter.
     *
     * @since  1.0.0
     * @return void
     *
     * @access public
     */
    public function loadTextdomain() {
        load_textdomain( 'backdrop', '' );
    }

    /**
     * Re-syncs the framework textdomain with the theme's translations.
     * This covers themes that load their own textdomain after the
     * framework textdomain was loaded (see `overrideLoadTextdomain()`):
     * if the theme's translations are loaded by now, point the
     * `backdrop` domain at them. Safe to call repeatedly; it does
     * nothing until the theme's textdomain is loaded.
     *
     * @since  1.0.0
     * @global array $l10n
     * @return void
     *
     * @access public
     */
    public function syncTextdomain() {
        global $l10n;

        // Get the theme's textdomain.
        $theme_textdomain = $this->parentTextdomain();

        // If the theme's textdomain is loaded, use its translations instead.
        if ( $theme_textdomain && isset( $l10n[ $theme_textdomain ] ) ) {

            $l10n['backdrop'] = $l10n[ $theme_textdomain ];
        }
    }

    /**
     * Overrides the load textdomain functionality when `backdrop` is
     * the domain in use. The purpose of this is to allow theme translations
     * to handle the framework's strings.  What this function does is sets
     * the `backdrop` domain's translations to the theme's. That way,
     * we're not loading multiple of the same MO files.
     *
     * @since  1.0.0
     * @global array   $l10n
     * @param  bool   $override
     * @param  string $domain
     * @param  string $mofile
     * @return bool
     *
     * @access public
     */
    public function overrideLoadTextdomain( $override, $domain, $mofile ) {
        global $l10n;

        // Check if the domain is one of our framework domains.
        if ( 'backdrop' === $domain ) {

            // Get the theme's textdomain.
            $theme_textdomain = $this->parentTextdomain();

            // If the theme's textdomain is loaded, use its translations instead.
            if ( $theme_textdomain && isset( $l10n[ $theme_textdomain ] ) ) {

                $l10n[ $domain ] = $l10n[ $theme_textdomain ];
            }

            // Always override.  We only want the theme to handle translations.
            $override = true;
        }

        return $override;
    }

    /**
     * Filters the `load_textdomain_mofile` filter hook so that we can
     * prepend the theme textdomain to the mofile filename. This also allows
     * child themes to house a copy of the parent theme translations so that
     * it doesn't get overwritten when a parent theme is updated.
     *
     * @since  1.0.0
     * @param  string $mofile File name of the .mo file.
     * @param  string $domain The textdomain currently being filtered.
     * @return string
     *
     * @access public
     */
    public function loadTextdomainMofile( $mofile, $domain ) {

        // If the `$domain` is for the parent or child theme, search for
        // a `$domain-$locale.mo` file.
        if ( $domain === $this->parentTextdomain() || $domain === $this->childTextdomain() ) {

            // Get the locale.
            $locale = is_admin() ? get_user_locale() : get_locale();

            // Define locale functions files.
            $child_mofile = $this->childPath( "{$domain}-{$locale}.mo" );
            $theme_mofile = $this->parentPath( "{$domain}-{$locale}.mo" );

            // Overwrite the mofile if it exists.
            if ( is_child_theme() && file_exists( $child_mofile ) ) {

                $mofile = $child_mofile;

            } elseif ( file_exists( $theme_mofile ) ) {

                $mofile = $theme_mofile;
            }
        }

        return $mofile;
    }

}