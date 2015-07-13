<?php
if (!defined('ABSPATH')) exit;

class sphinxOmnibusRelatedPostWidget extends WP_Widget
{

    /**
     * Sets up the widgets name etc
     */
    public function __construct()
    {
        // widget actual processes
        parent::__construct(
            'sphinxOmnibusRelatedPostWidget', // Base ID
            __('Related Content', 'text_domain'), // Name
            array('description' => __('Related Post/Category Content', 'text_domain'),) // Args
        );

        //add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue_scripts') );

    }

    public function admin_enqueue_scripts()
    {
        if ('widgets.php' != $hook) {
            return;
        }

        wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . 'sphinxOmnibusRelatedWidget.js');
    } // admin_enqueue_scripts

    /**
     * Outputs the content of the widget
     *
     * @param array $args
     * @param array $instance
     */
    public function widget($args, $instance)
    {
        // outputs the content of the widget
        echo $args['before_widget'];
        if (!empty($instance['title'])) {
            echo $args['before_title'] . apply_filters('widget_title', $instance['title']) . $args['after_title'];
        }

        if (!empty($instance['subtitle'])) {
            echo $instance['subtitle'];
        }

        $matchPostIDArray = array();

        // Identify post or category
        if (is_single()) {
            $rootID = get_the_id();
        } else {
            $idset = $this->get_taxa_and_archiveID();
            $idset['id'];
            $sphinxOmnibus_TaxaIndex = sphinxOmnibus_TaxaIndex::get_instance();
            $rootID = $sphinxOmnibus_TaxaIndex->txIndex_getTxPostID($idset['taxa'], $idset['id']);
        }
        // Get the related search query
        $renderResults = true;
        $sphinxOmnibusRelatedSearchWords = get_field('sphinxOmnibusRelatedSearchWords', $rootID);


        if ($sphinxOmnibusRelatedSearchWords == "") {
            // No Search words found. Attempt to create them.
            $relatedObject = sphinxOmnibusRelatedContent::get_instance();
            $sphinxOmnibusRelatedSearchWords = $relatedObject->updatePostRelatedSearchWords($rootID);
        }

        if ($sphinxOmnibusRelatedSearchWords == "") {
            $renderResults = false;
        }
        // Create search object
        if ($renderResults == true) {
            $objectSphinxSearch = sphinxOmnibusSearch::get_instance();
            $objectSphinxSearch->search->init();


            // Add any required filters
            foreach ($instance as $instanceKey => $instanceElement) {
                if (strpos($instanceKey, "attr-") === 0) {
                    $theFilter = str_replace("attr-", "", $instanceKey);
                    if ($instanceElement != "") {
                        // a search filter attribute key found add filter attributes
                        $objectSphinxSearch->search->setAttributeFilter($theFilter, $instanceElement);
                    }
                }
            }

            // Run search
            //$objectSphinxSearch->search->setLimits($instance['resultcount'],0);
            $searchReturn = $objectSphinxSearch->search->query(stripslashes($sphinxOmnibusRelatedSearchWords));

            if ($searchReturn) {
                $matchPostIDArray = $objectSphinxSearch->search->get_search_results_id_array();
            } else {
                $renderResults = false;
            }
        }

        // Get posts
        if (count($matchPostIDArray) > 0 && $renderResults == true) {

            $mogs = array(
                'post__in' => $matchPostIDArray,
                'posts_per_page' => $instance['resultcount'],
                'orderby' => 'post__in'
            );

            $rQuery = new WP_Query($mogs);
            //$rQuery->posts = $objectSphinxSearch->sort_post_results($rQuery->posts); // Sorts the results

            if ($rQuery->have_posts()) {
                echo '<ul class="sphinxOmnibusRelatedPosts">';
                while ($rQuery->have_posts()) {
                    $rQuery->the_post();
                    echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
                }
                echo '</ul>';
            } else {
                // no posts found
            }
            wp_reset_postdata();

            /* Restore original Post Data */
        } else {
            echo apply_filters('the_content', $instance['htmlNoResults']);
            $renderResults = false;
        }

        // Render


        echo $args['after_widget'];
    }

    /**
     * Outputs the options form on admin
     *
     * @param array $instance The widget options
     */
    public function form($instance)
    {
        // outputs the options form on admin
        $title = !empty($instance['title']) ? $instance['title'] : __('Related Content', 'text_domain');
        $subtitle = $instance['subtitle'];
        $htmlNoResults = !empty($instance['htmlNoResults']) ? $instance['htmlNoResults'] : __('<p>No related content found.</p>', 'text_domain');
        if (intval($instance['resultcount']) <= 0) {
            $showResults = 3;
        } else {
            $showResults = intval($instance['resultcount']);
        }

        $objectSphinxSearch = sphinxOmnibusSearch::get_instance();
        $possibleFilters = $objectSphinxSearch->search->getAvailableAttributeFilters();
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <label for="<?php echo $this->get_field_id('subtitle'); ?>"><?php _e('Subtitle:'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('subtitle'); ?>"
                   name="<?php echo $this->get_field_name('subtitle'); ?>" type="text"
                   value="<?php echo esc_attr($subtitle); ?>">
        </p>
        <label for="<?php echo $this->get_field_id('htmlNoResults'); ?>">Content to Show for No Results [HTML
            Allowed]</label>
        <textarea class="widefat" id="<?php echo $this->get_field_id('htmlNoResults'); ?>"
                  name="<?php echo $this->get_field_name('htmlNoResults'); ?>"><?php echo $htmlNoResults; ?></textarea>
        <label for="<?php echo $this->get_field_id('resultcount'); ?>"></label>
        <select id="<?php echo $this->get_field_id('resultcount'); ?>"
                name="<?php echo $this->get_field_name('resultcount'); ?>">
            <?php
            $vbo = 1;
            while ($vbo <= 25) {
                if ($vbo == $showResults) {
                    $selected = "selected";
                } else {
                    $selected = "";
                }
                echo "\n<option value=\"$vbo\" $selected>$vbo</option>";
                $vbo++;
            }
            ?>
        </select>


        <div class="sphinxOmnibusRelatedWidgetFilters">
            <?php
            foreach ($possibleFilters as $possibleFilter) {
                $availableFilter = "attr-" . $possibleFilter;
                $filterVal = $instance[$availableFilter];
                ?>
                <p>
                    <label
                        for="<?php echo $this->get_field_id($availableFilter); ?>"><?php _e("Filter Attribute " . $possibleFilter . ":"); ?></label>
                    <input class="widefat" id="<?php echo $this->get_field_id($availableFilter); ?>"
                           name="<?php echo $this->get_field_name($availableFilter); ?>" type="text"
                           value="<?php echo esc_attr($filterVal); ?>">
                </p>
            <?php
            }
            ?>
        </div>
    <?php
    }

    /**
     * Processing widget options on save
     *
     * @param array $new_instance The new options
     * @param array $old_instance The previous options
     */
    public function update($new_instance, $old_instance)
    {
        // processes widget options to be saved
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['subtitle'] = (!empty($new_instance['subtitle'])) ? strip_tags($new_instance['subtitle']) : '';
        $instance['htmlNoResults'] = (!empty($new_instance['htmlNoResults'])) ? strip_tags($new_instance['htmlNoResults']) : '';
        $instance['resultcount'] = (!empty($new_instance['resultcount'])) ? intval($new_instance['resultcount']) : '';

        $objectSphinxSearch = sphinxOmnibusSearch::get_instance();
        $possibleFilters = $objectSphinxSearch->search->getAvailableAttributeFilters();
        foreach ($possibleFilters as $possibleFilter) {
            $availableFilter = "attr-" . $possibleFilter;
            $instance[$availableFilter] = (!empty($new_instance[$availableFilter])) ? strip_tags($new_instance[$availableFilter]) : '';
        }
        return $instance;
    }


    function get_taxa_and_archiveID()
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

            $return['taxa'] = $wp_query->query_vars['taxonomy'];
            $return['id'] = $term->term_taxonomy_id;

        else :
            $return = false;

        endif;

        return $return;

    }// rg_get_taxa_and_archiveID

}// sphinxOmnibusRelatedPostWidget


add_action('widgets_init', function () {
    register_widget('sphinxOmnibusRelatedPostWidget');
});