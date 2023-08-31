<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
Module Name: Orders
Description: Default module for defining orders
Version: 1.0.1
Requires at least: 2.3.*
*/

define('ORDERS_MODULE_NAME', 'orders');
define('ORDER_ATTACHMENTS_FOLDER', 'uploads/orders/');

hooks()->add_filter('before_order_updated', '_format_data_order_feature');
hooks()->add_filter('before_order_added', '_format_data_order_feature');

hooks()->add_action('after_cron_run', 'orders_notification');
hooks()->add_action('admin_init', 'orders_module_init_menu_items');
hooks()->add_action('admin_init', 'orders_permissions');
hooks()->add_action('clients_init', 'orders_clients_area_menu_items');

//hooks()->add_action('app_admin_head', 'orders_head_component');
//hooks()->add_action('app_admin_footer', 'orders_footer_js_component');

hooks()->add_action('staff_member_deleted', 'orders_staff_member_deleted');

hooks()->add_filter('migration_tables_to_replace_old_links', 'orders_migration_tables_to_replace_old_links');
hooks()->add_filter('global_search_result_query', 'orders_global_search_result_query', 10, 3);
hooks()->add_filter('global_search_result_output', 'orders_global_search_result_output', 10, 2);
hooks()->add_filter('get_dashboard_widgets', 'orders_add_dashboard_widget');
hooks()->add_filter('module_orders_action_links', 'module_orders_action_links');


function orders_add_dashboard_widget($widgets)
{
    $widgets[] = [
        'path'      => 'orders/widgets/order_this_week',
        'container' => 'left-8',
    ];
    return $widgets;
}


function orders_staff_member_deleted($data)
{
    $CI = &get_instance();
    $CI->db->where('staff_id', $data['id']);
    $CI->db->update(db_prefix() . 'orders', [
            'staff_id' => $data['transfer_data_to'],
        ]);
}

function orders_global_search_result_output($output, $data)
{
    if ($data['type'] == 'orders') {
        $output = '<a href="' . admin_url('orders/order/' . $data['result']['id']) . '">' . format_order_number($data['result']['id']) . '</a>';
    }

    return $output;
}

function orders_global_search_result_query($result, $q, $limit)
{
    $CI = &get_instance();
    if (has_permission('orders', '', 'view')) {

        // orders
        $CI->db->select()
           ->from(db_prefix() . 'orders')
           ->like(db_prefix() . 'orders.formatted_number', $q)->limit($limit);
        
        $result[] = [
                'result'         => $CI->db->get()->result_array(),
                'type'           => 'orders',
                'search_heading' => _l('orders'),
            ];
        
        if(isset($result[0]['result'][0]['id'])){
            return $result;
        }

        // orders
        $CI->db->select()->from(db_prefix() . 'orders')->like(db_prefix() . 'clients.company', $q)->or_like(db_prefix() . 'orders.formatted_number', $q)->limit($limit);
        $CI->db->join(db_prefix() . 'clients',db_prefix() . 'orders.clientid='.db_prefix() .'clients.userid', 'left');
        $CI->db->order_by(db_prefix() . 'clients.company', 'ASC');

        $result[] = [
                'result'         => $CI->db->get()->result_array(),
                'type'           => 'orders',
                'search_heading' => _l('orders'),
            ];
    }

    return $result;
}

function orders_migration_tables_to_replace_old_links($tables)
{
    $tables[] = [
                'table' => db_prefix() . 'orders',
                'field' => 'description',
            ];

    return $tables;
}

function orders_permissions()
{
    $capabilities = [];

    $capabilities['capabilities'] = [
            'view'   => _l('permission_view') . '(' . _l('permission_global') . ')',
            'create' => _l('permission_create'),
            'edit'   => _l('permission_edit'),
            'delete' => _l('permission_delete'),
    ];

    register_staff_capabilities('orders', $capabilities, _l('orders'));
    
}


/**
* Register activation module hook
*/
register_activation_hook(ORDERS_MODULE_NAME, 'orders_module_activation_hook');

function orders_module_activation_hook()
{
    $CI = &get_instance();
    require_once(__DIR__ . '/install.php');
}

/**
* Register deactivation module hook
*/
register_deactivation_hook(ORDERS_MODULE_NAME, 'orders_module_deactivation_hook');

function orders_module_deactivation_hook()
{

     log_activity( 'Hello, world! . orders_module_deactivation_hook ' );
}

//hooks()->add_action('deactivate_' . $module . '_module', $function);

/**
* Register language files, must be registered if the module is using languages
*/
register_language_files(ORDERS_MODULE_NAME, [ORDERS_MODULE_NAME]);

/**
 * Init orders module menu items in setup in admin_init hook
 * @return null
 */
function orders_module_init_menu_items()
{
    $CI = &get_instance();

    $CI->app->add_quick_actions_link([
            'name'       => _l('order'),
            'url'        => 'orders',
            'permission' => 'orders',
            'position'   => 57,
            ]);

    if (has_permission('orders', '', 'view')) {
        $CI->app_menu->add_sidebar_menu_item('orders', [
                'slug'     => 'orders-tracking',
                'name'     => _l('orders'),
                'icon'     => 'fa fa-calendar',
                'href'     => admin_url('orders'),
                'position' => 12,
        ]);
    }
}

function module_orders_action_links($actions)
{
    $actions[] = '<a href="' . admin_url('settings?group=orders') . '">' . _l('settings') . '</a>';

    return $actions;
}

function orders_clients_area_menu_items()
{   
    // Show menu item only if client is logged in
    if (is_client_logged_in()) {
        add_theme_menu_item('orders', [
                    'name'     => _l('orders'),
                    'href'     => site_url('orders/list'),
                    'position' => 15,
        ]);
    }
}

/**
 * [perfex_dark_theme_settings_tab net menu item in setup->settings]
 * @return void
 */
function orders_settings_tab()
{
    $CI = &get_instance();
    $CI->app_tabs->add_settings_tab('orders', [
        'name'     => _l('settings_group_orders'),
        //'view'     => module_views_path(ORDERS_MODULE_NAME, 'admin/settings/includes/orders'),
        'view'     => 'orders/orders_settings',
        'position' => 51,
    ]);
}

$CI = &get_instance();
$CI->load->helper(ORDERS_MODULE_NAME . '/orders');

//if(($CI->uri->segment(0)=='admin' && $CI->uri->segment(1)=='orders') || $CI->uri->segment(1)=='orders'){
    $CI->app_scripts->add(ORDERS_MODULE_NAME.'-js', base_url('modules/'.ORDERS_MODULE_NAME.'/assets/js/'.ORDERS_MODULE_NAME.'.js'));
    $CI->app_css->add(ORDERS_MODULE_NAME.'-css', base_url('modules/'.ORDERS_MODULE_NAME.'/assets/css/'.ORDERS_MODULE_NAME.'.css'));

//}
//    echo ('<script type="text/javascript" id="vendor-js" src="http://crm.local/modules/orders/assets/js/orders.js"></script>');


