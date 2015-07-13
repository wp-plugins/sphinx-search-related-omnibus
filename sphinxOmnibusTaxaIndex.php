<?php
if (!defined('ABSPATH')) exit;


class sphinxOmnibus_TaxaIndex
{
    private static $instance;


    function sphinxOmnibus_TaxaIndex()
    {
        add_action('pre_get_posts', array(&$this, 'sphinxOmnibus_TaxaIndex_search_post_type'), 50);
        add_action('admin_menu', array(&$this, 'sphinxOmnibus_TaxaIndex_remove_menu_items'));

        add_action('created_term', array(&$this, 'sphinxOmnibusTaxa_created_term'), 10, 3);
        add_action('edited_term', array(&$this, 'sphinxOmnibusTaxa_edited_term'), 10, 3);
        add_action('delete_term', array(&$this, 'sphinxOmnibusTaxa_delete_term'), 10, 3);

        // Modifies the outputs of the permalinks to match taxa links
        add_filter('post_link', array(&$this, 'post_link'), 99, 3);
        add_filter('post_type_link', array(&$this, 'post_link'), 99, 3);

        add_action('the_posts', array(&$this, 'txIndexPost_RedirectToTaxa'), 2);

    }

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    function post_link($url, $post, $leavename)
    {
        // If getting the link for a txindex, return the link of the related Taxonomy
        if ($post->post_type == 'txindex') {
            $taxonomy = get_post_meta($post->ID, 'txindex_taxonomy', true);
            $termID = get_post_meta($post->ID, 'txindex_term_id', true);
            $term = get_term($termID, $taxonomy);
            $url = get_term_link($term);
        }
        return $url;
    }

    function txIndexPost_RedirectToTaxa($posts)
    {

        if (count($posts) == 0) {
            return $posts;
        }

        // If a user directly links to the taxa type pages, redirect them via 301
        if (is_single() && $posts[0]->post_type == "txindex") {
            $url = $this->post_link(null, $posts[0], false);
            echo "<p>$url</p>";
            wp_redirect($url, 301);
            exit;
        }
        return $posts;
    }


    function txIndex_getTxPostID($taxonomy = "category", $term_id = 0)
    {
        if (intval($term_id) == 0) {
            return false;
        }

        global $wpdb;

        $txPostID = $wpdb->get_var($wpdb->prepare(
            "SELECT PM1.post_id FROM $wpdb->postmeta PM1
INNER JOIN $wpdb->postmeta PM2 ON PM1.post_id = PM2.post_id AND PM1.meta_key=\"txindex_taxonomy\" AND PM2.meta_key = \"txindex_term_id\"
WHERE PM1.meta_value = %s AND PM2.meta_value = %d",
            $taxonomy,
            $term_id));

        if ($txPostID > 0) {
            return $txPostID;
        } else {

            return false;
        }

    }//txIndex_getTxPostID

    function sphinxOmnibusTaxa_created_term($term_id, $tt_id, $taxonomy)
    {
        $this->sphinxOmnibus_TaxaIndexUpdate("new", $term_id, $tt_id, $taxonomy);
    }

    function sphinxOmnibusTaxa_edited_term($term_id, $tt_id, $taxonomy)
    {
        $this->sphinxOmnibus_TaxaIndexUpdate("edit", $term_id, $tt_id, $taxonomy);
    }

    function sphinxOmnibusTaxa_delete_term($term_id, $tt_id, $taxonomy)
    {
        $this->sphinxOmnibus_TaxaIndexUpdate("delete", $term_id, $tt_id, $taxonomy);
    }

    function sphinxOmnibus_TaxaIndexType()
    {
        $labels = array(
            'name' => _x('TaxonomyIndex', 'post type general name'),
            'singular_name' => _x('TaxonomyIndex Item', 'post type singular name'),
            'add_new' => _x('Add New', 'book'),
            'add_new_item' => __('Add New TaxonomyIndex Item'),
            'edit_item' => __('Edit TaxonomyIndex Item'),
            'new_item' => __('New TaxonomyIndex Item'),
            'all_items' => __('All TaxonomyIndex'),
            'view_item' => __('View TaxonomyIndex Item'),
            'search_items' => __('Search TaxonomyIndex'),
            'not_found' => __('No TaxonomyIndex found'),
            'not_found_in_trash' => __('No TaxonomyIndex found in the Trash'),
            'parent_item_colon' => '',
            'menu_name' => 'TaxonomyIndex'
        );
        $args = array(
            'labels' => $labels,
            'public' => true, // All the relevant settings below inherit from this setting
            'exclude_from_search' => false, // When a search is conducted through search.php, should it be excluded?
            'publicly_queryable' => true, // When a parse_request() search is conducted, should it be included?
            'show_ui' => false, // Should the primary admin menu be displayed?
            'show_in_nav_menus' => false, // Should it show up in Appearance > Menus?
            'show_in_menu' => false, // This inherits from show_ui, and determines *where* it should be displayed in the admin
            'show_in_admin_bar' => false, // Should it show up in the toolbar when a user is logged in?
            'has_archive' => false,
            'rewrite' => array('slug' => 'txindex'),
        );
        if (!post_type_exists('txindex')) {
            register_post_type('txindex', $args);
        }

    }

    function sphinxOmnibus_TaxaIndex_remove_menu_items()
    {
        if (!current_user_can('administrator')):
            remove_menu_page('edit.php?post_type=txindex');
        endif;
    }


    function sphinxOmnibus_TaxaIndexRemove()
    {
        // Removes all Taxonomy Index Posts
        global $wpdb;

        $listOfVictims = $wpdb->get_results("SELECT ID FROM " . $wpdb->posts . " WHERE post_type = 'txindex'", ARRAY_A);

        foreach ($listOfVictims as $robspierre) {
            wp_delete_post($robspierre['ID'], true);
        }
    } // sphinxOmnibus_TaxaIndexRemove()


    function sphinxOmnibus_TaxaIndexUpdate($action = "edit", $term, $tt_id, $taxonomy)
    {
        global $wpdb;

        // Get Taxonomy Data

        // Get the meta data
        $txSlug = $taxonomy . "_" . $term . "_";
        $txMetaQResults = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value FROM " . $wpdb->options . " WHERE option_name LIKE \"%s%\"", $txSlug), ARRAY_A);
        $metaBlob = "";
        if (is_array($txMetaQResults)) {
            foreach ($txMetaQResults as $key => $value) {
                $newOptionName = str_replace($txSlug, "", $value['option_name']);
                $txMeta[$newOptionName] = $value['option_value'];
                $metaBlob .= $value['option_value'];
            }
        }
        $txData = get_term($term, $taxonomy);

        if ($txMeta['rg_category_page_content'] != "") {
            $txContent = $txMeta['rg_category_page_content'];
        } else {
            $txContent = $txData->description;
        }


        // Get existing post object (if there is one)
        $taxIndexItemRow = $wpdb->get_row($wpdb->prepare("SELECT wposts.ID
                    FROM " . $wpdb->posts . " AS wposts
                    INNER JOIN " . $wpdb->postmeta . " AS wpostmeta_taxonomy
                    ON wpostmeta_taxonomy.post_id = wposts.ID
                    AND wpostmeta_taxonomy.meta_key = 'txindex_taxonomy'
                    AND wpostmeta_taxonomy.meta_value = %s
                    INNER JOIN " . $wpdb->postmeta . " AS wpostmeta_tt_id
                    ON wpostmeta_tt_id.post_id = wposts.ID
                    AND wpostmeta_tt_id.meta_key = 'txindex_term_id'
                    AND wpostmeta_tt_id.meta_value = %s
                    ", $taxonomy, $term));
        if (is_array($taxIndexItemRow)) {
            $taxIndexItemID = $taxIndexItemRow['ID'];
        } else {
            $taxIndexItemID = $taxIndexItemRow->ID;
        }

        //$taxIndexItem = get_post($taxIndexItemID,ARRAY_A);

        // New Post Information
        $txUpdatePost = array(
            'post_content' => $txContent,
            'post_name' => $txData->taxonomy . "_" . $txData->slug,
            'post_title' => $txData->name, // Same as Taxonomy's Name
            'post_status' => 'publish',
            'post_type' => 'txindex',
            'ping_status' => 'closed',
            'post_excerpt' => $txData->description, // Load Taxonomy's description
            'comment_status' => 'closed',
        );


        if ($txUpdatePost['post_title'] != "") {
            if ($action == "new" && $taxIndexItemID < 1) {
                // If new, create
                $taxIndexItemID = wp_insert_post($txUpdatePost);
                $sphinxOmnibusRelatedContent = sphinxOmnibusRelatedContent::get_instance();
                $sphinxOmnibusRelatedContent->updatePostRelatedSearchWords($taxIndexItemID);
                update_post_meta($taxIndexItemID, 'txindex_taxonomy', $taxonomy);
                update_post_meta($taxIndexItemID, 'txindex_term_id', $term);
            } else {
                // If delete, delete
                if ($action == "delete") {
                    wp_delete_post($taxIndexItemID, true);
                } else {
                    // Else Update
                    if ($taxIndexItemID > 0) {
                        $txUdatePost['ID'] = $taxIndexItemID;
                    }
                    $taxIndexItemID = wp_update_post($txUpdatePost);
                    $sphinxOmnibusRelatedContent = sphinxOmnibusRelatedContent::get_instance();
                    $sphinxOmnibusRelatedContent->updatePostRelatedSearchWords($taxIndexItemID);
                    update_post_meta($taxIndexItemID, 'txindex_taxonomy', $taxonomy);
                    update_post_meta($taxIndexItemID, 'txindex_term_id', $term);
                }
            }
        }
    }// sphinxOmnibus_TaxaIndexUpdate


    function sphinxOmnibus_TaxaIndexGenerate()
    {
        // Create Taxonomy Index Posts and/or update the existing Taxonomy index posts
        global $wpdb, $listOfVictims;

        // Get a list of all taxonomy and whether there are index posts for them
        $listOfVictims = $wpdb->get_results("SELECT TTX.*,ExIdx.ID FROM " . $wpdb->prefix . "term_taxonomy TTX
    LEFT JOIN
    (SELECT P.ID,TX.meta_value as taxonomy,TT.meta_value as term_id FROM " . $wpdb->prefix . "posts P
    LEFT JOIN " . $wpdb->prefix . "postmeta TX ON P.ID = TX.post_id AND TX.meta_key = \"txindex_taxonomy\"
    LEFT JOIN " . $wpdb->prefix . "postmeta TT ON P.ID = TT.post_id AND TT.meta_key = \"txindex_term_id\"
    WHERE P.post_type = \"txindex\")
    AS ExIdx ON TTX.taxonomy = ExIdx.taxonomy AND TTX.term_id = ExIdx.term_id WHERE ExIdx.ID is null;", ARRAY_A);

        // Loop through the list of all unindexed taxonomy to create the index
        foreach ($listOfVictims as $robspierre) {
            $this->sphinxOmnibus_TaxaIndexUpdate("new", $robspierre['term_id'], $robspierre['term_taxonomy_id'], $robspierre['taxonomy']);
        }

    } // sphinxOmnibus_TaxaIndexGenerate()


    function sphinxOmnibus_TaxaIndex_search_post_type($query)
    {
        if (is_search() && !is_admin()) {
            if (is_array($query->post_type)) {
                $arrNew = $query->post_type;
                $arrNew[] = 'txindex';
                $query->set('post_type', $arrNew);
            } elseif ($query->post_type == 'post') {
                $query->set('post_type', array('post', 'txindex'));
            }
        }
        return $query;
    }


}


