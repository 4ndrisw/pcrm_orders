<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order extends ClientsController
{
    public function index($id, $hash)
    {
        check_order_restrictions($id, $hash);
        $order = $this->orders_model->get($id);

        if (!is_client_logged_in()) {
            load_client_language($order->clientid);
        }

        $identity_confirmation_enabled = get_option('order_accept_identity_confirmation');

        if ($this->input->post('order_action')) {
            $action = $this->input->post('order_action');

            // Only decline and accept allowed
            if ($action == 4 || $action == 3) {
                $success = $this->orders_model->mark_action_status($action, $id, true);

                $redURL   = $this->uri->uri_string();
                $accepted = false;
                if (is_array($success) && $success['invoiced'] == true) {
                    $accepted = true;
                    $invoice  = $this->invoices_model->get($success['invoiceid']);
                    set_alert('success', _l('clients_order_invoiced_successfully'));
                    $redURL = site_url('invoice/' . $invoice->id . '/' . $invoice->hash);
                } elseif (is_array($success) && $success['invoiced'] == false || $success === true) {
                    if ($action == 4) {
                        $accepted = true;
                        set_alert('success', _l('clients_order_accepted_not_invoiced'));
                    } else {
                        set_alert('success', _l('clients_order_declined'));
                    }
                } else {
                    set_alert('warning', _l('clients_order_failed_action'));
                }
                if ($action == 4 && $accepted = true) {
                    process_digital_signature_image($this->input->post('signature', false), ESTIMATE_ATTACHMENTS_FOLDER . $id);

                    $this->db->where('id', $id);
                    $this->db->update(db_prefix() . 'orders', get_acceptance_info_array());
                }
            }
            redirect($redURL);
        }
        // Handle Order PDF generator
        if ($this->input->post('orderpdf')) {
            try {
                $pdf = order_pdf($order);
            } catch (Exception $e) {
                echo $e->getMessage();
                die;
            }

            $order_number = format_order_number($order->id);
            $companyname     = get_option('invoice_company_name');
            if ($companyname != '') {
                $order_number .= '-' . mb_strtoupper(slug_it($companyname), 'UTF-8');
            }

            $filename = hooks()->apply_filters('customers_area_download_order_filename', mb_strtoupper(slug_it($order_number), 'UTF-8') . '.pdf', $order);

            $pdf->Output($filename, 'D');
            die();
        }
        $this->load->library('app_number_to_word', [
            'clientid' => $order->clientid,
        ], 'numberword');

        $this->app_scripts->theme('sticky-js', 'assets/plugins/sticky/sticky.js');

        $data['title'] = format_order_number($order->id);
        $this->disableNavigation();
        $this->disableSubMenu();
        $data['hash']                          = $hash;
        $data['can_be_accepted']               = false;
        $data['order']                      = hooks()->apply_filters('order_html_pdf_data', $order);
        $data['bodyclass']                     = 'vieworder';
        $data['identity_confirmation_enabled'] = $identity_confirmation_enabled;
        if ($identity_confirmation_enabled == '1') {
            $data['bodyclass'] .= ' identity-confirmation';
        }
        $this->data($data);
        $this->view('orderhtml');
        add_views_tracking('order', $id);
        hooks()->do_action('order_html_viewed', $id);
        no_index_customers_area();
        $this->layout();
    }
}
