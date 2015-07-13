<?php
if (!defined('ABSPATH')) exit;

//$sourceFileLocation = "/etc/sphinxsearch/sphinx.conf";

//$sourceFileContents = file_get_contents($sourceFileLocation);


function sphinxOmnibus_parseSphinxConfiguration($sourceFileContents)
{
    preg_match_all('/[0-9a-zA-Z_\-]{3,}\ [0-9a-zA-Z_\-]{3,}[^a-zA-Z;\-\\_\:]*\{.*\}/misU', $sourceFileContents, $configComponents);

    $indexConfiguration = array();
    $indexList = array();
    $attributeList = array();
    foreach ($configComponents[0] as $key => $configBlock) {

        //var_dump($configBlock);
        preg_match_all('/[^ \{]{3,}+/misU', $configBlock, $blockWords);
        preg_match('/\{.*\}/misU', $configBlock, $blockSettingString);

        // If Is index
        if (trim($blockWords[0][0]) == "index") {
            // Get Sources List
            unset($indexSources);
            preg_match_all('/source[^a-zA-Z=]*=[^\n]*+/misU', $blockSettingString[0], $indexSources);
            foreach ($indexSources[0] as $index) {
                $indexParts = explode("=", $index);
                $indexConfiguration['indexes'][trim($blockWords[0][1])]['sources'][] = trim($indexParts[1]);
                $indexList[] = trim($blockWords[0][1]);
                // Get Source Data
                preg_match('/\n[ *]?source wp_main_posts[ \n]\{.*\}[^;]/misU', $sourceFileContents, $singleSourceConfiguration);
                unset($indexAttrSources);
                preg_match_all('/\n[^#][ *]?sql_attr_[^=]*=[^\n]*+/', $singleSourceConfiguration[0], $indexAttrSources);
                foreach ($indexAttrSources[0] as $iSource) {
                    $sourceAttrParts = explode("=", $iSource);

                    if (trim($sourceAttrParts[0]) !== 'sql_attr_multi') {
                        $indexConfiguration['sources'][trim($blockWords[0][1])]['attributes'][] = trim($sourceAttrParts[1]);
                        $attributeList[] = trim($sourceAttrParts[1]);
                    } else {
                        $sourceAttrSubParts = explode(" ", $sourceAttrParts[1]);
                        $indexConfiguration['sources'][trim($blockWords[0][1])]['attributes'][] = trim($sourceAttrSubParts[1]);
                        $attributeList[] = trim($sourceAttrSubParts[2]);
                    }

                }

            }


        }


        foreach ($indexConfiguration['indexes'] as $ikey => $index) {
            if (!is_array($indexConfiguration['indexes'][$ikey]['attributes'])) {
                $indexConfiguration['indexes'][$ikey]['attributes'] = array();
            }
            foreach ($index['sources'] as $dkey => $idxSource) {
                // Add attributes to each index
                foreach ($indexConfiguration['sources'][$idxSource]['attributes'] as $attr) {
                    //echo "ATTR $attr\n";
                    if (!in_array($attr, $indexConfiguration['indexes'][$ikey]['attributes'])) {
                        $indexConfiguration['indexes'][$ikey]['attributes'][] = $attr;
                    }
                }

            }
        }
    }


// Extract searchd Configuration
    $indexConfiguration['configuration']['port'] = null;
    preg_match_all('/searchd[^a-zA-Z;\-\\_\:]*\{.*\}/misU', $sourceFileContents, $configComponents);
    foreach ($configComponents[0] as $key => $configBlock) {

        // if is searchd, get suggested settings
        preg_match_all('/[\n][^#][\t ]*listen[^a-zA-Z=]*=[^\n]*+/', $configBlock, $searchdConfigSettings);


        foreach ($searchdConfigSettings[0] as $scs) {
            if ($indexConfiguration['configuration']['port'] == null) {
                // No port currently found in config file

                $scsA = explode("=", $scs);

                $scsSet = explode(":", trim($scsA[1]));

                foreach ($scsSet as $skey => $scsSetEntry) {
                    if ($scsSetEntry == intval($scsSetEntry) && intval($scsSetEntry) > 0) {
                        // found a port number
                        // check protocol sphinx (or default)
                        if (!isset($scsSet[$key + 1]) || $scsSet[$key + 1] == "sphinx") {
                            $indexConfiguration['configuration']['port'] = $scsSetEntry;
                        }

                    }
                }

            }
        }


    }


    $indexConfiguration['indexList'] = array_unique($indexList);
    $indexConfiguration['attributeList'] = array_unique($attributeList);


    // Update options for indexes & filter attributes
    if (count($indexConfiguration['indexList']) > 0) {
        update_option('sphinxOmnibusIndexes', implode(",", $indexConfiguration['indexList']));

        // Check to make sure the selected index is still valid
        $selectedIndex = get_option('sphinxOmnibusSelectedIndex');

        if (!in_array($selectedIndex, $indexConfiguration['indexList']) || $selectedIndex == "") {
            // Selected index no longer exists
            update_option('sphinxOmnibusSelectedIndex', $indexConfiguration['indexList'][0]);
        }


    } else {
        // no indexes found.
        // Empty settings and return null

        update_option('sphinxOmnibusIndexes', "");
        update_option('sphinxOmnibusSelectedIndex', "");
        update_option('sphinxOmnibusAttributes', "");
        update_option('sphinxOmnibusServerPort', "");
    }


    if (count($indexConfiguration['attributeList']) > 0) {
        update_option('sphinxOmnibusAttributes', implode(",", $indexConfiguration['attributeList']));

    }
    if (intval($indexConfiguration['configuration']['port']) != 0) {
        update_option('sphinxOmnibusServerPort', intval($indexConfiguration['configuration']['port']));
    }
//echo "<pre>";var_dump($indexConfiguration);echo "</pre>";die;


    echo json_encode($indexConfiguration);
    return $indexConfiguration;
} // end sphinxOmnibus_parseSphinxConfiguration()
