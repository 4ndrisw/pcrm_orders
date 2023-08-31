<?php

use app\services\orders\OrdersPipeline;

defined('BASEPATH') or exit('No direct script access allowed');

class Orders extends AdminController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('orders_model');
    }

    /* Get all orders in case user go on index page */
    public function index($id = '')
    {
        $this->list_orders($id);
    }

    /* List all orders datatables */
    public function list_orders($id = '')
    {
        if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && get_option('allow_staff_view_orders_assigned') == '0') {
            access_denied('orders');
        }

        $isPipeline = $this->session->userdata('order_pipeline') == 'true';

        $data['order_statuses'] = $this->orders_model->get_statuses();
        if ($isPipeline && !$this->input->get('status') && !$this->input->get('filter')) {
            $data['title']           = _l('orders_pipeline');
            $data['bodyclass']       = 'orders-pipeline orders-total-manual';
            $data['switch_pipeline'] = false;

            if (is_numeric($id)) {
                $data['orderid'] = $id;
            } else {
                $data['orderid'] = $this->session->flashdata('orderid');
            }

            $this->load->view('admin/orders/pipeline/manage', $data);
        } else {

            // Pipeline was initiated but user click from home page and need to show table only to filter
            if ($this->input->get('status') || $this->input->get('filter') && $isPipeline) {
                $this->pipeline(0, true);
            }

            $data['orderid']            = $id;
            $data['switch_pipeline']       = true;
            $data['title']                 = _l('orders');
            $data['bodyclass']             = 'orders-total-manual';
            $data['orders_years']       = $this->orders_model->get_orders_years();
            $data['orders_sale_agents'] = $this->orders_model->get_sale_agents();
            $this->load->view('admin/orders/manage', $data);
        }
    }

    public function table($clientid = '')
    {
        if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && get_option('allow_staff_view_orders_assigned') == '0') {
            ajax_access_denied();
        }

        $this->app->get_table_data(module_views_path('orders', 'admin/tables/orders'), [
            'clientid' => $clientid,
        ]);
    }

    /* Add new order or update existing */
    public function order($id = '')
    {
        if ($this->input->post()) {
            $order_data = $this->input->post();

            $save_and_send_later = false;
            if (isset($order_data['save_and_send_later'])) {
                unset($order_data['save_and_send_later']);
                $save_and_send_later = true;
            }

            if ($id == '') {
                if (!has_permission('orders', '', 'create')) {
                    access_denied('orders');
                }
                $id = $this->orders_model->add($order_data);

                if ($id) {
                    set_alert('success', _l('added_successfully', _l('order')));

                    $redUrl = admin_url('orders/list_orders/' . $id);

                    if ($save_and_send_later) {
                        $this->session->set_userdata('send_later', true);
                        // die(redirect($redUrl));
                    }

                    redirect(
                        !$this->set_order_pipeline_autoload($id) ? $redUrl : admin_url('orders/list_orders/')
                    );
                }
            } else {
                if (!has_permission('orders', '', 'edit')) {
                    access_denied('orders');
                }
                $success = $this->orders_model->update($order_data, $id);
                if ($success) {
                    set_alert('success', _l('updated_successfully', _l('order')));
                }
                if ($this->set_order_pipeline_autoload($id)) {
                    redirect(admin_url('orders/list_orders/'));
                } else {
                    redirect(admin_url('orders/list_orders/' . $id));
                }
            }
        }
        if ($id == '') {
            $title = _l('create_new_order');
        } else {
            $order = $this->orders_model->get($id);

            if (!$order || !user_can_view_order($id)) {
                blank_page(_l('order_not_found'));
            }

            $data['order'] = $order;
            $data['edit']     = true;
            $title            = _l('edit', _l('order_lowercase'));
        }

        if ($this->input->get('customer_id')) {
            $data['customer_id'] = $this->input->get('customer_id');
        }

        if ($this->input->get('order_request_id')) {
            $data['order_request_id'] = $this->input->get('order_request_id');
        }

        $this->load->model('taxes_model');
        $data['taxes'] = $this->taxes_model->get();
        $this->load->model('currencies_model');
        $data['currencies'] = $this->currencies_model->get();

        $data['base_currency'] = $this->currencies_model->get_base_currency();

        $this->load->model('invoice_items_model');

        $data['ajaxItems'] = false;
        if (total_rows(db_prefix() . 'items') <= ajax_on_total_items()) {
            $data['items'] = $this->invoice_items_model->get_grouped();
        } else {
            $data['items']     = [];
            $data['ajaxItems'] = true;
        }
        $data['items_groups'] = $this->invoice_items_model->get_groups();

        $data['staff']             = $this->staff_model->get('', ['active' => 1]);
        $data['order_statuses'] = $this->orders_model->get_statuses();
        $data['title']             = $title;
        $this->load->view('admin/orders/order', $data);
    }

    public function clear_signature($id)
    {
        if (has_permission('orders', '', 'delete')) {
            $this->orders_model->clear_signature($id);
        }

        redirect(admin_url('orders/list_orders/' . $id));
    }

    public function update_number_settings($id)
    {
        $response = [
            'success' => false,
            'message' => '',
        ];
        if (has_permission('orders', '', 'edit')) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'orders', [
                'prefix' => $this->input->post('prefix'),
            ]);
            if ($this->db->affected_rows() > 0) {
                $response['success'] = true;
                $response['message'] = _l('updated_successfully', _l('order'));
            }
        }

        echo json_encode($response);
        die;
    }

    public function validate_order_number()
    {
        $isedit          = $this->input->post('isedit');
        $number          = $this->input->post('number');
        $date            = $this->input->post('date');
        $original_number = $this->input->post('original_number');
        $number          = trim($number);
        $number          = ltrim($number, '0');

        if ($isedit == 'true') {
            if ($number == $original_number) {
                echo json_encode(true);
                die;
            }
        }

        if (total_rows(db_prefix() . 'orders', [
            'YEAR(date)' => date('Y', strtotime(to_sql_date($date))),
            'number' => $number,
        ]) > 0) {
            echo 'false';
        } else {
            echo 'true';
        }
    }

    public function delete_attachment($id)
    {
        $file = $this->misc_model->get_file($id);
        if ($file->staffid == get_staff_user_id() || is_admin()) {
            echo $this->orders_model->delete_attachment($id);
        } else {
            header('HTTP/1.0 400 Bad error');
            echo _l('access_denied');
            die;
        }
    }

    /* Get all order data used when user click on order number in a datatable left side*/
    public function get_order_data_ajax($id, $to_return = false)
    {
        if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && get_option('allow_staff_view_orders_assigned') == '0') {
            echo _l('access_denied');
            die;
        }

        if (!$id) {
            die('No order found');
        }

        $order = $this->orders_model->get($id);

        if (!$order || !user_can_view_order($id)) {
            echo _l('order_not_found');
            die;
        }

        $order->date       = _d($order->date);
        $order->expirydate = _d($order->expirydate);
        if ($order->invoiceid !== null) {
            $this->load->model('invoices_model');
            $order->invoice = $this->invoices_model->get($order->invoiceid);
        }

        if ($order->sent == 0) {
            $template_name = 'order_send_to_customer';
        } else {
            $template_name = 'order_send_to_customer_already_sent';
        }

        $data = prepare_mail_preview_data($template_name, $order->clientid);

        $data['activity']          = $this->orders_model->get_order_activity($id);
        $data['order']          = $order;
        $data['members']           = $this->staff_model->get('', ['active' => 1]);
        $data['order_statuses'] = $this->orders_model->get_statuses();
        $data['totalNotes']        = total_rows(db_prefix() . 'notes', ['rel_id' => $id, 'rel_type' => 'order']);

        $data['send_later'] = false;
        if ($this->session->has_userdata('send_later')) {
            $data['send_later'] = true;
            $this->session->unset_userdata('send_later');
        }

        if ($to_return == false) {
            $this->load->view('admin/orders/order_preview_template', $data);
        } else {
            return $this->load->view('admin/orders/order_preview_template', $data, true);
        }
    }

    public function get_orders_total()
    {
        if ($this->input->post()) {
            $data['totals'] = $this->orders_model->get_orders_total($this->input->post());

            $this->load->model('currencies_model');

            if (!$this->input->post('customer_id')) {
                $multiple_currencies = call_user_func('is_using_multiple_currencies', db_prefix() . 'orders');
            } else {
                $multiple_currencies = call_user_func('is_client_using_multiple_currencies', $this->input->post('customer_id'), db_prefix() . 'orders');
            }

            if ($multiple_currencies) {
                $data['currencies'] = $this->currencies_model->get();
            }

            $data['orders_years'] = $this->orders_model->get_orders_years();

            if (
                count($data['orders_years']) >= 1
                && !\app\services\utilities\Arr::inMultidimensional($data['orders_years'], 'year', date('Y'))
            ) {
                array_unshift($data['orders_years'], ['year' => date('Y')]);
            }

            $data['_currency'] = $data['totals']['currencyid'];
            unset($data['totals']['currencyid']);
            $this->load->view('admin/orders/orders_total_template', $data);
        }
    }

    public function add_note($rel_id)
    {
        if ($this->input->post() && user_can_view_order($rel_id)) {
            $this->misc_model->add_note($this->input->post(), 'order', $rel_id);
            echo $rel_id;
        }
    }

    public function add_order_note()
    {
        if ($this->input->post()) {
            echo json_encode([
                'success' => $this->orders_model->add_note($this->input->post()),
            ]);
        }
    }


    public function get_order_notes($id)
    {
        $data['notes'] = $this->orders_model->get_notes($id);
        $this->load->view('admin/orders/notes_template', $data);
    }
    
    public function get_notes($id)
    {
        if (user_can_view_order($id)) {
            $data['notes'] = $this->misc_model->get_notes($id, 'order');
            $this->load->view('admin/includes/sales_notes_template', $data);
        }
    }

    public function mark_action_status($status, $id)
    {
        if (!has_permission('orders', '', 'edit')) {
            access_denied('orders');
        }
        $success = $this->orders_model->mark_action_status($status, $id);
        if ($success) {
            set_alert('success', _l('order_status_changed_success'));
        } else {
            set_alert('danger', _l('order_status_changed_fail'));
        }
        if ($this->set_order_pipeline_autoload($id)) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('orders/list_orders/' . $id));
        }
    }

    public function send_expiry_reminder($id)
    {
        $canView = user_can_view_order($id);
        if (!$canView) {
            access_denied('Orders');
        } else {
            if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && $canView == false) {
                access_denied('Orders');
            }
        }

        $success = $this->orders_model->send_expiry_reminder($id);
        if ($success) {
            set_alert('success', _l('sent_expiry_reminder_success'));
        } else {
            set_alert('danger', _l('sent_expiry_reminder_fail'));
        }
        if ($this->set_order_pipeline_autoload($id)) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('orders/list_orders/' . $id));
        }
    }

    /* Send order to email */
    public function send_to_email($id)
    {
        $canView = user_can_view_order($id);
        if (!$canView) {
            access_denied('orders');
        } else {
            if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && $canView == false) {
                access_denied('orders');
            }
        }

        try {
            $success = $this->orders_model->send_order_to_client($id, '', $this->input->post('attach_pdf'), $this->input->post('cc'));
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        // In case client use another language
        load_admin_language();
        if ($success) {
            set_alert('success', _l('order_sent_to_client_success'));
        } else {
            set_alert('danger', _l('order_sent_to_client_fail'));
        }
        if ($this->set_order_pipeline_autoload($id)) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('orders/list_orders/' . $id));
        }
    }

    /* Convert order to invoice */
    public function convert_to_invoice($id)
    {
        if (!has_permission('invoices', '', 'create')) {
            access_denied('invoices');
        }
        if (!$id) {
            die('No order found');
        }
        $draft_invoice = false;
        if ($this->input->get('save_as_draft')) {
            $draft_invoice = true;
        }
        $invoiceid = $this->orders_model->convert_to_invoice($id, false, $draft_invoice);
        if ($invoiceid) {
            set_alert('success', _l('order_convert_to_invoice_successfully'));
            redirect(admin_url('invoices/list_invoices/' . $invoiceid));
        } else {
            if ($this->session->has_userdata('order_pipeline') && $this->session->userdata('order_pipeline') == 'true') {
                $this->session->set_flashdata('orderid', $id);
            }
            if ($this->set_order_pipeline_autoload($id)) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect(admin_url('orders/list_orders/' . $id));
            }
        }
    }

    public function copy($id)
    {
        if (!has_permission('orders', '', 'create')) {
            access_denied('orders');
        }
        if (!$id) {
            die('No order found');
        }
        $new_id = $this->orders_model->copy($id);
        if ($new_id) {
            set_alert('success', _l('order_copied_successfully'));
            if ($this->set_order_pipeline_autoload($new_id)) {
                redirect($_SERVER['HTTP_REFERER']);
            } else {
                redirect(admin_url('orders/order/' . $new_id));
            }
        }
        set_alert('danger', _l('order_copied_fail'));
        if ($this->set_order_pipeline_autoload($id)) {
            redirect($_SERVER['HTTP_REFERER']);
        } else {
            redirect(admin_url('orders/order/' . $id));
        }
    }

    /* Delete order */
    public function delete($id)
    {
        if (!has_permission('orders', '', 'delete')) {
            access_denied('orders');
        }
        if (!$id) {
            redirect(admin_url('orders/list_orders'));
        }
        $success = $this->orders_model->delete($id);
        if (is_array($success)) {
            set_alert('warning', _l('is_invoiced_order_delete_error'));
        } elseif ($success == true) {
            set_alert('success', _l('deleted', _l('order')));
        } else {
            set_alert('warning', _l('problem_deleting', _l('order_lowercase')));
        }
        redirect(admin_url('orders/list_orders'));
    }

    public function clear_acceptance_info($id)
    {
        if (is_admin()) {
            $this->db->where('id', $id);
            $this->db->update(db_prefix() . 'orders', get_acceptance_info_array(true));
        }

        redirect(admin_url('orders/list_orders/' . $id));
    }

    /* Generates order PDF and senting to email  */
    public function pdf($id)
    {
        $canView = user_can_view_order($id);
        if (!$canView) {
            access_denied('Orders');
        } else {
            if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && $canView == false) {
                access_denied('Orders');
            }
        }
        if (!$id) {
            redirect(admin_url('orders/list_orders'));
        }
        $order        = $this->orders_model->get($id);
        $order_number = format_order_number($order->id);

        try {
            $pdf = order_pdf($order);
        } catch (Exception $e) {
            $message = $e->getMessage();
            echo $message;
            if (strpos($message, 'Unable to get the size of the image') !== false) {
                show_pdf_unable_to_get_image_size_error();
            }
            die;
        }

        $type = 'D';

        if ($this->input->get('output_type')) {
            $type = $this->input->get('output_type');
        }

        if ($this->input->get('print')) {
            $type = 'I';
        }

        $fileNameHookData = hooks()->apply_filters('order_file_name_admin_area', [
                            'file_name' => mb_strtoupper(slug_it($order_number)) . '.pdf',
                            'order'  => $order,
                        ]);

        $pdf->Output($fileNameHookData['file_name'], $type);
    }

    // Pipeline
    public function get_pipeline()
    {
        if (has_permission('orders', '', 'view') || has_permission('orders', '', 'view_own') || get_option('allow_staff_view_orders_assigned') == '1') {
            $data['order_statuses'] = $this->orders_model->get_statuses();
            $this->load->view('admin/orders/pipeline/pipeline', $data);
        }
    }

    public function pipeline_open($id)
    {
        $canView = user_can_view_order($id);
        if (!$canView) {
            access_denied('Orders');
        } else {
            if (!has_permission('orders', '', 'view') && !has_permission('orders', '', 'view_own') && $canView == false) {
                access_denied('Orders');
            }
        }

        $data['id']       = $id;
        $data['order'] = $this->get_order_data_ajax($id, true);
        $this->load->view('admin/orders/pipeline/order', $data);
    }

    public function update_pipeline()
    {
        if (has_permission('orders', '', 'edit')) {
            $this->orders_model->update_pipeline($this->input->post());
        }
    }

    public function pipeline($set = 0, $manual = false)
    {
        if ($set == 1) {
            $set = 'true';
        } else {
            $set = 'false';
        }
        $this->session->set_userdata([
            'order_pipeline' => $set,
        ]);
        if ($manual == false) {
            redirect(admin_url('orders/list_orders'));
        }
    }

    public function pipeline_load_more()
    {
        $status = $this->input->get('status');
        $page   = $this->input->get('page');

        $orders = (new OrdersPipeline($status))
            ->search($this->input->get('search'))
            ->sortBy(
                $this->input->get('sort_by'),
                $this->input->get('sort')
            )
            ->page($page)->get();

        foreach ($orders as $order) {
            $this->load->view('admin/orders/pipeline/_kanban_card', [
                'order' => $order,
                'status'   => $status,
            ]);
        }
    }

    public function set_order_pipeline_autoload($id)
    {
        if ($id == '') {
            return false;
        }

        if ($this->session->has_userdata('order_pipeline')
                && $this->session->userdata('order_pipeline') == 'true') {
            $this->session->set_flashdata('orderid', $id);

            return true;
        }

        return false;
    }

    public function get_due_date()
    {
        if ($this->input->post()) {
            $date    = $this->input->post('date');
            $duedate = '';
            if (get_option('order_due_after') != 0) {
                $date    = to_sql_date($date);
                $d       = date('Y-m-d', strtotime('+' . get_option('order_due_after') . ' DAY', strtotime($date)));
                $duedate = _d($d);
                echo $duedate;
            }
        }
    }
}
