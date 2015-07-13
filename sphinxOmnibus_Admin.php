<?php
if (!defined('ABSPATH')) exit;

function sphinxOmnibus_register_setting()
{
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusServerHost'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusServerPort'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusIndexes'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusSelectedIndex'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusEnableTaxonomy'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusAttributes'); // Creates setting in DB
    register_setting('sphinxOmnibus-settings-group', 'sphinxOmnibusConflocation'); // Creates setting in DB


    add_settings_section('sphinxOmnibus-general-settings', 'General Settings', 'sphinxOmnibus_general_settings_callback', 'sphinxOmnibus-settings'); // Defines the Section & group of settings
    add_settings_field('sphinxOmnibusServerHost', 'Sphinx Host', 'sphinxOmnibus_server_host_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field
    add_settings_field('sphinxOmnibusServerPort', 'Sphinx Port', 'sphinxOmnibus_server_port_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field
    add_settings_field('sphinxOmnibus-server-indexes', 'Sphinx Indexes Available', 'sphinxOmnibus_server_indexes_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field
    add_settings_field('sphinxOmnibus-server-selectedIndex', 'Sphinx Selected Post Index', 'sphinxOmnibus_server_selectedIndex_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field
    add_settings_field('sphinxOmnibus-server-attributes', 'Sphinx Post Filter Attributes Available', 'sphinxOmnibus_server_attributes_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field

    add_settings_field('sphinxOmnibusConflocation', 'Load Configuration File From (Replaces Configuration)', 'sphinxOmnibus_conflocation_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings'); // Creates the field


    //add_settings_field( 'sphinxOmnibus-allowableFields', 'Query String Variables to Manage', 'sphinxOmnibus_allowableFields_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings' ); // Creates the field
    //add_settings_field( 'sphinxOmnibus-targetURLs', 'Domain Names to Apply Query String Variables To', 'sphinxOmnibus_targetURL_callback', 'sphinxOmnibus-settings', 'sphinxOmnibus-general-settings' ); // Creates the field
}


function sphinxOmnibus_conf_settings_callback()
{
    // render group code here
}

function sphinxOmnibus_general_settings_callback()
{
    // render group code here
}

function sphinxOmnibus_conflocation_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusConflocation'));
    echo "\n<select name='sphinxOmnibusConflocationSelect' id='sphinxOmnibusConflocation-selector'>";
    echo "\n<option value='none'>None</option>";
    echo "\n<option value='/etc/sphinxsearch/sphinx.conf'>Ubuntu: /etc/sphinxsearch/sphinx.conf</option>";
    echo "\n<option value='/etc/sphinxsearch/sphinx.conf'>RHEL/CentOS: /etc/sphinxsearch/sphinx.conf</option>";
    echo "\n<option value='c:\Sphinx\sphinx.conf'>Windows: c:\Sphinx\sphinx.conf</option>";
    echo "\n<option value='/usr/local/etc/sphinx.conf'>OSX: /usr/local/etc/sphinx.conf</option>";
    echo "\n<option value='custom'>Custom Location</option>";
    echo "\n</select>";


    echo "\n<input type='hidden' name='sphinxOmnibusConflocation' id='sphinxOmnibusConflocation' value='$setting' />";
    echo "<p><em>Select a configuration file to load from.  Note: Your system may have the config located in a different location than 'stock' installs.  If this is the case, select \"Custom\" and input the location of the sphinx.conf.</em></p>";

}


function sphinxOmnibus_server_host_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusServerHost'));
    echo "<input type='text' name='sphinxOmnibusServerHost' value='$setting' />";
}

function sphinxOmnibus_server_port_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusServerPort'));
    echo "<input type='text' name='sphinxOmnibusServerPort' value='$setting' />";
}

function sphinxOmnibus_server_indexes_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusIndexes'));
    echo "<input type='text' name='sphinxOmnibusIndexes' value='$setting' />";
}

function sphinxOmnibus_server_selectedIndex_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusSelectedIndex'));
    $availIndexes = esc_attr(get_option('sphinxOmnibusIndexes'));
    $availIndexesArray = explode(",", $availIndexes);
    echo "<select name='sphinxOmnibusSelectedIndex'>";
    foreach ($availIndexesArray as $candidateIndex) {
        $candidateIndex = trim($candidateIndex);
        if ($setting == $candidateIndex) {
            $selected = "selected";
        } else {
            $selected = "";
        }
        echo "<option value='$candidateIndex' $selected>$candidateIndex</option>";
    }
    echo "</select>";

    //echo "<select type='text' name='' value='$setting' />";
}

function sphinxOmnibus_server_attributes_callback()
{
    $setting = esc_attr(get_option('sphinxOmnibusAttributes'));
    echo "<input type='text' name='sphinxOmnibusAttributes' value='$setting' />";
}


add_action('admin_init', 'sphinxOmnibus_register_setting');


add_action('admin_menu', 'sphinxOmnibus_admin_menu');


function sphinxOmnibus_admin_menu()
{
    $page_title = 'Sphinx Omnibus';
    $menu_title = 'Sphinx Omnibus';
    $capability = 'manage_options';
    $menu_slug = 'sphinxOmnibus-settings';
    $function = 'sphinxOmnibus_settings';
    add_options_page($page_title, $menu_title, $capability, $menu_slug, $function);
}


function sphinxOmnibus_settings()
{
    if (!current_user_can('manage_options')) {
        wp_die('You do not have sufficient permissions to access this page.');
    }

    // Test sphinx status

    $guineaPig = new sphinxOmnibusAPI;
    $status = $guineaPig->sphinxConnect();



    ?>
    <script type="text/javascript">
        var sphinxOmnibus_admin = true;
    </script>
    <div class="wrap">
        <?php screen_icon(); ?>
        <h2>Attribution Sphinx Omnibus</h2>
        <?php
        if ($status === true) {
            echo "<h3>Sphinx is Connected</h3>";
        } else {
            $error = $guineaPig->sphinx->getLastError();
            echo "<h3>Sphinx is NOT Connected.  Error: $error</h3>";
        }
        ?>
        <p></p>


        <div id="poststuff">

            <div id="postbox-container" class="postbox-container">
                <form method="post" action="options.php" class="postbox-container" id="postbox-container-2">
                    <?php settings_fields('sphinxOmnibus-settings-group'); ?>
                    <?php do_settings_sections('sphinxOmnibus-settings'); ?>

                    <?php submit_button(); ?>

                </form>

                <h3>Taxonomy Source Index</h3>

                <p>Taxonomies are searched via a set of posts that are created within wordpress with the post type
                    'txindex'. Those posts can be regenerated here.</p>

                <form method="POST" action="<?php echo admin_url('admin.php'); ?>">
                    <input type="hidden" name="action" value="sphinxOmnibusRegenerateTaxonomyIndexAction"/>
                    <input type="submit" class="button button-secondary" value="Regenerate Taxonomy Source Index"/>
                </form>

            </div>
            <!-- end id="postbox-container-2" class="postbox-container" -->
        </div>
        <!-- end #poststuff -->
	<?php
}


add_action('admin_enqueue_scripts', 'sphinxOmnibus_plugin_admin_scripts');

function sphinxOmnibus_plugin_admin_scripts()
{
    wp_enqueue_script('sphinxOmnibus_plugin_admin_script', SPHINXOMNIBUS_PLUGIN_URL . "/admin-script.js", "jquery");
}


function sphinxOmnibus_selectDefault_SelectedIndex($new_value, $old_value)
{

    // Check to see if the index selected is valid

    // Get list of possible indexes
    $availIndexes = esc_attr(get_option('sphinxOmnibusIndexes'));
    $availIndexesArray = explode(",", $availIndexes);


    // Check to see if selection is possible
    if (!in_array($new_value, $availIndexesArray) || $new_value == "" && count($availIndexesArray) > 0) {

        // Selection not valid, check old value
        if (in_array($old_value, $availIndexesArray) && $new_value != "") {
            // Old selection valid, do not change
            $new_value = $old_value;
        } else {
            // Old selection not valid, us default
            $new_value = $availIndexesArray[0];

        }
    }

    return $new_value;
}// sphinxOmnibus_selectDefault_SelectedIndex

//add_filter( 'pre_update_option_sphinxOmnibusSelectedIndex', 'sphinxOmnibus_selectDefault_SelectedIndex', 10, 2 );


function sphinxOmnibus_conflocation_updateOption($new_value, $old_value)
{


    //update_option('sphinxOmnibusServerPort', '54');

    if ($new_value != "none" && $new_value != "") {
        // Load file if not loading none or ""

        // Get File Content
        $sourceFileContents = file_get_contents($new_value);


        // If success update the sphinxOmnibusConftext option
        if ($sourceFileContents != false) {
            //echo $sourceFileContents;die;

            //Update the settings based on conf file
            sphinxOmnibus_parseSphinxConfiguration($sourceFileContents);

            // if local configuration file was used - assume that the sphinx server is local
            if (!in_array($new_value, array("none", "custom")) || strpos($new_value, "/etc/") !== false) {
                update_option('sphinxOmnibusServerHost', 'localhost');
            }
            $new_value = "none";
        } else {
            update_option('sphinxOmnibusConftext', "MOO");

        }

    }


    return $new_value;

} //sphinxOmnibus_conflocation_updateOption
add_filter('pre_update_option_sphinxOmnibusConflocation', 'sphinxOmnibus_conflocation_updateOption', 10, 2);


function sphinxOmnibus_server_port_filter($new_value, $old_value)
{
    if (intval($new_value) == 0) {
        // new value is not valid
        if (intval($old_value) == 0) {
            // old value is not valid, use default
            $new_value = 9312;
        } else {
            // use old value
            $new_value = $old_value;

        }
    }

    return $new_value;

} // sphinxOmnibus_server_port_filter
add_filter('pre_update_option_sphinxOmnibusServerPort', 'sphinxOmnibus_server_port_filter', 11, 2);


add_action('admin_action_sphinxOmnibusRegenerateTaxonomyIndexAction', 'sphinxOmnibusRegenerateTaxonomyIndexAction_admin_action');
function sphinxOmnibusRegenerateTaxonomyIndexAction_admin_action()
{
    // Do your stuff here

    if (!is_a($sphinxOmnibus_TaxaIndex, sphinxOmnibus_TaxaIndex)) {
        $sphinxOmnibus_TaxaIndex = sphinxOmnibus_TaxaIndex::get_instance();
    }
    $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexType();
    $sphinxOmnibus_TaxaIndex->sphinxOmnibus_TaxaIndexGenerate();
    wp_redirect($_SERVER['HTTP_REFERER']);
    exit();
}

