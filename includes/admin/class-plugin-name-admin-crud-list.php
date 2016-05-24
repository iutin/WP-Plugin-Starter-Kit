<?php
/**
 * CONF Plugin Name.
 *
 * @package   Plugin_Name_Admin_CRUD_List
 * @author    CONF_Plugin_Author
 * @license   GPL-2.0+
 * @link      CONF_Author_Link
 * @copyright CONF_Plugin_Copyright
 */

/**
 *-----------------------------------------
 * Do not delete this line
 * Added for security reasons: http://codex.wordpress.org/Theme_Development#Template_Files
 *-----------------------------------------
 */
defined('ABSPATH') or die("Direct access to the script does not allowed");
/*-----------------------------------------*/

/**
 * Create custom admin pages for custom DB Tables using WP_List_Table class
 */

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Plugin_Name_Admin_CRUD_List extends WP_List_Table
{

    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct([
            'singular' => __('Entry Name', 'plugin-name'),
            'plural'   => __('Entry Names', 'plugin-name'),
            'ajax'     => false,
        ]);
    }

    /**
     * Retrieve entries from the database
     *
     * @param int $per_page
     * @param int $page_number
     *
     * @return mixed
     */
    public static function get_entries($per_page = 5, $page_number = 1)
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . Plugin_Name_DB::get_table_name();
        if (!empty($_REQUEST['orderby'])) {
            $sql .= ' ORDER BY ' . esc_sql($_REQUEST['orderby']);
            $sql .= !empty($_REQUEST['order']) ? ' ' . esc_sql($_REQUEST['order']) : ' ASC';
        }
        $sql .= " LIMIT $per_page";
        $sql .= ' OFFSET ' . ($page_number - 1) * $per_page;
        $result = $wpdb->get_results($sql, 'ARRAY_A');
        return $result;
    }

    /**
     * Delete an entry from database.
     *
     * @param int $id Entry ID
     */
    public static function delete_entry($id)
    {
        global $wpdb;
        $wpdb->delete(
            Plugin_Name_DB::get_table_name(),
            ['id' => $id],
            ['%d']
        );
    }

    /**
     * Returns the count of records in the database.
     *
     * @return null|string
     */
    public static function record_count()
    {
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM ' . Plugin_Name_DB::get_table_name();
        return $wpdb->get_var($sql);
    }

    /**
     * No Items Found
     *
     */
    public function no_items()
    {
        _e('No entries found.', 'plugin-name');
    }

    /**
     * Column Titles
     *
     */
    public function column_title($item)
    {

        $actions = array(
            'edit'   => sprintf('<a href="?page=%s&action=edit&id=%s">%s</a>', 'plugin-name-entries-view', $item['id'], __('Edit')),
            'delete' => sprintf('<a href="?page=%s&action=%s&id=%s" onclick="return confirm(\'Are you sure you want to delete this entry?\');">%s</a>', $_REQUEST['page'], 'delete', $item['id'], __('Delete')),
        );

        //Return the title contents
        return sprintf('<a href="?page=%1$s&id=%2$s" class="row-title">%3$s</a> %4$s',
            /*$1%s*/'plugin-name-entry-edit',
            /*$2%s*/$item['id'],
            /*$3%s*/$item['title'],
            /*$4%s*/$this->row_actions($actions)
        );
    }

    /**
     * Render a column when no column specific method exist.
     *
     * @param array $item
     * @param string $column_name
     *
     * @return mixed
     */
    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'title':
            case 'id':
                return $item[$column_name];
            case 'shortcode':
                return '[plugin_shortcode id="' . $item['id'] . '"]';
            default:
                return print_r($item, true); //Show the whole array for troubleshooting purposes
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    public function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="bulk-delete[]" value="%s" />', $item['id']
        );
    }

    /**
     *  Associative array of columns
     *
     * @return array
     */
    public function get_columns()
    {
        $columns = [
            'cb'        => '<input type="checkbox" />',
            'title'     => __('Title', 'plugin-name'),
            'id'        => __('Entry ID', 'plugin-name'),
            'shortcode' => __('Shortcode', 'plugin-name'),
        ];
        return $columns;
    }

    /**
     * Columns to make sortable.
     *
     * @return array
     */
    public function get_sortable_columns()
    {
        $sortable_columns = array(
            'title' => array('title', true),
            'id'    => array('id', false),
        );
        return $sortable_columns;
    }

    /**
     * Returns an associative array containing the bulk action
     *
     * @return array
     */
    public function get_bulk_actions()
    {
        $actions = [
            'bulk-delete' => 'Delete',
        ];
        return $actions;
    }

    /**
     * Handles data query and filter, sorting, and pagination.
     */
    public function prepare_items()
    {
        $columns  = $this->get_columns();
        $hidden   = array();
        $sortable = $this->get_sortable_columns();

        $this->_column_headers = array($columns, $hidden, $sortable);
        /** Process bulk action */
        $this->process_bulk_action();
        $per_page     = 10;
        $current_page = $this->get_pagenum();
        $total_items  = self::record_count();
        $this->set_pagination_args([
            'total_items' => $total_items, //WE have to calculate the total number of items
            'per_page'    => $per_page, //WE have to determine how many items to show on a page
        ]);
        $this->items = self::get_entries($per_page, $current_page);
    }

    /**
     * Handle actions
     *
     */
    public function process_bulk_action()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        //Detect when a bulk action is being triggered...
        if ('delete' === $this->current_action()) {

            self::delete_entry(absint($_GET['id']));
            echo '<div class="updated"><p>Entry has been deleted!</p></div>';
            //            wp_redirect( esc_url( add_query_arg() ) );
            //            exit;

        }

        // If the delete bulk action is triggered
        if ((isset($_POST['action']) && $_POST['action'] == 'bulk-delete')
            || (isset($_POST['action2']) && $_POST['action2'] == 'bulk-delete')
        ) {
            $delete_ids = esc_sql($_POST['bulk-delete']);

            // loop over the array of record IDs and delete them
            foreach ($delete_ids as $id) {
                self::delete_entry($id);
            }
            echo '<div class="updated"><p>Entries has been deleted!</p></div>';
            //    wp_redirect( esc_url( add_query_arg() ) );
            //    exit;
        }
    }

}
