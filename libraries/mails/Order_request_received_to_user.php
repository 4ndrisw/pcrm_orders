<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_request_received_to_user extends App_mail_template
{
    protected $order_request_id;

    protected $email;

    public $slug = 'order-request-received-to-user';

    public $rel_type = 'order_request';

    public function __construct($order_request_id, $email)
    {
        parent::__construct();
        $this->order_request_id = $order_request_id;
        $this->email = $email;
    }

    public function build()
    {
        $this->to($this->email)
            ->set_rel_id($this->order_request_id)
            ->set_merge_fields('order_request_merge_fields', $this->order_request_id);
    }
}
