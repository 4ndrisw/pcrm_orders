<?php

defined('BASEPATH') or exit('No direct script access allowed');

hooks()->add_action('app_admin_head', 'orders_head_component');
//hooks()->add_action('app_admin_footer', 'orders_footer_js__component');
hooks()->add_action('admin_init', 'orders_settings_tab');


function orders_notification()
{
    $CI = &get_instance();
    $CI->load->model('orders/orders_model');
    $orders = $CI->orders_model->get('', true);
    /*
    foreach ($orders as $goal) {
        $achievement = $CI->orders_model->calculate_goal_achievement($goal['id']);

        if ($achievement['percent'] >= 100) {
            if (date('Y-m-d') >= $goal['end_date']) {
                if ($goal['notify_when_achieve'] == 1) {
                    $CI->orders_model->notify_staff_members($goal['id'], 'success', $achievement);
                } else {
                    $CI->orders_model->mark_as_notified($goal['id']);
                }
            }
        } else {
            // not yet achieved, check for end date
            if (date('Y-m-d') > $goal['end_date']) {
                if ($goal['notify_when_fail'] == 1) {
                    $CI->orders_model->notify_staff_members($goal['id'], 'failed', $achievement);
                } else {
                    $CI->orders_model->mark_as_notified($goal['id']);
                }
            }
        }
    }
    */
}


/**
 * Get Order short_url
 * @since  Version 2.7.3
 * @param  object $order
 * @return string Url
 */
function get_order_shortlink($order)
{
    $long_url = site_url("order/{$order->id}/{$order->hash}");
    if (!get_option('bitly_access_token')) {
        return $long_url;
    }

    // Check if order has short link, if yes return short link
    if (!empty($order->short_link)) {
        return $order->short_link;
    }

    // Create short link and return the newly created short link
    $short_link = app_generate_short_link([
        'long_url'  => $long_url,
        'title'     => format_order_number($order->id)
    ]);

    if ($short_link) {
        $CI = &get_instance();
        $CI->db->where('id', $order->id);
        $CI->db->update(db_prefix() . 'orders', [
            'short_link' => $short_link
        ]);
        return $short_link;
    }
    return $long_url;
}

/**
 * Check order restrictions - hash, clientid
 * @param  mixed $id   order id
 * @param  string $hash order hash
 */
function check_order_restrictions($id, $hash)
{
    $CI = &get_instance();
    $CI->load->model('orders_model');
    if (!$hash || !$id) {
        show_404();
    }
    if (!is_client_logged_in() && !is_staff_logged_in()) {
        if (get_option('view_order_only_logged_in') == 1) {
            redirect_after_login_to_current_url();
            redirect(site_url('authentication/login'));
        }
    }
    $order = $CI->orders_model->get($id);
    if (!$order || ($order->hash != $hash)) {
        show_404();
    }
    // Do one more check
    if (!is_staff_logged_in()) {
        if (get_option('view_order_only_logged_in') == 1) {
            if ($order->clientid != get_client_user_id()) {
                show_404();
            }
        }
    }
}

/**
 * Check if order email template for expiry reminders is enabled
 * @return boolean
 */
function is_orders_email_expiry_reminder_enabled()
{
    return total_rows(db_prefix() . 'emailtemplates', ['slug' => 'order-expiry-reminder', 'active' => 1]) > 0;
}

/**
 * Check if there are sources for sending order expiry reminders
 * Will be either email or SMS
 * @return boolean
 */
function is_orders_expiry_reminders_enabled()
{
    return is_orders_email_expiry_reminder_enabled() || is_sms_trigger_active(SMS_TRIGGER_ORDER_EXP_REMINDER);
}

/**
 * Return RGBa order status color for PDF documents
 * @param  mixed $status_id current order status
 * @return string
 */
function order_status_color_pdf($status_id)
{
    if ($status_id == 1) {
        $statusColor = '119, 119, 119';
    } elseif ($status_id == 2) {
        // Sent
        $statusColor = '3, 169, 244';
    } elseif ($status_id == 3) {
        //Declines
        $statusColor = '252, 45, 66';
    } elseif ($status_id == 4) {
        //Accepted
        $statusColor = '0, 191, 54';
    } else {
        // Expired
        $statusColor = '255, 111, 0';
    }

    return hooks()->apply_filters('order_status_pdf_color', $statusColor, $status_id);
}

/**
 * Format order status
 * @param  integer  $status
 * @param  string  $classes additional classes
 * @param  boolean $label   To include in html label or not
 * @return mixed
 */
function format_order_status($status, $classes = '', $label = true)
{
    $id          = $status;
    $label_class = order_status_color_class($status);
    $status      = order_status_by_id($status);
    if ($label == true) {
        return '<span class="label label-' . $label_class . ' ' . $classes . ' s-status order-status-' . $id . ' order-status-' . $label_class . '">' . $status . '</span>';
    }

    return $status;
}

/**
 * Return order status translated by passed status id
 * @param  mixed $id order status id
 * @return string
 */
function order_status_by_id($id)
{
    $status = '';
    if ($id == 1) {
        $status = _l('order_status_draft');
    } elseif ($id == 2) {
        $status = _l('order_status_sent');
    } elseif ($id == 3) {
        $status = _l('order_status_declined');
    } elseif ($id == 4) {
        $status = _l('order_status_accepted');
    } elseif ($id == 5) {
        // status 5
        $status = _l('order_status_expired');
    } else {
        if (!is_numeric($id)) {
            if ($id == 'not_sent') {
                $status = _l('not_sent_indicator');
            }
        }
    }

    return hooks()->apply_filters('order_status_label', $status, $id);
}

/**
 * Return order status color class based on twitter bootstrap
 * @param  mixed  $id
 * @param  boolean $replace_default_by_muted
 * @return string
 */
function order_status_color_class($id, $replace_default_by_muted = false)
{
    $class = '';
    if ($id == 1) {
        $class = 'default';
        if ($replace_default_by_muted == true) {
            $class = 'muted';
        }
    } elseif ($id == 2) {
        $class = 'info';
    } elseif ($id == 3) {
        $class = 'danger';
    } elseif ($id == 4) {
        $class = 'success';
    } elseif ($id == 5) {
        // status 5
        $class = 'warning';
    } else {
        if (!is_numeric($id)) {
            if ($id == 'not_sent') {
                $class = 'default';
                if ($replace_default_by_muted == true) {
                    $class = 'muted';
                }
            }
        }
    }

    return hooks()->apply_filters('order_status_color_class', $class, $id);
}

/**
 * Check if the order id is last invoice
 * @param  mixed  $id orderid
 * @return boolean
 */
function is_last_order($id)
{
    $CI = &get_instance();
    $CI->db->select('id')->from(db_prefix() . 'orders')->order_by('id', 'desc')->limit(1);
    $query            = $CI->db->get();
    $last_order_id = $query->row()->id;
    if ($last_order_id == $id) {
        return true;
    }

    return false;
}

/**
 * Format order number based on description
 * @param  mixed $id
 * @return string
 */
function format_order_number($id)
{
    $CI = &get_instance();
    $CI->db->select('date,number,prefix,number_format')->from(db_prefix() . 'orders')->where('id', $id);
    $order = $CI->db->get()->row();

    if (!$order) {
        return '';
    }

    $number = order_number_format($order->number, $order->number_format, $order->prefix, $order->date);

    return hooks()->apply_filters('format_order_number', $number, [
        'id'       => $id,
        'order' => $order,
    ]);
}


function order_number_format($number, $format, $applied_prefix, $date)
{
    $originalNumber = $number;
    $prefixPadding  = get_option('number_padding_prefixes');

    if ($format == 1) {
        // Number based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 2) {
        // Year based
        $number = $applied_prefix . date('Y', strtotime($date)) . '.' . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT);
    } elseif ($format == 3) {
        // Number-yy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '-' . date('y', strtotime($date));
    } elseif ($format == 4) {
        // Number-mm-yyyy based
        $number = $applied_prefix . str_pad($number, $prefixPadding, '0', STR_PAD_LEFT) . '.' . date('m', strtotime($date)) . '.' . date('Y', strtotime($date));
    }

    return hooks()->apply_filters('order_number_format', $number, [
        'format'         => $format,
        'date'           => $date,
        'number'         => $originalNumber,
        'prefix_padding' => $prefixPadding,
    ]);
}

/**
 * Calculate orders percent by status
 * @param  mixed $status          order status
 * @return array
 */
function get_orders_percent_by_status($status, $project_id = null)
{
    $has_permission_view = has_permission('orders', '', 'view');
    $where               = '';

    if (isset($project_id)) {
        $where .= 'project_id=' . get_instance()->db->escape_str($project_id) . ' AND ';
    }
    if (!$has_permission_view) {
        $where .= get_orders_where_sql_for_staff(get_staff_user_id());
    }

    $where = trim($where);

    if (endsWith($where, ' AND')) {
        $where = substr_replace($where, '', -3);
    }

    $total_orders = total_rows(db_prefix() . 'orders', $where);

    $data            = [];
    $total_by_status = 0;

    if (!is_numeric($status)) {
        if ($status == 'not_sent') {
            $total_by_status = total_rows(db_prefix() . 'orders', 'sent=0 AND status NOT IN(2,3,4)' . ($where != '' ? ' AND (' . $where . ')' : ''));
        }
    } else {
        $whereByStatus = 'status=' . $status;
        if ($where != '') {
            $whereByStatus .= ' AND (' . $where . ')';
        }
        $total_by_status = total_rows(db_prefix() . 'orders', $whereByStatus);
    }

    $percent                 = ($total_orders > 0 ? number_format(($total_by_status * 100) / $total_orders, 2) : 0);
    $data['total_by_status'] = $total_by_status;
    $data['percent']         = $percent;
    $data['total']           = $total_orders;

    return $data;
}

function get_orders_where_sql_for_staff($staff_id)
{
    $CI = &get_instance();
    $has_permission_view_own             = has_permission('orders', '', 'view_own');
    $allow_staff_view_orders_assigned = get_option('allow_staff_view_orders_assigned');
    $whereUser                           = '';
    if ($has_permission_view_own) {
        $whereUser = '((' . db_prefix() . 'orders.addedfrom=' . $CI->db->escape_str($staff_id) . ' AND ' . db_prefix() . 'orders.addedfrom IN (SELECT staff_id FROM ' . db_prefix() . 'staff_permissions WHERE feature = "orders" AND capability="view_own"))';
        if ($allow_staff_view_orders_assigned == 1) {
            $whereUser .= ' OR assigned=' . $CI->db->escape_str($staff_id);
        }
        $whereUser .= ')';
    } else {
        $whereUser .= 'assigned=' . $CI->db->escape_str($staff_id);
    }

    return $whereUser;
}
/**
 * Check if staff member have assigned orders / added as sale agent
 * @param  mixed $staff_id staff id to check
 * @return boolean
 */
function staff_has_assigned_orders($staff_id = '')
{
    $CI       = &get_instance();
    $staff_id = is_numeric($staff_id) ? $staff_id : get_staff_user_id();
    $cache    = $CI->app_object_cache->get('staff-total-assigned-orders-' . $staff_id);

    if (is_numeric($cache)) {
        $result = $cache;
    } else {
        $result = total_rows(db_prefix() . 'orders', ['assigned' => $staff_id]);
        $CI->app_object_cache->add('staff-total-assigned-orders-' . $staff_id, $result);
    }

    return $result > 0 ? true : false;
}
/**
 * Check if staff member can view order
 * @param  mixed $id order id
 * @param  mixed $staff_id
 * @return boolean
 */
function user_can_view_order($id, $staff_id = false)
{
    $CI = &get_instance();

    $staff_id = $staff_id ? $staff_id : get_staff_user_id();

    if (has_permission('orders', $staff_id, 'view')) {
        return true;
    }

    if(is_client_logged_in()){

        $CI = &get_instance();
        $CI->load->model('orders_model');
       
        $order = $CI->orders_model->get($id);
        if (!$order) {
            show_404();
        }
        // Do one more check
        if (get_option('view_ordert_only_logged_in') == 1) {
            if ($order->clientid != get_client_user_id()) {
                show_404();
            }
        }
    
        return true;
    }
    
    $CI->db->select('id, addedfrom, assigned');
    $CI->db->from(db_prefix() . 'orders');
    $CI->db->where('id', $id);
    $order = $CI->db->get()->row();

    if ((has_permission('orders', $staff_id, 'view_own') && $order->addedfrom == $staff_id)
        || ($order->assigned == $staff_id && get_option('allow_staff_view_orders_assigned') == '1')
    ) {
        return true;
    }

    return false;
}


/**
 * Prepare general order pdf
 * @since  Version 1.0.2
 * @param  object $order order as object with all necessary fields
 * @param  string $tag tag for bulk pdf exporter
 * @return mixed object
 */
function order_pdf($order, $tag = '')
{
    return app_pdf('order',  module_libs_path(ORDERS_MODULE_NAME) . 'pdf/Order_pdf', $order, $tag);
}


/**
 * Prepare general order pdf
 * @since  Version 1.0.2
 * @param  object $order order as object with all necessary fields
 * @param  string $tag tag for bulk pdf exporter
 * @return mixed object
 */
function order_office_pdf($order, $tag = '')
{
    return app_pdf('order',  module_libs_path(ORDERS_MODULE_NAME) . 'pdf/Order_office_pdf', $order, $tag);
}



/**
 * Get items table for preview
 * @param  object  $transaction   e.q. invoice, estimate from database result row
 * @param  string  $type          type, e.q. invoice, estimate, proposal
 * @param  string  $for           where the items will be shown, html or pdf
 * @param  boolean $admin_preview is the preview for admin area
 * @return object
 */
function get_order_items_table_data($transaction, $type, $for = 'html', $admin_preview = false)
{
    include_once(module_libs_path(ORDERS_MODULE_NAME) . 'Order_items_table.php');

    $class = new Order_items_table($transaction, $type, $for, $admin_preview);

    $class = hooks()->apply_filters('items_table_class', $class, $transaction, $type, $for, $admin_preview);

    if (!$class instanceof App_items_table_template) {
        show_error(get_class($class) . ' must be instance of "Order_items_template"');
    }

    return $class;
}



/**
 * Add new item do database, used for proposals,estimates,credit notes,invoices
 * This is repetitive action, that's why this function exists
 * @param array $item     item from $_POST
 * @param mixed $rel_id   relation id eq. invoice id
 * @param string $rel_type relation type eq invoice
 */
function add_new_order_item_post($item, $rel_id, $rel_type)
{

    $CI = &get_instance();

    $CI->db->insert(db_prefix() . 'itemable', [
                    'description'      => $item['description'],
                    'long_description' => nl2br($item['long_description']),
                    'qty'              => $item['qty'],
                    'rel_id'           => $rel_id,
                    'rel_type'         => $rel_type,
                    'item_order'       => $item['order'],
                    'unit'             => isset($item['unit']) ? $item['unit'] : 'unit',
                ]);

    $id = $CI->db->insert_id();

    return $id;
}

/**
 * Update order item from $_POST 
 * @param  mixed $item_id item id to update
 * @param  array $data    item $_POST data
 * @param  string $field   field is require to be passed for long_description,rate,item_order to do some additional checkings
 * @return boolean
 */
function update_order_item_post($item_id, $data, $field = '')
{
    $update = [];
    if ($field !== '') {
        if ($field == 'long_description') {
            $update[$field] = nl2br($data[$field]);
        } elseif ($field == 'rate') {
            $update[$field] = number_format($data[$field], get_decimal_places(), '.', '');
        } elseif ($field == 'item_order') {
            $update[$field] = $data['order'];
        } else {
            $update[$field] = $data[$field];
        }
    } else {
        $update = [
            'item_order'       => $data['order'],
            'description'      => $data['description'],
            'long_description' => nl2br($data['long_description']),
            'qty'              => $data['qty'],
            'unit'             => $data['unit'],
        ];
    }

    $CI = &get_instance();
    $CI->db->where('id', $item_id);
    $CI->db->update(db_prefix() . 'itemable', $update);

    return $CI->db->affected_rows() > 0 ? true : false;
}


/**
 * Prepares email template preview $data for the view
 * @param  string $template    template class name
 * @param  mixed $customer_id_or_email customer ID to fetch the primary contact email or email
 * @return array
 */
function order_mail_preview_data($template, $customer_id_or_email, $mailClassParams = [])
{
    $CI = &get_instance();

    if (is_numeric($customer_id_or_email)) {
        $contact = $CI->clients_model->get_contact(get_primary_contact_user_id($customer_id_or_email));
        $email   = $contact ? $contact->email : '';
    } else {
        $email = $customer_id_or_email;
    }

    $CI->load->model('emails_model');

    $data['template'] = $CI->app_mail_template->prepare($email, $template);
    $slug             = $CI->app_mail_template->get_default_property_value('slug', $template, $mailClassParams);

    $data['template_name'] = $slug;

    $template_result = $CI->emails_model->get(['slug' => $slug, 'language' => 'english'], 'row');

    $data['template_system_name'] = $template_result->name;
    $data['template_id']          = $template_result->emailtemplateid;

    $data['template_disabled'] = $template_result->active == 0;

    return $data;
}


/**
 * Function that return full path for upload based on passed type
 * @param  string $type
 * @return string
 */
function get_order_upload_path($type=NULL)
{
   $type = 'order';
   $path = ORDER_ATTACHMENTS_FOLDER;
   
    return hooks()->apply_filters('get_upload_path_by_type', $path, $type);
}




/**
 * Injects theme CSS
 * @return null
 */
function orders_head_component()
{
    $CI = &get_instance();
    if (($CI->uri->segment(1) == 'admin' && $CI->uri->segment(2) == 'orders') ||
        $CI->uri->segment(1) == 'orders'){
        echo '<link href="' . base_url('modules/orders/assets/css/orders.css') . '"  rel="stylesheet" type="text/css" >';
    }
}


/**
 * Remove and format some common used data for the order feature eq invoice,orders etc..
 * @param  array $data $_POST data
 * @return array
 */
function _format_data_order_feature($data)
{
    foreach (_get_order_feature_unused_names() as $u) {
        if (isset($data['data'][$u])) {
            unset($data['data'][$u]);
        }
    }

    if (isset($data['data']['date'])) {
        $data['data']['date'] = to_sql_date($data['data']['date']);
    }

    if (isset($data['data']['open_till'])) {
        $data['data']['open_till'] = to_sql_date($data['data']['open_till']);
    }

    if (isset($data['data']['expirydate'])) {
        $data['data']['expirydate'] = to_sql_date($data['data']['expirydate']);
    }

    if (isset($data['data']['duedate'])) {
        $data['data']['duedate'] = to_sql_date($data['data']['duedate']);
    }

    if (isset($data['data']['clientnote'])) {
        $data['data']['clientnote'] = nl2br_save_html($data['data']['clientnote']);
    }

    if (isset($data['data']['terms'])) {
        $data['data']['terms'] = nl2br_save_html($data['data']['terms']);
    }

    if (isset($data['data']['adminnote'])) {
        $data['data']['adminnote'] = nl2br($data['data']['adminnote']);
    }

    foreach (['country', 'billing_country', 'shipping_country', 'project_id', 'assigned'] as $should_be_zero) {
        if (isset($data['data'][$should_be_zero]) && $data['data'][$should_be_zero] == '') {
            $data['data'][$should_be_zero] = 0;
        }
    }

    return $data;
}


/**
 * Unsed $_POST request names, mostly they are used as helper inputs in the form
 * The top function will check all of them and unset from the $data
 * @return array
 */
function _get_order_feature_unused_names()
{
    return [
        'taxname', 'description',
        'currency_symbol', 'price',
        'isedit', 'taxid',
        'long_description', 'unit',
        'rate', 'quantity',
        'item_select', 'tax',
        'billed_tasks', 'billed_expenses',
        'task_select', 'task_id',
        'expense_id', 'repeat_every_custom',
        'repeat_type_custom', 'bill_expenses',
        'save_and_send', 'merge_current_invoice',
        'cancel_merged_invoices', 'invoices_to_merge',
        'tags', 's_prefix', 'save_and_record_payment',
    ];
}

/**
 * When item is removed eq from invoice will be stored in removed_items in $_POST
 * With foreach loop this function will remove the item from database and it's taxes
 * @param  mixed $id       item id to remove
 * @param  string $rel_type item relation eq. invoice, estimate
 * @return boolena
 */
function handle_removed_order_item_post($id, $rel_type)
{
    $CI = &get_instance();

    $CI->db->where('id', $id);
    $CI->db->where('rel_type', $rel_type);
    $CI->db->delete(db_prefix() . 'itemable');
    if ($CI->db->affected_rows() > 0) {
        return true;
    }

    return false;
}


/**
 * Injects theme CSS
 * @return null
 */
function order_head_component()
{
}

$CI = &get_instance();
// Check if order is excecuted
if ($CI->uri->segment(1)=='orders') {
    hooks()->add_action('app_customers_head', 'order_app_client_includes');
}

/**
 * Theme clients footer includes
 * @return stylesheet
 */
function order_app_client_includes()
{
    echo '<link href="' . base_url('modules/' .ORDERS_MODULE_NAME. '/assets/css/orders.css') . '"  rel="stylesheet" type="text/css" >';
    echo '<script src="' . module_dir_url('' .ORDERS_MODULE_NAME. '', 'assets/js/orders.js') . '"></script>';
}


function after_order_updated($id){


}



/**
 * Check if customer has project assigned
 * @param  mixed $customer_id customer id to check
 * @return boolean
 */
function project_has_orders($project_id)
{
    $totalProjectsOrderd = total_rows(db_prefix() . 'orders', 'project_id=' . get_instance()->db->escape_str($project_id));

    return ($totalProjectsOrderd > 0 ? true : false);
}



/**
 * Function that return order item taxes based on passed item id
 * @param  mixed $itemid
 * @return array
 */
function get_order_item_taxes($itemid)
{
    $CI = &get_instance();
    $CI->db->where('itemid', $itemid);
    $CI->db->where('rel_type', 'order');
    $taxes = $CI->db->get(db_prefix() . 'item_tax')->result_array();
    $i     = 0;
    foreach ($taxes as $tax) {
        $taxes[$i]['taxname'] = $tax['taxname'] . '|' . $tax['taxrate'];
        $i++;
    }

    return $taxes;
}


/**
 * Fetches custom pdf logo url for pdf or use the default logo uploaded for the iso
 * Additional statements applied because this function wont work on all servers. All depends how the server is configured.
 * @return string
 */
function pdf_right_logo_url()
{
    $custom_pdf_logo_image_url = get_option('custom_pdf_logo_image_url');
    $width                     = get_option('pdf_logo_width');
    $isoUploadPath         = 'uploads/iso' . '/';
    $logoUrl                   = '';

    if ($width == '') {
        $width = 120;
    }

    if ($custom_pdf_logo_image_url != '') {
        $logoUrl = $custom_pdf_logo_image_url;
    } else {
        if (get_option('iso_logo_dark') != '' && file_exists($isoUploadPath . get_option('iso_logo_dark'))) {
            $logoUrl = $isoUploadPath . get_option('iso_logo_dark');
        } elseif (get_option('iso_logo') != '' && file_exists($isoUploadPath . get_option('iso_logo'))) {
            $logoUrl = $isoUploadPath . get_option('iso_logo');
        }
    }

    $logoImage = '';

    if ($logoUrl != '') {
        $logoImage = '<img width="' . $width . 'px" src="' . $logoUrl . '">';
    }

    return hooks()->apply_filters('pdf_logo_url', $logoImage);
}

if (!function_exists('format_order_info')) {
    /**
     * Format order info format
     * @param  object $order order from database
     * @param  string $for      where this info will be used? Admin area, HTML preview?
     * @return string
     */
    function format_order_info($order, $for = '')
    {
        $format = get_option('customer_info_format');

        $countryCode = '';
        $countryName = '';

        if ($country = get_country($order->billing_country)) {
            $countryCode = $country->iso2;
            $countryName = $country->short_name;
        }

        $orderTo = '<b>' . get_company_name($order->clientid) . '</b>';
        $phone      = $order->phone;
        $email      = $order->email;

        if ($for == 'admin') {
            $hrefAttrs = '';
            if ($order->rel_type == 'lead') {
                $hrefAttrs = ' href="#" onclick="init_lead(' . $order->rel_id . '); return false;" data-toggle="tooltip" data-title="' . _l('lead') . '"';
            } else {
                $hrefAttrs = ' href="' . admin_url('clients/client/' . $order->rel_id) . '" data-toggle="tooltip" data-title="' . _l('client') . '"';
            }
            $orderTo = '<a' . $hrefAttrs . '>' . $orderTo . '</a>';
        }

        if ($for == 'html' || $for == 'admin') {
            $phone = '<a href="tel:' . $order->phone . '">' . $order->phone . '</a>';
            $email = '<a href="mailto:' . $order->email . '">' . $order->email . '</a>';
        }

        $format = _info_format_replace('company_name', $orderTo, $format);
        $format = _info_format_replace('street', $order->billing_street, $format);
        $format = _info_format_replace('city', $order->billing_city, $format);
        $format = _info_format_replace('state', $order->billing_state, $format);

        $format = _info_format_replace('country_code', $countryCode, $format);
        $format = _info_format_replace('country_name', $countryName, $format);

        $format = _info_format_replace('zip_code', $order->billing_zip, $format);
        $format = _info_format_replace('phone', $phone, $format);
        $format = _info_format_replace('email', $email, $format);

        $whereCF = [];
        if (is_custom_fields_for_customers_portal()) {
            $whereCF['show_on_client_portal'] = 1;
        }

        // If no custom fields found replace all custom fields merge fields to empty
        $format = _maybe_remove_first_and_last_br_tag($format);

        // Remove multiple white spaces
        $format = preg_replace('/\s+/', ' ', $format);
        $format = trim($format);

        return hooks()->apply_filters('order_info_text', $format, ['order' => $order, 'for' => $for]);
    }
}
