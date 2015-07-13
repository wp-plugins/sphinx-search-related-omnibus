<?php
/*
   Plugin Name: Sphinx Search & Related Omnibus
   Plugin URI: http://wordpress.org/extend/plugins/sphinx-omnibus/
   Version: 0.1
   Author: Tor N. Johnson
   Description: Use sphinx indexes to search content and provide related posts functionality
   Text Domain: sphinx-omnibus
   License: GPLv3
  */

/*
    "WordPress Plugin Template" Copyright (C) 2015 Michael Simpson  (email : michael.d.simpson@gmail.com)

    This following part of this file is part of WordPress Plugin Template for WordPress.

    WordPress Plugin Template is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    WordPress Plugin Template is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Contact Form to Database Extension.
    If not, see http://www.gnu.org/licenses/gpl-3.0.html
*/
if (!defined('ABSPATH')) exit;

$sphinxOmnibus_minimalRequiredPhpVersion = '5.3';

/**
 * Check the PHP version and give a useful error message if the user's version is less than the required version
 * @return boolean true if version check passed. If false, triggers an error which WP will handle, by displaying
 * an error message on the Admin page
 */
function sphinxOmnibus_noticePhpVersionWrong()
{
    global $sphinxOmnibus_minimalRequiredPhpVersion;
    echo '<div class="updated fade">' .
        __('Error: plugin "Sphinx Search & Related Omnibus" requires a newer version of PHP to be running.', 'sphinx-omnibus') .
        '<br/>' . __('Minimal version of PHP required: ', 'sphinx-omnibus') . '<strong>' . $sphinxOmnibus_minimalRequiredPhpVersion . '</strong>' .
        '<br/>' . __('Your server\'s PHP version: ', 'sphinx-omnibus') . '<strong>' . phpversion() . '</strong>' .
        '</div>';
}


function sphinxOmnibus_PhpVersionCheck()
{
    global $sphinxOmnibus_minimalRequiredPhpVersion;
    if (version_compare(phpversion(), $sphinxOmnibus_minimalRequiredPhpVersion) < 0) {
        add_action('admin_notices', 'sphinxOmnibus_noticePhpVersionWrong');
        return false;
    }
    return true;
}

// Define shorthand constants
if (!defined('SPHINXOMNIBUS_PLUGIN_NAME')) {
    define('SPHINXOMNIBUS_PLUGIN_NAME', trim(dirname(plugin_basename(__FILE__)), '/'));
}

if (!defined('SPHINXOMNIBUS_PLUGIN_DIR')) {
    define('SPHINXOMNIBUS_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . SPHINXOMNIBUS_PLUGIN_NAME);
}

if (!defined('SPHINXOMNIBUS_PLUGIN_URL')) {
    define('SPHINXOMNIBUS_PLUGIN_URL', plugins_url() . '/' . SPHINXOMNIBUS_PLUGIN_NAME);
}

// Set version information
if (!defined('SPHINXOMNIBUS_VERSION_KEY')) {
    define('SPHINXOMNIBUS_VERSION_KEY', 'aqsm_version');
}

if (!defined('SPHINXOMNIBUS_VERSION_NUM')) {
    define('SPHINXOMNIBUS_VERSION_NUM', '0.1.0');
}
add_option(SPHINXOMNIBUS_VERSION_KEY, SPHINXOMNIBUS_VERSION_NUM);


// Check to see if updates need to occur
if (get_option(SPHINXOMNIBUS_VERSION_KEY) != SPHINXOMNIBUS_VERSION_NUM) {
    // If there is any future update code needed it will go here

    // Then update the version value
    update_option(SPHINXOMNIBUS_VERSION_KEY, SPHINXOMNIBUS_VERSION_NUM);
}


//////////////////////////////////
// Run initialization
/////////////////////////////////


// Next, run the version check.
// If it is successful, continue with initialization for this plugin
if (sphinxOmnibus_PhpVersionCheck()) {
    // Only load and run the init function if we know PHP version can parse it
    if (!interface_exists('SphinxClient')) {
        require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/lib/sphinxapi.php");
    }

    require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibusAPI.php");
    require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibusTaxaIndex.php");
    require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibusIntegration.php");
    require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibusRelatedPost.php");
    require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibusRelatedPostWidget.php");


    if (is_admin()) {
        require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibus_Admin_sphinxConfParsing.php");
        require_once(SPHINXOMNIBUS_PLUGIN_DIR . "/sphinxOmnibus_Admin.php");
    }


    // General Init

    function sphinxOmnibus_init()
    {

        $defaultObjectSphinxSearch = sphinxOmnibusSearch::get_instance();
        $sphinxOmnibus_TaxaIndex = sphinxOmnibus_TaxaIndex::get_instance();
        $sphinxOmnibusRelatedContent = sphinxOmnibusRelatedContent::get_instance();
        $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexType();

    }

    add_action('init', 'sphinxOmnibus_init');


    function sphinxOmnibus_activate()
    {
        // ATTENTION: This is *only* done during plugin activation hook in this example!
        // You should *NEVER EVER* do this on every page load!!

        if (!is_plugin_active('advanced-custom-fields-pro/acf.php') && !is_plugin_active('advanced-custom-fields/acf.php')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die('Unable to activate. Sphinx Omnibus requires the presence of the Advanced Custom Fields plugin.');
        }


        if (!is_a($sphinxOmnibus_TaxaIndex, sphinxOmnibus_TaxaIndex)) {
            $sphinxOmnibus_TaxaIndex = sphinxOmnibus_TaxaIndex::get_instance();
        }
        $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexType();
        $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexGenerate();
        flush_rewrite_rules();
    }

    register_activation_hook(__FILE__, 'sphinxOmnibus_activate');


    function sphinxOmnibus_deactivate()
    {
        if (!is_a($sphinxOmnibus_TaxaIndex, sphinxOmnibus_TaxaIndex)) {
            $sphinxOmnibus_TaxaIndex = sphinxOmnibus_TaxaIndex::get_instance();
        }
        $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexRemove();
        flush_rewrite_rules();
    }

    register_deactivation_hook(__FILE__, 'sphinxOmnibus_deactivate');


}


if (!function_exists('rg_get_taxa_and_archiveID')) {
    function rg_get_taxa_and_archiveID()
    {
        global $wp_query;

        if (is_category()) :
            $return['taxa'] = 'category';
            $return['id'] = $wp_query->query_vars["cat"];
        elseif (is_tag()) :
            $return['taxa'] = 'tag';
            $return['id'] = $wp_query->query_vars["tag_id"];

        elseif (is_author()) :
            $return['taxa'] = 'author';
            $return['id'] = $wp_query->query_vars["author"];

        elseif (is_day()) :
            $return['taxa'] = 'day';
            $return['id'] = $wp_query->query_vars["day"];

        elseif (is_month()) :
            $return['taxa'] = 'month';
            $return['id'] = $wp_query->query_vars["month"];

        elseif (is_year()) :
            $return['taxa'] = 'year';
            $return['id'] = $wp_query->query_vars["year"];

        elseif (is_tax()) :
            $term = $wp_query->get_queried_object();

            $return['taxa'] = $term->taxonomy;
            $return['id'] = $term->term_taxonomy_id;

        else :
            $return = false;

        endif;

        return $return;

    }// rg_get_taxa_and_archiveID
}



