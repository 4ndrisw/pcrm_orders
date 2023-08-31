<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_send_to_customer_already_sent extends App_mail_template
{
    protected $for = 'customer';

    protected $order;

    protected $contact;

    public $slug = 'order-already-send';

    public $rel_type = 'order';

    public function __construct($order, $contact, $cc = '')
    {
        parent::__construct();

        $this->order = $order;
        $this->contact = $contact;
        $this->cc      = $cc;
    }

    public function build()
    {
        if ($this->ci->input->post('email_attachments')) {
            $_other_attachments = $this->ci->input->post('email_attachments');
            foreach ($_other_attachments as $attachment) {
                $_attachment = $this->ci->orders_model->get_attachments($this->order->id, $attachment);
                $this->add_attachment([
                                'attachment' => get_upload_path_by_type('order') . $this->order->id . '/' . $_attachment->file_name,
                                'filename'   => $_attachment->file_name,
                                'type'       => $_attachment->filetype,
                                'read'       => true,
                            ]);
            }
        }

        $this->to($this->contact->email)
        ->set_rel_id($this->order->id)
        ->set_merge_fields('client_merge_fields', $this->order->clientid, $this->contact->id)
        ->set_merge_fields('order_merge_fields', $this->order->id);
    }
}
