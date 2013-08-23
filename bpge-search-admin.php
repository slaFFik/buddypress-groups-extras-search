<?php
class BPGE_ADMIN_SEARCH extends BPGE_ADMIN_TAB{
    // position is used to define where exactly this tab will appear
    var $position = 35;
    // slug that is used in url to access this tab
    var $slug     = 'search';
    // title is used as a tab name
    var $title    = null;

    function __construct(){
        $this->title = __('Search', 'bpge');

        parent::__construct();
    }

    function header_title_attach(){
        return $this->title;
    }

    /**
     * Create sections of options
     */
    function register_sections(){
        add_settings_field('search_prepare',
            __('Prepare Search', 'bpge'),
            array($this, 'display_prepare'),
            $this->slug,
            $this->slug . '_settings');
        add_settings_field('search_fields',
            __('Search for Fields', 'bpge'),
            array($this, 'display_fields'),
            $this->slug,
            $this->slug . '_settings');
        add_settings_field('search_pages',
            __('Search for Pages', 'bpge'),
            array($this, 'display_pages'),
            $this->slug,
            $this->slug . '_settings');
        add_settings_field('field_2_link',
            __('Fields to Links', 'bpge'),
            array($this, 'display_f2l'),
            $this->slug,
            $this->slug . '_settings');
    }

    /**
     * Display the tab description
     */
    function display(){
        echo '<p class="description">'. __('Control the search options of groups fields and pages.', 'bpge') .'</p>';
    }

    /**
     * Preparaion section
     */
    function display_prepare(){ ?>
        <p>
            <?php _e('Currently you need to prepare the data to be searchable. Use the button below for this purpose. This should be done only once.', 'bpge'); ?>
        </p>

        <input type="submit" id="bpge_search_prepare" class="button" name="bpge_search_prepare" value="<?php _e('Prepare Data', 'bpge'); ?>" />
        <?php
    }

    /**
     * Options for fields
     */
    function display_fields(){ ?>
        <p>
            <?php _e('Would you like to enable sitewide search for data entered in groups fields?'); ?>
        </p>

        <?php
        if(!isset($this->bpge['search_fields']) || empty($this->bpge['search_fields']))
            $this->bpge['search_fields'] = 'off';
        ?>

        <ul>
            <li><label>
                <input type="radio" <?php checked('on', $this->bpge['search_fields']); ?> name="bpge_search_fields" value="on" />&nbsp;
                <?php _e('Enable', 'bpge'); ?>
            </label></li>
            <li><label>
                <input type="radio" <?php checked('off', $this->bpge['search_fields']); ?> name="bpge_search_fields" value="off" />&nbsp;
                <?php _e('Disable', 'bpge'); ?>
            </label></li>

        </ul>
        <?php
    }

    /**
     * Options for pages
     */
    function display_pages(){ ?>
        <p>
            <?php _e('Would you like to enable sitewide search for data entered in groups pages?'); ?>
        </p>

        <?php
        if(!isset($this->bpge['search_pages']) || empty($this->bpge['search_pages']))
            $this->bpge['search_pages'] = 'off';
        ?>

        <ul>
            <li><label>
                <input type="radio" <?php checked('on', $this->bpge['search_pages']); ?> name="bpge_search_pages" value="on" />&nbsp;
                <?php _e('Enable', 'bpge'); ?>
            </label></li>
            <li><label>
                <input type="radio" <?php checked('off', $this->bpge['search_pages']); ?> name="bpge_search_pages" value="off" />&nbsp;
                <?php _e('Disable', 'bpge'); ?>
            </label></li>

        </ul>
        <?php
    }

    /**
     *
     */
    function display_f2l(){
        echo '<p>';
            _e('Would you like to convert custom fields values in all groups to links?', 'bpge'); echo '<br />';
            _e('Using that links people will be able to search for groups with the same fields values.', 'bpge');
        echo '</p>';

        if(!isset($this->bpge['field_2_link']) || empty($this->bpge['field_2_link']))
            $this->bpge['field_2_link'] = 'no';

        echo '<ul>';
            echo '<li><label><input type="radio" name="bpge_field_2_link" '.checked($this->bpge['field_2_link'], 'yes', false).' value="yes">&nbsp'.__('Enable','bpge').'</label></li>';
            echo '<li><label><input type="radio" name="bpge_field_2_link" '.checked($this->bpge['field_2_link'], 'no', false).' value="no">&nbsp'.__('Disable','bpge').'</label></li>';
        echo '</ul>';

        echo '<p class="description">'. __('Note: this option uses the same logic as members profiles fields auto-linking.') .'</p>';

    }

    /**
     * Save everything
     */
    function save(){
        if ( isset($_POST['bpge_search_fields']) ) {
            $this->bpge['search_fields'] = $_POST['bpge_search_fields'] == 'on' ? 'on' : 'off';
        }

        if ( isset($_POST['bpge_search_pages']) ) {
            $this->bpge['search_pages'] = $_POST['bpge_search_pages'] == 'on' ? 'on' : 'off';
        }

        if ( isset($_POST['bpge_field_2_link']) ) {
            $this->bpge['field_2_link'] = $_POST['bpge_field_2_link'] == 'yes' ? 'yes' : 'no';
        }

        if( isset($_POST['bpge_search_prepare']) ) {
            // get all data from groupmeta
            global $wpdb, $bp;

            // get all groups meta - group id and associated top level `gpages` page
            $meta = $wpdb->get_results("SELECT group_id, meta_value AS `data`
                                        FROM {$bp->groups->table_name_groupmeta}
                                        WHERE meta_key = 'bpge'");
            $type = BPGE_GPAGES;
            foreach ((array)$meta as $data) {
                $data->data = maybe_unserialize($data->data);
                foreach($data->data as $k => $v){
                    if($k == 'gpage_id'){
                        // $v = post_id (gpage associated with the group)
                        // now we need to get all its subpages and provide group_id for them too
                        update_post_meta($v, 'group_id', $data->group_id);
                        $subpages = $wpdb->get_col("SELECT ID FROM {$wpdb->posts}
                                                    WHERE post_type = '{$type}'
                                                      AND post_parent = '{$v}'");
                        foreach((array)$subpages as $subpage_id){
                            update_post_meta($subpage_id, 'group_id', $data->group_id);
                        }
                    }
                }
            }
        }

        bp_update_option('bpge', $this->bpge);
    }
}

return new BPGE_ADMIN_SEARCH;