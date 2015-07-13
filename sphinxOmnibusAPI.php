<?php
if (!defined('ABSPATH')) exit;


class sphinxOmnibusAPI
{

    public $adminOptions = array();
    public $adminOptionDefaults = array();
    public $sphinx;
    public $sphinxStatus;
    public $is_searchd_up = false;
    public $connectionAttempted = false;
    public $limits;
    public $search_string;
    public $search_string_original;
    public $attributeFilters;
    public $post_typeIndexReference = array();
    public $search_results = array();
    public $error;
    public $warning;
    public $possibleFilters = array();
    public $search_results_id_array = array();
    private static $instance;

    function sphinxOmnibusAPI()
    {

        // Set default values for $adminOptionsDefaults
        $this->adminOptionDefaults = array(
            'sphinxOmnibusServerHost' => 'localhost',
            'sphinxOmnibusServerPort' => '9312',
            'sphinxOmnibusSelectedIndex' => 'wp_main',
            'sphinxOmnibusEnableTaxonomy' => 'false');


    } // end sphinxOmnibusAPI()

    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }


    function init()
    {
        // Get some key values
        $this->enableTaxonomy = $this->getAdminOption('sphinxOmnibusEnableTaxonomy');
        $this->sphinxConnect();
        if ($this->is_searchd_up) {
            $this->sphinx->resetFilters();
            $attributeFilters = null;
        }
    } // end init


    function sphinxStatus()
    {
        // Returns whether there is a working sphinx connection

        if ($this->connectionAttempted == true) {
            return $this->is_searchd_up;
        } else {
            $IamWorking = $this->sphinxConnect();
            $this->is_searchd_up = $IamWorking;
            $this->connectionAttempted = true;
        }
    } // isConnected


    function getAvailableAttributeFilters()
    {
        // Returns a list of available attribute filters (According to the admin options)
        global $public_query_vars, $private_query_vars;

        if (count($this->possibleFilters) > 0) {
            return $this->possibleFilters;
        }

        // get post filter list
        $possibleFilters = explode(",", $this->getAdminOption('sphinxOmnibusAttributes'));
        foreach ($possibleFilters as $possibleFilter) {
            //if(!in_array($possibleFilter,$public_query_vars) && !in_array($possibleFilter,$private_query_vars)){
            $confirmedPossible[] = trim($possibleFilter);
            //}
        }


        $this->possibleFilters = $confirmedPossible;

        return $this->possibleFilters;
    } // end getAvailableFilters


    function setAttributeFilter($filterAttribute, $value)
    {
        $possibleFilters = $this->getAvailableAttributeFilters();

        if (!in_array($filterAttribute, $possibleFilters)) {
            return false;
        }

        if (!is_array($value)) {
            $value = array($value);
        }

        // If filter is post_type filter, then convert to ID number
        if (count($this->post_typeIndexReference) == 0 && $filterAttribute == "post_type") {
            // Post type array has not yet been populated, populate
            global $wpdb;
            $thisPostTypeListResults = $wpdb->get_results("SELECT (@cnt := @cnt + 1) AS post_type_id, D.* FROM (
                        SELECT DISTINCT(post_type) as PT FROM " . $wpdb->posts . "
                            UNION
                        SELECT DISTINCT(meta_value) as PT FROM " . $wpdb->postmeta . " TX WHERE TX.meta_key = \"txindex_taxonomy\"
                        ORDER BY PT ASC
                        ) AS D
                CROSS JOIN (SELECT @cnt := 0) AS dummy;", ARRAY_A);

            foreach ($thisPostTypeListResults as $PTKey => $PTValue) {
                $this->post_typeIndexReference[$PTValue['PT']] = $PTValue['post_type_id'];
            }
        }

        if ($filterAttribute == "post_type") {
            // Convert the post type strings into INT
            $newValueArray = array();
            foreach ($value as $valKey => $thisValue) {
                if ("any" != $thisValue) {
                    $candidateValue = $this->post_typeIndexReference[$thisValue];
                    if ($candidateValue != null) {
                        $newValueArray[] = intval($candidateValue);
                    }
                }
            }
            $value = $newValueArray;
        }

        // If filter type is a taxononmy, convert the string value to the term_id
        // if filterAttribute && value is not int is taxonomy convert it to term_id
        if (taxonomy_exists($filterAttribute)) {

            // get the term
            foreach ($value as $vkey => $subValue) {
                if (intval($subValue) == 0) {
                    $sphinxOmnibusTax = get_term_by('slug', $subValue, $filterAttribute);

                    // get the term_id
                    $candidateValue = intval($sphinxOmnibusTax->term_id);
                    if ($candidateValue > 0) {
                        // Use the term_id value if one is found
                        $value[$vkey] = $candidateValue;
                    }
                }

            }
        }

        if (count($value) > 0) {
            $this->attributeFilters[$filterAttribute] = $value;
        }
    } // setFilterAttribute


    function query($search_string)
    {
        // Runs the query.  Returns arrays of posts and categories

        // Check for Limits / Set Limits
        if (count($this->limits['posts_per_page']) < 1) {
            // No limits yet set
            $this->setLimits();
        }

        // Prepare Query String
        $this->search_string_original = $search_string;
        $this->search_string = $search_string;
        $this->search_string = $this->unify_keywords($this->search_string);
        $this->search_string = html_entity_decode($this->search_string, ENT_QUOTES);


        // Clear result values
        $this->search_results_id_array = array();
        $this->search_results = array();


        // Set Filters
        $this->sphinx->resetFilters();


        if (is_array($this->attributeFilters)) {
            foreach ($this->attributeFilters as $filterName => $filtervalue) {
                if (($filterName == "post_type" && $filtervalue != 0) || $filterName !== "post_type") {
                    if (is_array($filtervalue)) {
                        foreach ($filtervalue as $key => $value) {
                            $filtervalue[$key] = intval($value);
                        }
                    } else {
                        $filtervalue = intval($filtervalue);
                    }
                    $isTrue = $this->sphinx->setFilter($filterName, $filtervalue);
                }
            }
        }


        // Set matching mode (strictest)
        $this->matchingMode(1);

        // Set Index Weights
        $this->sphinx->setFieldWeights(array(
            'title' => 9750,
            'category_string' => 1,
            'body' => 3,
        ));

        // Run Query (1st attempt)
        //$this->sphinx->SetLimits(0, 1000, 0, 0);
        $res = $this->sphinx->Query($this->search_string, $this->adminOptions['sphinxOmnibusSelectedIndex']);

        // Relax matching mode if nothing is returned then run query (2nd attempt)
        if (empty($res["matches"]) && $this->is_simple_query($this->search_string)) {
            $this->matchingMode(1);
            $res = $this->sphinx->Query($this->search_string, $this->adminOptions['sphinxOmnibusSelectedIndex']);
            $this->used_match_any = true;

        }

        if ($this->sphinx->getLastError()) {
            $this->error = $this->sphinx->getLastError();
        }

        if ($this->sphinx->getLastWarning()) {
            $this->warning = $this->sphinx->getLastWarning();
        }

        //if no posts found return empty array
        if (!is_array($res)) {
            return array();
        }
        if (!isset($res['matches']) && !isset($res['taxonomy'])) {
            return array();
        }
        if (!is_array($res["matches"]) && !is_array($res['taxonomy']["matches"])) return array();

        foreach ($res['matches'] as $rrk => $rr) {
            $this->search_results_id_array[] = ($rrk - 1) / 2;
        }
        // Return results
        $this->search_results = $res;
        return $this->search_results;

    } // query
// end query// end query// end query// end query// end query// end query// end query
// end query// end query// end query// end query// end query// end query// end query


    function matchesArray($res)
    {
        foreach ($res as $key => $value) {
            $newArr[] = $key;
        }
        return $newArr;
    }


    function parse_results()
    {
        global $wpdb;

        $content = array();
        foreach ($this->search_results["matches"] as $key => $val) {
            if ($val['attrs']['comment_id'] == 0)
                $content['posts'][] = array('post_id' => ($key - 1) / 2, 'weight' => $val['weight'], 'comment_id' => 0, 'is_comment' => 0);
            else
                $content['posts'][] = array('comment_id' => ($key) / 2, 'weight' => $val['weight'], 'post_id' => $val['attrs']['post_id'], 'is_comment' => 1);
        }

        $this->posts_info = $content['posts'];
        $this->post_count = $this->search_results['total_found'];

        return $this;
    }

    function get_search_results_id_array()
    {
        if (count($this->search_results["matches"]) <= 0) {
            return false;
        } else {
            foreach ($this->search_results["matches"] as $key => $val) {
                $this->search_results_id_array[] = ($key - 1) / 2;
            }
        }
        return $this->search_results_id_array;
    }

    private function matchingMode($level = 0)
    {
        global $wpdb;
        if ($level == 0) {
            // Set Matching Mode
            if (!empty($_GET['search_sortby'])) {
                $this->params['search_sortby'] = $wpdb->escape($_GET['search_sortby']);
            } else {
                $this->params['search_sortby'] = '';//sort by relevance, by default
            }

            if ($this->params['search_sortby'] == 'date') {
                {
                    $this->sphinx->SetSortMode(SPH_SORT_ATTR_DESC, 'date_added');
                }
            } else if ($this->params['search_sortby'] == 'date_added') {
                $this->sphinx->SetSortMode(SPH_SORT_TIME_SEGMENTS, 'date_added');
            } else {
                $this->sphinx->SetSortMode(SPH_SORT_RELEVANCE);
            }
        } else {
            $this->sphinx->SetMatchMode(SPH_MATCH_ANY);
        }


    } // end matchingMode


    function setLimits($posts_per_page = 0, $offset = 0)
    {
        global $wp_query;

        if (!$this->sphinxStatus()) {
            return false;
        }

        // Set Limits

        // if specifically passed in, use those with intval

        if (intval($posts_per_page) > 0) {
            $ppp = intval($posts_per_page);
            $ofs = intval($offset);
        } else {
            // Set max posts based on php memory limits (this is an approximate science)
            $assumedAveragePostSize = "51200"; // in bytes
            $memoryLimitInBytes = $this->return_bytes_from_string(ini_get('memory_limit'));
            $ppp = floor($memoryLimitInBytes / $assumedAveragePostSize);
            $ofs = 0;//intval(($searchpage - 1) * $ppp);
        }
        $this->limits['posts_per_page'] = $ppp;
        $this->limits['offset'] = $ofs;

        $this->sphinx->setLimits(intval($this->limits['offset']), intval($this->limits['posts_per_page']), 0, 0);

    }


    function getAllAdminOptions()
    {
        // Retrieve the admin options from the database.  Does NOT override manually set options.

        // Make sure settings exist
        foreach ($this->adminOptionDefaults as $key => $organ) {

            // If setting is NOT already set, then get it
            if (!isset($this->adminOptions[$key])) {
                $oldOrgan = esc_attr(get_option($key));
                if ($oldOrgan == "" && $organ != "") {
                    update_option($key, $organ);
                    $oldOrgan = $organ;
                }

                $output[$key] = $oldOrgan;
            } else {
                // Setting is already set
                $output[$key] = $this->adminOptions[$key];
            }
        }

        $this->adminOptions = $output;


        return $output;

    } // getAllAdminOptions


    function setAdminOption($optionName, $optionValue)
    {
        // Sets a single admin option
        if (isset($this->adminOptionDefaults[$optionName])) {
            $this->adminOptions[$optionName] = $optionValue;
            return true;
        } else {
            return false;
        }
    } //setAdminOption


    function getAdminOption($optionName)
    {
        // gets a single admin option
        if ($optionName == "") {
            return false;
        }

        if (!in_array($optionName, $this->adminOptions)) {
            $this->adminOptions[$optionName] = get_option($optionName);
        }
        return $this->adminOptions[$optionName];
    } // getAdminOption


    public function sphinxConnect()
    {
        if ($this->sphinxStatus != -1) {
            // Include the sphinxapi
            require_once(SPHINXOMNIBUS_PLUGIN_DIR . '/lib/sphinxapi.php');

            // check to see if sphinx running
            if ($this->is_searchd_up != 1) {
                $this->connectionAttempted = true;
                $this->sphinx = new SphinxClient();
                $this->getAllAdminOptions();
                $this->sphinx->SetServer($this->adminOptions['sphinxOmnibusServerHost'], intval($this->adminOptions['sphinxOmnibusServerPort']));
                $this->sphinx->SetMaxQueryTime(3500); // Maximum amount of time the search query can run

                $active = $this->sphinx->open();

                if (!$active) {
                    $this->sphinx->close();
                    $this->is_searchd_up = -1;
                    return false;
                } else {
                    $this->is_searchd_up = 1;
                    return true;
                }
            } else {
                return true;
            }
        }

        $this->sphinxStatus = -1;
        return false;
    } // end getAllAdminOptions


    function is_simple_query($query)
    {
        $stopWords = array('@title', '@body', '@category', '!', '-', '~', '(', ')', '|', '"', '/');
        foreach ($stopWords as $st) {
            if (strpos($query, $st) !== false) return false;
        }
        return true;
    } // is_simple_query


    function unify_keywords($keywords)
    {
        //replace key-buffer to key buffer
        //replace key -buffer to key -buffer
        //replace key- buffer to key buffer
        //replace key - buffer to key buffer
        $keywords = preg_replace("#([\w\S])\-([\w\S])#", "\$1 \$2", $keywords);
        $keywords = preg_replace("#([\w\S])\s\-\s([\w\S])#", "\$1 \$2", $keywords);
        $keywords = preg_replace("#([\w\S])-\s([\w\S])#", "\$1 \$2", $keywords);

        $from = array('\\', '(', ')', '|', '!', '@', '~', '"', '&', '/', '^', '$', '=');
        $to = array('\\\\', '\(', '\)', '\|', '\!', '\@', '\~', '\"', '\&', '\/', '\^', '\$', '\=');

        $keywords = str_replace($from, $to, $keywords);
        $keywords = str_ireplace(array('\@title', '\@body', '\@category', '\@tags', '\@\!tags'),
            array('@title', '@body', '@category', '@tags', '@!tags'), $keywords);

        return $keywords;
    } // unify_keywords

    function return_bytes_from_string($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        switch ($last) {
            // The 'G' modifier is available since PHP 5.1.0
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }


}