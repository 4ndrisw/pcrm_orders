<?php

use app\services\AbstractKanban;
use app\services\orders\OrdersPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Orders_model extends App_Model
{
    private $statuses;

    private $shipping_fields = ['shipping_street', 'shipping_city', 'shipping_city', 'shipping_state', 'shipping_zip', 'shipping_country'];

    public function __construct()
    {
        parent::__construct();

        $this->statuses = hooks()->apply_filters('before_set_order_statuses', [
            1,
            2,
            5,
            3,
            4,
        ]);
    }

    /**
     * Get unique sale agent for orders / Used for filters
     * @return array
     */
    public function get_sale_agents()
    {
        return $this->db->query("SELECT DISTINCT(sale_agent) as sale_agent, CONCAT(firstname, ' ', lastname) as full_name FROM " . db_prefix() . 'orders JOIN ' . db_prefix() . 'staff on ' . db_prefix() . 'staff.staffid=' . db_prefix() . 'orders.sale_agent WHERE sale_agent != 0')->result_array();
    }

    /**
     * Get order/s
     * @param mixed $id order id
     * @param array $where perform where
     * @return mixed
     */
    public function get($id = '', $where = [])
    {
        $this->db->select('*,' . db_prefix() . 'currencies.id as currencyid, ' . db_prefix() . 'orders.id as id, ' . db_prefix() . 'currencies.name as currency_name');
        $this->db->from(db_prefix() . 'orders');
        $this->db->join(db_prefix() . 'currencies', db_prefix() . 'currencies.id = ' . db_prefix() . 'orders.currency', 'left');
        $this->db->where($where);
        if (is_numeric($id)) {
            $this->db->where(db_prefix() . 'orders.id', $id);
            $order = $this->db->get()->row();
            if ($order) {
                $order->attachments                           = $this->get_attachments($id);
                $order->visible_attachments_to_customer_found = false;

                foreach ($order->attachments as $attachment) {
                    if ($attachment['visible_to_customer'] == 1) {
                        $order->visible_attachments_to_customer_found = true;

                        break;
                    }
                }

                $order->items = get_items_by_type('order', $id);

                if ($order->project_id != 0) {
                    $this->load->model('projects_model');
                    $order->project_data = $this->projects_model->get($order->project_id);
                }

                $order->client = $this->clients_model->get($order->clientid);

                if (!$order->client) {
                    $order->client          = new stdClass();
                    $order->client->company = $order->deleted_customer_name;
                }

                $this->load->model('email_schedule_model');
                $order->orderd_email = $this->email_schedule_model->get($id, 'order');
            }

            return $order;
        }
        $this->db->order_by('number,YEAR(date)', 'desc');

        return $this->db->get()->result_array();
    }

    /**
     * Get order statuses
     * @return array
     */
    public function get_statuses()
    {
        return $this->statuses;
    }

    public function clear_signature($id)
    {
        $this->db->select('signature');
        $this->db->where('id', $id);
        $order = $this->db->get(db_prefix() . 'orders')->row();

        if ($order) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'orders', ['signature' => null]);

            if (!empty($order->signature)) {
                unlink(get_upload_path_by_type('order') . $id . '/' . $order->signature);
            }

            return true;
        }

        return false;
    }

    /**
     * Convert order to invoice
     * @param mixed $id order id
     * @return mixed     New invoice ID
     */
    public function convert_to_invoice($id, $client = false, $draft_invoice = false)
    {
        // Recurring invoice date is okey lets convert it to new invoice
        $_order = $this->get($id);

        $new_invoice_data = [];
        if ($draft_invoice == true) {
            $new_invoice_data['save_as_draft'] = true;
        }
        $new_invoice_data['clientid']   = $_order->clientid;
        $new_invoice_data['project_id'] = $_order->project_id;
        $new_invoice_data['number']     = get_option('next_invoice_number');
        $new_invoice_data['date']       = _d(date('Y-m-d'));
        $new_invoice_data['duedate']    = _d(date('Y-m-d'));
        if (get_option('invoice_due_after') != 0) {
            $new_invoice_data['duedate'] = _d(date('Y-m-d', strtotime('+' . get_option('invoice_due_after') . ' DAY', strtotime(date('Y-m-d')))));
        }
        $new_invoice_data['show_quantity_as'] = $_order->show_quantity_as;
        $new_invoice_data['currency']         = $_order->currency;
        $new_invoice_data['subtotal']         = $_order->subtotal;
        $new_invoice_data['total']            = $_order->total;
        $new_invoice_data['adjustment']       = $_order->adjustment;
        $new_invoice_data['discount_percent'] = $_order->discount_percent;
        $new_invoice_data['discount_total']   = $_order->discount_total;
        $new_invoice_data['discount_type']    = $_order->discount_type;
        $new_invoice_data['sale_agent']       = $_order->sale_agent;
        // Since version 1.0.6
        $new_invoice_data['billing_street']   = clear_textarea_breaks($_order->billing_street);
        $new_invoice_data['billing_city']     = $_order->billing_city;
        $new_invoice_data['billing_state']    = $_order->billing_state;
        $new_invoice_data['billing_zip']      = $_order->billing_zip;
        $new_invoice_data['billing_country']  = $_order->billing_country;
        $new_invoice_data['shipping_street']  = clear_textarea_breaks($_order->shipping_street);
        $new_invoice_data['shipping_city']    = $_order->shipping_city;
        $new_invoice_data['shipping_state']   = $_order->shipping_state;
        $new_invoice_data['shipping_zip']     = $_order->shipping_zip;
        $new_invoice_data['shipping_country'] = $_order->shipping_country;

        if ($_order->include_shipping == 1) {
            $new_invoice_data['include_shipping'] = 1;
        }

        $new_invoice_data['show_shipping_on_invoice'] = $_order->show_shipping_on_order;
        $new_invoice_data['terms']                    = get_option('predefined_terms_invoice');
        $new_invoice_data['clientnote']               = get_option('predefined_clientnote_invoice');
        // Set to unpaid status automatically
        $new_invoice_data['status']    = 1;
        $new_invoice_data['adminnote'] = '';

        $this->load->model('payment_modes_model');
        $modes = $this->payment_modes_model->get('', [
            'expenses_only !=' => 1,
        ]);
        $temp_modes = [];
        foreach ($modes as $mode) {
            if ($mode['selected_by_default'] == 0) {
                continue;
            }
            $temp_modes[] = $mode['id'];
        }
        $new_invoice_data['allowed_payment_modes'] = $temp_modes;
        $new_invoice_data['newitems']              = [];
        $custom_fields_items                       = get_custom_fields('items');
        $key                                       = 1;
        foreach ($_order->items as $item) {
            $new_invoice_data['newitems'][$key]['description']      = $item['description'];
            $new_invoice_data['newitems'][$key]['long_description'] = clear_textarea_breaks($item['long_description']);
            $new_invoice_data['newitems'][$key]['qty']              = $item['qty'];
            $new_invoice_data['newitems'][$key]['unit']             = $item['unit'];
            $new_invoice_data['newitems'][$key]['taxname']          = [];
            $taxes                                                  = get_order_item_taxes($item['id']);
            foreach ($taxes as $tax) {
                // tax name is in format TAX1|10.00
                array_push($new_invoice_data['newitems'][$key]['taxname'], $tax['taxname']);
            }
            $new_invoice_data['newitems'][$key]['rate']  = $item['rate'];
            $new_invoice_data['newitems'][$key]['order'] = $item['item_order'];
            foreach ($custom_fields_items as $cf) {
                $new_invoice_data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);

                if (!defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }
        $this->load->model('invoices_model');
        $id = $this->invoices_model->add($new_invoice_data);
        if ($id) {
            // Customer accepted the order and is auto converted to invoice
            if (!is_staff_logged_in()) {
                $this->db->where('rel_type', 'invoice');
                $this->db->where('rel_id', $id);
                $this->db->delete(db_prefix() . 'sales_activity');
                $this->invoices_model->log_invoice_activity($id, 'invoice_activity_auto_converted_from_order', true, serialize([
                    '<a href="' . admin_url('orders/list_orders/' . $_order->id) . '">' . format_order_number($_order->id) . '</a>',
                ]));
            }
            // For all cases update addefrom and sale agent from the invoice
            // May happen staff is not logged in and these values to be 0
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'invoices', [
                'addedfrom'  => $_order->addedfrom,
                'sale_agent' => $_order->sale_agent,
            ]);

            // Update order with the new invoice data and set to status accepted
            $this->db->where('id', $_order->id);
            $this->db->update(db_prefix() . 'orders', [
                'invoiced_date' => date('Y-m-d H:i:s'),
                'invoiceid'     => $id,
                'status'        => 4,
            ]);


            if (is_custom_fields_smart_transfer_enabled()) {
                $this->db->where('fieldto', 'order');
                $this->db->where('active', 1);
                $cfOrders = $this->db->get(db_prefix() . 'customfields')->result_array();
                foreach ($cfOrders as $field) {
                    $tmpSlug = explode('_', $field['slug'], 2);
                    if (isset($tmpSlug[1])) {
                        $this->db->where('fieldto', 'invoice');

                        $this->db->group_start();
                        $this->db->like('slug', 'invoice_' . $tmpSlug[1], 'after');
                        $this->db->where('type', $field['type']);
                        $this->db->where('options', $field['options']);
                        $this->db->where('active', 1);
                        $this->db->group_end();

                        // $this->db->where('slug LIKE "invoice_' . $tmpSlug[1] . '%" AND type="' . $field['type'] . '" AND options="' . $field['options'] . '" AND active=1');
                        $cfTransfer = $this->db->get(db_prefix() . 'customfields')->result_array();

                        // Don't make mistakes
                        // Only valid if 1 result returned
                        // + if field names similarity is equal or more then CUSTOM_FIELD_TRANSFER_SIMILARITY%
                        if (count($cfTransfer) == 1 && ((similarity($field['name'], $cfTransfer[0]['name']) * 100) >= CUSTOM_FIELD_TRANSFER_SIMILARITY)) {
                            $value = get_custom_field_value($_order->id, $field['id'], 'order', false);

                            if ($value == '') {
                                continue;
                            }

                            $this->db->insert(db_prefix() . 'customfieldsvalues', [
                                'relid'   => $id,
                                'fieldid' => $cfTransfer[0]['id'],
                                'fieldto' => 'invoice',
                                'value'   => $value,
                            ]);
                        }
                    }
                }
            }

            if ($client == false) {
                $this->log_order_activity($_order->id, 'order_activity_converted', false, serialize([
                    '<a href="' . admin_url('invoices/list_invoices/' . $id) . '">' . format_invoice_number($id) . '</a>',
                ]));
            }

            hooks()->do_action('order_converted_to_invoice', ['invoice_id' => $id, 'order_id' => $_order->id]);
        }

        return $id;
    }

    /**
     * Copy order
     * @param mixed $id order id to copy
     * @return mixed
     */
    public function copy($id)
    {
        $_order                       = $this->get($id);
        $new_order_data               = [];
        $new_order_data['clientid']   = $_order->clientid;
        $new_order_data['project_id'] = $_order->project_id;
        $new_order_data['number']     = get_option('next_order_number');
        $new_order_data['date']       = _d(date('Y-m-d'));
        $new_order_data['expirydate'] = null;

        if ($_order->expirydate && get_option('order_due_after') != 0) {
            $new_order_data['expirydate'] = _d(date('Y-m-d', strtotime('+' . get_option('order_due_after') . ' DAY', strtotime(date('Y-m-d')))));
        }

        $new_order_data['show_quantity_as'] = $_order->show_quantity_as;
        $new_order_data['currency']         = $_order->currency;
        $new_order_data['subtotal']         = $_order->subtotal;
        $new_order_data['total']            = $_order->total;
        $new_order_data['adminnote']        = $_order->adminnote;
        $new_order_data['adjustment']       = $_order->adjustment;
        $new_order_data['discount_percent'] = $_order->discount_percent;
        $new_order_data['discount_total']   = $_order->discount_total;
        $new_order_data['discount_type']    = $_order->discount_type;
        $new_order_data['terms']            = $_order->terms;
        $new_order_data['sale_agent']       = $_order->sale_agent;
        $new_order_data['reference_no']     = $_order->reference_no;
        // Since version 1.0.6
        $new_order_data['billing_street']   = clear_textarea_breaks($_order->billing_street);
        $new_order_data['billing_city']     = $_order->billing_city;
        $new_order_data['billing_state']    = $_order->billing_state;
        $new_order_data['billing_zip']      = $_order->billing_zip;
        $new_order_data['billing_country']  = $_order->billing_country;
        $new_order_data['shipping_street']  = clear_textarea_breaks($_order->shipping_street);
        $new_order_data['shipping_city']    = $_order->shipping_city;
        $new_order_data['shipping_state']   = $_order->shipping_state;
        $new_order_data['shipping_zip']     = $_order->shipping_zip;
        $new_order_data['shipping_country'] = $_order->shipping_country;
        if ($_order->include_shipping == 1) {
            $new_order_data['include_shipping'] = $_order->include_shipping;
        }
        $new_order_data['show_shipping_on_order'] = $_order->show_shipping_on_order;
        // Set to unpaid status automatically
        $new_order_data['status']     = 1;
        $new_order_data['clientnote'] = $_order->clientnote;
        $new_order_data['adminnote']  = '';
        $new_order_data['newitems']   = [];
        $custom_fields_items             = get_custom_fields('items');
        $key                             = 1;
        foreach ($_order->items as $item) {
            $new_order_data['newitems'][$key]['description']      = $item['description'];
            $new_order_data['newitems'][$key]['long_description'] = clear_textarea_breaks($item['long_description']);
            $new_order_data['newitems'][$key]['qty']              = $item['qty'];
            $new_order_data['newitems'][$key]['unit']             = $item['unit'];
            $new_order_data['newitems'][$key]['taxname']          = [];
            $taxes                                                   = get_order_item_taxes($item['id']);
            foreach ($taxes as $tax) {
                // tax name is in format TAX1|10.00
                array_push($new_order_data['newitems'][$key]['taxname'], $tax['taxname']);
            }
            $new_order_data['newitems'][$key]['rate']  = $item['rate'];
            $new_order_data['newitems'][$key]['order'] = $item['item_order'];
            foreach ($custom_fields_items as $cf) {
                $new_order_data['newitems'][$key]['custom_fields']['items'][$cf['id']] = get_custom_field_value($item['id'], $cf['id'], 'items', false);

                if (!defined('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST')) {
                    define('COPY_CUSTOM_FIELDS_LIKE_HANDLE_POST', true);
                }
            }
            $key++;
        }
        $id = $this->add($new_order_data);
        if ($id) {
            $custom_fields = get_custom_fields('order');
            foreach ($custom_fields as $field) {
                $value = get_custom_field_value($_order->id, $field['id'], 'order', false);
                if ($value == '') {
                    continue;
                }

                $this->db->insert(db_prefix() . 'customfieldsvalues', [
                    'relid'   => $id,
                    'fieldid' => $field['id'],
                    'fieldto' => 'order',
                    'value'   => $value,
                ]);
            }

            $tags = get_tags_in($_order->id, 'order');
            handle_tags_save($tags, $id, 'order');

            log_activity('Copied Order ' . format_order_number($_order->id));

            return $id;
        }

        return false;
    }

    /**
     * Performs orders totals status
     * @param array $data
     * @return array
     */
    public function get_orders_total($data)
    {
        $statuses            = $this->get_statuses();
        $has_permission_view = has_permission('orders', '', 'view');
        $this->load->model('currencies_model');
        if (isset($data['currency'])) {
            $currencyid = $data['currency'];
        } elseif (isset($data['customer_id']) && $data['customer_id'] != '') {
            $currencyid = $this->clients_model->get_customer_default_currency($data['customer_id']);
            if ($currencyid == 0) {
                $currencyid = $this->currencies_model->get_base_currency()->id;
            }
        } elseif (isset($data['project_id']) && $data['project_id'] != '') {
            $this->load->model('projects_model');
            $currencyid = $this->projects_model->get_currency($data['project_id'])->id;
        } else {
            $currencyid = $this->currencies_model->get_base_currency()->id;
        }

        $currency = get_currency($currencyid);
        $where    = '';
        if (isset($data['customer_id']) && $data['customer_id'] != '') {
            $where = ' AND clientid=' . $data['customer_id'];
        }

        if (isset($data['project_id']) && $data['project_id'] != '') {
            $where .= ' AND project_id=' . $data['project_id'];
        }

        if (!$has_permission_view) {
            $where .= ' AND ' . get_orders_where_sql_for_staff(get_staff_user_id());
        }

        $sql = 'SELECT';
        foreach ($statuses as $order_status) {
            $sql .= '(SELECT SUM(total) FROM ' . db_prefix() . 'orders WHERE status=' . $order_status;
            $sql .= ' AND currency =' . $this->db->escape_str($currencyid);
            if (isset($data['years']) && count($data['years']) > 0) {
                $sql .= ' AND YEAR(date) IN (' . implode(', ', array_map(function ($year) {
                    return get_instance()->db->escape_str($year);
                }, $data['years'])) . ')';
            } else {
                $sql .= ' AND YEAR(date) = ' . date('Y');
            }
            $sql .= $where;
            $sql .= ') as "' . $order_status . '",';
        }

        $sql     = substr($sql, 0, -1);
        $result  = $this->db->query($sql)->result_array();
        $_result = [];
        $i       = 1;
        foreach ($result as $key => $val) {
            foreach ($val as $status => $total) {
                $_result[$i]['total']         = $total;
                $_result[$i]['symbol']        = $currency->symbol;
                $_result[$i]['currency_name'] = $currency->name;
                $_result[$i]['status']        = $status;
                $i++;
            }
        }
        $_result['currencyid'] = $currencyid;

        return $_result;
    }

    /**
     * Insert new order to database
     * @param array $data invoiec data
     * @return mixed - false if not insert, order ID if succes
     */
    public function add($data)
    {
        $data['datecreated'] = date('Y-m-d H:i:s');

        $data['addedfrom'] = get_staff_user_id();

        $data['prefix'] = get_option('order_prefix');

        $data['number_format'] = get_option('order_number_format');

        $save_and_send = isset($data['save_and_send']);

        $orderRequestID = false;
        if (isset($data['order_request_id'])) {
            $orderRequestID = $data['order_request_id'];
            unset($data['order_request_id']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            unset($data['custom_fields']);
        }

        $data['hash'] = app_generate_hash();
        $tags         = isset($data['tags']) ? $data['tags'] : '';

        $items = [];
        if (isset($data['newitems'])) {
            $items = $data['newitems'];
            unset($data['newitems']);
        }

        $data = $this->map_shipping_columns($data);

        $data['billing_street'] = trim($data['billing_street']);
        $data['billing_street'] = nl2br($data['billing_street']);

        if (isset($data['shipping_street'])) {
            $data['shipping_street'] = trim($data['shipping_street']);
            $data['shipping_street'] = nl2br($data['shipping_street']);
        }

        $hook = hooks()->apply_filters('before_order_added', [
            'data'  => $data,
            'items' => $items,
        ]);

        $data  = $hook['data'];
        $items = $hook['items'];

        $this->db->insert(db_prefix() . 'orders', $data);
        $insert_id = $this->db->insert_id();

        if ($insert_id) {
            // Update next order number in settings
            $this->db->where('name', 'next_order_number');
            $this->db->set('value', 'value+1', false);
            $this->db->update(db_prefix() . 'options');

            if ($orderRequestID !== false && $orderRequestID != '') {
                $this->load->model('order_request_model');
                $completedStatus = $this->order_request_model->get_status_by_flag('completed');
                $this->order_request_model->update_request_status([
                    'requestid' => $orderRequestID,
                    'status'    => $completedStatus->id,
                ]);
            }

            if (isset($custom_fields)) {
                handle_custom_fields_post($insert_id, $custom_fields);
            }

            handle_tags_save($tags, $insert_id, 'order');

            foreach ($items as $key => $item) {
                if ($itemid = add_new_sales_item_post($item, $insert_id, 'order')) {
                    _maybe_insert_post_item_tax($itemid, $item, $insert_id, 'order');
                }
            }

            update_sales_total_tax_column($insert_id, 'order', db_prefix() . 'orders');
            $this->log_order_activity($insert_id, 'order_activity_created');

            hooks()->do_action('after_order_added', $insert_id);

            if ($save_and_send === true) {
                $this->send_order_to_client($insert_id, '', true, '', true);
            }

            return $insert_id;
        }

        return false;
    }

    /**
     * Get item by id
     * @param mixed $id item id
     * @return object
     */
    public function get_order_item($id)
    {
        $this->db->where('id', $id);

        return $this->db->get(db_prefix() . 'itemable')->row();
    }

    /**
     * Update order data
     * @param array $data order data
     * @param mixed $id orderid
     * @return boolean
     */
    public function update($data, $id)
    {
        $affectedRows = 0;

        $data['number'] = trim($data['number']);

        $original_order = $this->get($id);

        $original_status = $original_order->status;

        $original_number = $original_order->number;

        $original_number_formatted = format_order_number($id);

        $save_and_send = isset($data['save_and_send']);

        $items = [];
        if (isset($data['items'])) {
            $items = $data['items'];
            unset($data['items']);
        }

        $newitems = [];
        if (isset($data['newitems'])) {
            $newitems = $data['newitems'];
            unset($data['newitems']);
        }

        if (isset($data['custom_fields'])) {
            $custom_fields = $data['custom_fields'];
            if (handle_custom_fields_post($id, $custom_fields)) {
                $affectedRows++;
            }
            unset($data['custom_fields']);
        }

        if (isset($data['tags'])) {
            if (handle_tags_save($data['tags'], $id, 'order')) {
                $affectedRows++;
            }
        }

        $data['billing_street'] = trim($data['billing_street']);
        $data['billing_street'] = nl2br($data['billing_street']);

        $data['shipping_street'] = trim($data['shipping_street']);
        $data['shipping_street'] = nl2br($data['shipping_street']);

        $data = $this->map_shipping_columns($data);

        $hook = hooks()->apply_filters('before_order_updated', [
            'data'          => $data,
            'items'         => $items,
            'newitems'      => $newitems,
            'removed_items' => isset($data['removed_items']) ? $data['removed_items'] : [],
        ], $id);

        $data                  = $hook['data'];
        $items                 = $hook['items'];
        $newitems              = $hook['newitems'];
        $data['removed_items'] = $hook['removed_items'];

        // Delete items checked to be removed from database
        foreach ($data['removed_items'] as $remove_item_id) {
            $original_item = $this->get_order_item($remove_item_id);
            if (handle_removed_sales_item_post($remove_item_id, 'order')) {
                $affectedRows++;
                $this->log_order_activity($id, 'invoice_order_activity_removed_item', false, serialize([
                    $original_item->description,
                ]));
            }
        }

        unset($data['removed_items']);

        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'orders', $data);

        if ($this->db->affected_rows() > 0) {
            // Check for status change
            if ($original_status != $data['status']) {
                $this->log_order_activity($original_order->id, 'not_order_status_updated', false, serialize([
                    '<original_status>' . $original_status . '</original_status>',
                    '<new_status>' . $data['status'] . '</new_status>',
                ]));
                if ($data['status'] == 2) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'orders', ['sent' => 1, 'datesend' => date('Y-m-d H:i:s')]);
                }
            }
            if ($original_number != $data['number']) {
                $this->log_order_activity($original_order->id, 'order_activity_number_changed', false, serialize([
                    $original_number_formatted,
                    format_order_number($original_order->id),
                ]));
            }
            $affectedRows++;
        }

        foreach ($items as $key => $item) {
            $original_item = $this->get_order_item($item['itemid']);

            if (update_sales_item_post($item['itemid'], $item, 'item_order')) {
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'unit')) {
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'rate')) {
                $this->log_order_activity($id, 'invoice_order_activity_updated_item_rate', false, serialize([
                    $original_item->rate,
                    $item['rate'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'qty')) {
                $this->log_order_activity($id, 'invoice_order_activity_updated_qty_item', false, serialize([
                    $item['description'],
                    $original_item->qty,
                    $item['qty'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'description')) {
                $this->log_order_activity($id, 'invoice_order_activity_updated_item_short_description', false, serialize([
                    $original_item->description,
                    $item['description'],
                ]));
                $affectedRows++;
            }

            if (update_sales_item_post($item['itemid'], $item, 'long_description')) {
                $this->log_order_activity($id, 'invoice_order_activity_updated_item_long_description', false, serialize([
                    $original_item->long_description,
                    $item['long_description'],
                ]));
                $affectedRows++;
            }

            if (isset($item['custom_fields'])) {
                if (handle_custom_fields_post($item['itemid'], $item['custom_fields'])) {
                    $affectedRows++;
                }
            }

            if (!isset($item['taxname']) || (isset($item['taxname']) && count($item['taxname']) == 0)) {
                if (delete_taxes_from_item($item['itemid'], 'order')) {
                    $affectedRows++;
                }
            } else {
                $item_taxes        = get_order_item_taxes($item['itemid']);
                $_item_taxes_names = [];
                foreach ($item_taxes as $_item_tax) {
                    array_push($_item_taxes_names, $_item_tax['taxname']);
                }

                $i = 0;
                foreach ($_item_taxes_names as $_item_tax) {
                    if (!in_array($_item_tax, $item['taxname'])) {
                        $this->db->where('id', $item_taxes[$i]['id'])
                            ->delete(db_prefix() . 'item_tax');
                        if ($this->db->affected_rows() > 0) {
                            $affectedRows++;
                        }
                    }
                    $i++;
                }
                if (_maybe_insert_post_item_tax($item['itemid'], $item, $id, 'order')) {
                    $affectedRows++;
                }
            }
        }

        foreach ($newitems as $key => $item) {
            if ($new_item_added = add_new_sales_item_post($item, $id, 'order')) {
                _maybe_insert_post_item_tax($new_item_added, $item, $id, 'order');
                $this->log_order_activity($id, 'invoice_order_activity_added_item', false, serialize([
                    $item['description'],
                ]));
                $affectedRows++;
            }
        }

        if ($affectedRows > 0) {
            update_sales_total_tax_column($id, 'order', db_prefix() . 'orders');
        }

        if ($save_and_send === true) {
            $this->send_order_to_client($id, '', true, '', true);
        }

        if ($affectedRows > 0) {
            hooks()->do_action('after_order_updated', $id);

            return true;
        }

        return false;
    }

    public function mark_action_status($action, $id, $client = false)
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'orders', [
            'status' => $action,
        ]);

        $notifiedUsers = [];

        if ($this->db->affected_rows() > 0) {
            $order = $this->get($id);
            if ($client == true) {
                $this->db->where('staffid', $order->addedfrom);
                $this->db->or_where('staffid', $order->sale_agent);
                $staff_order = $this->db->get(db_prefix() . 'staff')->result_array();

                $invoiceid = false;
                $invoiced  = false;

                $contact_id = !is_client_logged_in()
                    ? get_primary_contact_user_id($order->clientid)
                    : get_contact_user_id();

                if ($action == 4) {
                    if (get_option('order_auto_convert_to_invoice_on_client_accept') == 1) {
                        $invoiceid = $this->convert_to_invoice($id, true);
                        $this->load->model('invoices_model');
                        if ($invoiceid) {
                            $invoiced = true;
                            $invoice  = $this->invoices_model->get($invoiceid);
                            $this->log_order_activity($id, 'order_activity_client_accepted_and_converted', true, serialize([
                                '<a href="' . admin_url('invoices/list_invoices/' . $invoiceid) . '">' . format_invoice_number($invoice->id) . '</a>',
                            ]));
                        }
                    } else {
                        $this->log_order_activity($id, 'order_activity_client_accepted', true);
                    }

                    // Send thank you email to all contacts with permission orders
                    $contacts = $this->clients_model->get_contacts($order->clientid, ['active' => 1, 'order_emails' => 1]);

                    foreach ($contacts as $contact) {
                        send_mail_template('order_accepted_to_customer', $order, $contact);
                    }

                    foreach ($staff_order as $member) {
                        $notified = add_notification([
                            'fromcompany'     => true,
                            'touserid'        => $member['staffid'],
                            'description'     => 'not_order_customer_accepted',
                            'link'            => 'orders/list_orders/' . $id,
                            'additional_data' => serialize([
                                format_order_number($order->id),
                            ]),
                        ]);

                        if ($notified) {
                            array_push($notifiedUsers, $member['staffid']);
                        }

                        send_mail_template('order_accepted_to_staff', $order, $member['email'], $contact_id);
                    }

                    pusher_trigger_notification($notifiedUsers);
                    hooks()->do_action('order_accepted', $id);

                    return [
                        'invoiced'  => $invoiced,
                        'invoiceid' => $invoiceid,
                    ];
                } elseif ($action == 3) {
                    foreach ($staff_order as $member) {
                        $notified = add_notification([
                            'fromcompany'     => true,
                            'touserid'        => $member['staffid'],
                            'description'     => 'not_order_customer_declined',
                            'link'            => 'orders/list_orders/' . $id,
                            'additional_data' => serialize([
                                format_order_number($order->id),
                            ]),
                        ]);

                        if ($notified) {
                            array_push($notifiedUsers, $member['staffid']);
                        }
                        // Send staff email notification that customer declined order
                        send_mail_template('order_declined_to_staff', $order, $member['email'], $contact_id);
                    }

                    pusher_trigger_notification($notifiedUsers);
                    $this->log_order_activity($id, 'order_activity_client_declined', true);
                    hooks()->do_action('order_declined', $id);

                    return [
                        'invoiced'  => $invoiced,
                        'invoiceid' => $invoiceid,
                    ];
                }
            } else {
                if ($action == 2) {
                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'orders', ['sent' => 1, 'datesend' => date('Y-m-d H:i:s')]);
                }
                // Admin marked order
                $this->log_order_activity($id, 'order_activity_marked', false, serialize([
                    '<status>' . $action . '</status>',
                ]));

                return true;
            }
        }

        return false;
    }

    /**
     * Get order attachments
     * @param mixed $order_id
     * @param string $id attachment id
     * @return mixed
     */
    public function get_attachments($order_id, $id = '')
    {
        // If is passed id get return only 1 attachment
        if (is_numeric($id)) {
            $this->db->where('id', $id);
        } else {
            $this->db->where('rel_id', $order_id);
        }
        $this->db->where('rel_type', 'order');
        $result = $this->db->get(db_prefix() . 'files');
        if (is_numeric($id)) {
            return $result->row();
        }

        return $result->result_array();
    }

    /**
     *  Delete order attachment
     * @param mixed $id attachmentid
     * @return  boolean
     */
    public function delete_attachment($id)
    {
        $attachment = $this->get_attachments('', $id);
        $deleted    = false;
        if ($attachment) {
            if (empty($attachment->external)) {
                unlink(get_upload_path_by_type('order') . $attachment->rel_id . '/' . $attachment->file_name);
            }
            $this->db->where('id', $attachment->id);
            $this->db->delete(db_prefix() . 'files');
            if ($this->db->affected_rows() > 0) {
                $deleted = true;
                log_activity('Order Attachment Deleted [OrderID: ' . $attachment->rel_id . ']');
            }

            if (is_dir(get_upload_path_by_type('order') . $attachment->rel_id)) {
                // Check if no attachments left, so we can delete the folder also
                $other_attachments = list_files(get_upload_path_by_type('order') . $attachment->rel_id);
                if (count($other_attachments) == 0) {
                    // okey only index.html so we can delete the folder also
                    delete_dir(get_upload_path_by_type('order') . $attachment->rel_id);
                }
            }
        }

        return $deleted;
    }

    /**
     * Delete order items and all connections
     * @param mixed $id orderid
     * @return boolean
     */
    public function delete($id, $simpleDelete = false)
    {
        if (get_option('delete_only_on_last_order') == 1 && $simpleDelete == false) {
            if (!is_last_order($id)) {
                return false;
            }
        }
        $order = $this->get($id);
        if (!is_null($order->invoiceid) && $simpleDelete == false) {
            return [
                'is_invoiced_order_delete_error' => true,
            ];
        }
        hooks()->do_action('before_order_deleted', $id);

        $number = format_order_number($id);

        $this->clear_signature($id);

        $this->db->where('id', $id);
        $this->db->delete(db_prefix() . 'orders');

        if ($this->db->affected_rows() > 0) {
            if (!is_null($order->short_link)) {
                app_archive_short_link($order->short_link);
            }

            if (get_option('order_number_decrement_on_delete') == 1 && $simpleDelete == false) {
                $current_next_order_number = get_option('next_order_number');
                if ($current_next_order_number > 1) {
                    // Decrement next order number to
                    $this->db->where('name', 'next_order_number');
                    $this->db->set('value', 'value-1', false);
                    $this->db->update(db_prefix() . 'options');
                }
            }

            if (total_rows(db_prefix() . 'proposals', [
                    'order_id' => $id,
                ]) > 0) {
                $this->db->where('order_id', $id);
                $order = $this->db->get(db_prefix() . 'proposals')->row();
                $this->db->where('id', $order->id);
                $this->db->update(db_prefix() . 'proposals', [
                    'order_id'    => null,
                    'date_converted' => null,
                ]);
            }

            delete_tracked_emails($id, 'order');

            $this->db->where('relid IN (SELECT id from ' . db_prefix() . 'itemable WHERE rel_type="order" AND rel_id="' . $this->db->escape_str($id) . '")');
            $this->db->where('fieldto', 'items');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'order');
            $this->db->delete(db_prefix() . 'notes');

            $this->db->where('rel_type', 'order');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'views_tracking');

            $this->db->where('rel_type', 'order');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'taggables');

            $this->db->where('rel_type', 'order');
            $this->db->where('rel_id', $id);
            $this->db->delete(db_prefix() . 'reminders');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'order');
            $this->db->delete(db_prefix() . 'itemable');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'order');
            $this->db->delete(db_prefix() . 'item_tax');

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'order');
            $this->db->delete(db_prefix() . 'sales_activity');

            // Delete the custom field values
            $this->db->where('relid', $id);
            $this->db->where('fieldto', 'order');
            $this->db->delete(db_prefix() . 'customfieldsvalues');

            $attachments = $this->get_attachments($id);
            foreach ($attachments as $attachment) {
                $this->delete_attachment($attachment['id']);
            }

            $this->db->where('rel_id', $id);
            $this->db->where('rel_type', 'order');
            $this->db->delete('orderd_emails');

            // Get related tasks
            $this->db->where('rel_type', 'order');
            $this->db->where('rel_id', $id);
            $tasks = $this->db->get(db_prefix() . 'tasks')->result_array();
            foreach ($tasks as $task) {
                $this->tasks_model->delete_task($task['id']);
            }
            if ($simpleDelete == false) {
                log_activity('Orders Deleted [Number: ' . $number . ']');
            }

            hooks()->do_action('after_order_deleted', $id);

            return true;
        }

        return false;
    }

    /**
     * Set order to sent when email is successfuly sended to client
     * @param mixed $id orderid
     */
    public function set_order_sent($id, $emails_sent = [])
    {
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'orders', [
            'sent'     => 1,
            'datesend' => date('Y-m-d H:i:s'),
        ]);

        $this->log_order_activity($id, 'invoice_order_activity_sent_to_client', false, serialize([
            '<custom_data>' . implode(', ', $emails_sent) . '</custom_data>',
        ]));

        // Update order status to sent
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'orders', [
            'status' => 2,
        ]);

        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'order');
        $this->db->delete('orderd_emails');
    }

    /**
     * Send expiration reminder to customer
     * @param mixed $id order id
     * @return boolean
     */
    public function send_expiry_reminder($id)
    {
        $order        = $this->get($id);
        $order_number = format_order_number($order->id);
        set_mailing_constant();
        $pdf              = order_pdf($order);
        $attach           = $pdf->Output($order_number . '.pdf', 'S');
        $emails_sent      = [];
        $sms_sent         = false;
        $sms_reminder_log = [];

        // For all cases update this to prevent sending multiple reminders eq on fail
        $this->db->where('id', $id);
        $this->db->update(db_prefix() . 'orders', [
            'is_expiry_notified' => 1,
        ]);

        $contacts = $this->clients_model->get_contacts($order->clientid, ['active' => 1, 'order_emails' => 1]);

        foreach ($contacts as $contact) {
            $template = mail_template('order_expiration_reminder', $order, $contact);

            $merge_fields = $template->get_merge_fields();

            $template->add_attachment([
                'attachment' => $attach,
                'filename'   => str_replace('/', '-', $order_number . '.pdf'),
                'type'       => 'application/pdf',
            ]);

            if ($template->send()) {
                array_push($emails_sent, $contact['email']);
            }

            if (can_send_sms_based_on_creation_date($order->datecreated)
                && $this->app_sms->trigger(SMS_TRIGGER_ESTIMATE_EXP_REMINDER, $contact['phonenumber'], $merge_fields)) {
                $sms_sent = true;
                array_push($sms_reminder_log, $contact['firstname'] . ' (' . $contact['phonenumber'] . ')');
            }
        }

        if (count($emails_sent) > 0 || $sms_sent) {
            if (count($emails_sent) > 0) {
                $this->log_order_activity($id, 'not_expiry_reminder_sent', false, serialize([
                    '<custom_data>' . implode(', ', $emails_sent) . '</custom_data>',
                ]));
            }

            if ($sms_sent) {
                $this->log_order_activity($id, 'sms_reminder_sent_to', false, serialize([
                    implode(', ', $sms_reminder_log),
                ]));
            }

            return true;
        }

        return false;
    }

    /**
     * Send order to client
     * @param mixed $id orderid
     * @param string $template email template to sent
     * @param boolean $attachpdf attach order pdf or not
     * @return boolean
     */
    public function send_order_to_client($id, $template_name = '', $attachpdf = true, $cc = '', $manually = false)
    {
        $order = $this->get($id);

        if ($template_name == '') {
            $template_name = $order->sent == 0 ?
                'order_send_to_customer' :
                'order_send_to_customer_already_sent';
        }

        $order_number = format_order_number($order->id);

        $emails_sent = [];
        $send_to     = [];

        // Manually is used when sending the order via add/edit area button Save & Send
        if (!DEFINED('CRON') && $manually === false) {
            $send_to = $this->input->post('sent_to');
        } elseif (isset($GLOBALS['orderd_email_contacts'])) {
            $send_to = $GLOBALS['orderd_email_contacts'];
        } else {
            $contacts = $this->clients_model->get_contacts(
                $order->clientid,
                ['active' => 1, 'order_emails' => 1]
            );

            foreach ($contacts as $contact) {
                array_push($send_to, $contact['id']);
            }
        }

        $status_auto_updated = false;
        $status_now          = $order->status;

        if (is_array($send_to) && count($send_to) > 0) {
            $i = 0;

            // Auto update status to sent in case when user sends the order is with status draft
            if ($status_now == 1) {
                $this->db->where('id', $order->id);
                $this->db->update(db_prefix() . 'orders', [
                    'status' => 2,
                ]);
                $status_auto_updated = true;
            }

            if ($attachpdf) {
                $_pdf_order = $this->get($order->id);
                set_mailing_constant();
                $pdf = order_pdf($_pdf_order);

                $attach = $pdf->Output($order_number . '.pdf', 'S');
            }

            foreach ($send_to as $contact_id) {
                if ($contact_id != '') {
                    // Send cc only for the first contact
                    if (!empty($cc) && $i > 0) {
                        $cc = '';
                    }

                    $contact = $this->clients_model->get_contact($contact_id);

                    if (!$contact) {
                        continue;
                    }

                    $template = mail_template($template_name, $order, $contact, $cc);

                    if ($attachpdf) {
                        $hook = hooks()->apply_filters('send_order_to_customer_file_name', [
                            'file_name' => str_replace('/', '-', $order_number . '.pdf'),
                            'order'  => $_pdf_order,
                        ]);

                        $template->add_attachment([
                            'attachment' => $attach,
                            'filename'   => $hook['file_name'],
                            'type'       => 'application/pdf',
                        ]);
                    }

                    if ($template->send()) {
                        array_push($emails_sent, $contact->email);
                    }
                }
                $i++;
            }
        } else {
            return false;
        }

        if (count($emails_sent) > 0) {
            $this->set_order_sent($id, $emails_sent);
            hooks()->do_action('order_sent', $id);

            return true;
        }

        if ($status_auto_updated) {
            // Order not send to customer but the status was previously updated to sent now we need to revert back to draft
            $this->db->where('id', $order->id);
            $this->db->update(db_prefix() . 'orders', [
                'status' => 1,
            ]);
        }

        return false;
    }

    /**
     * All order activity
     * @param mixed $id orderid
     * @return array
     */
    public function get_order_activity($id)
    {
        $this->db->where('rel_id', $id);
        $this->db->where('rel_type', 'order');
        $this->db->order_by('date', 'asc');

        return $this->db->get(db_prefix() . 'sales_activity')->result_array();
    }

    /**
     * Log order activity to database
     * @param mixed $id orderid
     * @param string $description activity description
     */
    public function log_order_activity($id, $description = '', $client = false, $additional_data = '')
    {
        $staffid   = get_staff_user_id();
        $full_name = get_staff_full_name(get_staff_user_id());
        if (DEFINED('CRON')) {
            $staffid   = '[CRON]';
            $full_name = '[CRON]';
        } elseif ($client == true) {
            $staffid   = null;
            $full_name = '';
        }

        $this->db->insert(db_prefix() . 'sales_activity', [
            'description'     => $description,
            'date'            => date('Y-m-d H:i:s'),
            'rel_id'          => $id,
            'rel_type'        => 'order',
            'staffid'         => $staffid,
            'full_name'       => $full_name,
            'additional_data' => $additional_data,
        ]);
    }

    /**
     * Updates pipeline order when drag and drop
     * @param mixe $data $_POST data
     * @return void
     */
    public function update_pipeline($data)
    {
        $this->mark_action_status($data['status'], $data['orderid']);
        AbstractKanban::updateOrder($data['order'], 'pipeline_order', 'orders', $data['status']);
    }

    /**
     * Get order unique year for filtering
     * @return array
     */
    public function get_orders_years()
    {
        return $this->db->query('SELECT DISTINCT(YEAR(date)) as year FROM ' . db_prefix() . 'orders ORDER BY year DESC')->result_array();
    }

    private function map_shipping_columns($data)
    {
        if (!isset($data['include_shipping'])) {
            foreach ($this->shipping_fields as $_s_field) {
                if (isset($data[$_s_field])) {
                    $data[$_s_field] = null;
                }
            }
            $data['show_shipping_on_order'] = 1;
            $data['include_shipping']          = 0;
        } else {
            $data['include_shipping'] = 1;
            // set by default for the next time to be checked
            if (isset($data['show_shipping_on_order']) && ($data['show_shipping_on_order'] == 1 || $data['show_shipping_on_order'] == 'on')) {
                $data['show_shipping_on_order'] = 1;
            } else {
                $data['show_shipping_on_order'] = 0;
            }
        }

        return $data;
    }

    public function do_kanban_query($status, $search = '', $page = 1, $sort = [], $count = false)
    {
        _deprecated_function('Orders_model::do_kanban_query', '2.9.2', 'OrdersPipeline class');

        $kanBan = (new OrdersPipeline($status))
            ->search($search)
            ->page($page)
            ->sortBy($sort['sort'] ?? null, $sort['sort_by'] ?? null);

        if ($count) {
            return $kanBan->countAll();
        }

        return $kanBan->get();
    }

    /**
     * Get the orders about to expired in the given days
     *
     * @param  integer|null $staffId
     * @param  integer $days
     *
     * @return array
     */
    public function get_orders_this_week($staffId = null, $days = 7)
    {
        $order_number_of_date = get_option('order_number_of_date');
        
        if($order_number_of_date > 0){
            $days = $order_number_of_date;
        }
        
        $diff1 = date('Y-m-d', strtotime('-' . 0 . ' days'));
        $diff2 = date('Y-m-d', strtotime('+' . $days . ' days'));

        if ($staffId && ! staff_can('view', 'orders', $staffId)) {
            $this->db->where(db_prefix() . 'orders.addedfrom', $staffId);
        }

        $this->db->select([db_prefix() . 'orders.id', db_prefix() . 'orders.number', db_prefix() . 'clients.userid', db_prefix() . 'clients.company', db_prefix() . 'projects.id projects_id', db_prefix() . 'projects.name', db_prefix() . 'orders.date', db_prefix() . 'orders.status']);
        $this->db->join(db_prefix() . 'clients', db_prefix() . 'clients.userid = ' . db_prefix() . 'orders.clientid', 'left');
        $this->db->join(db_prefix() . 'projects', db_prefix() . 'projects.id = ' . db_prefix() . 'orders.project_id', 'left');
        $this->db->where('date IS NOT NULL');
        $this->db->where('date >=', $diff1);
        $this->db->where('date <=', $diff2);

        //return $this->db->get_compiled_select(db_prefix() . 'orders');

        return $this->db->get(db_prefix() . 'orders')->result_array();
    }
}
