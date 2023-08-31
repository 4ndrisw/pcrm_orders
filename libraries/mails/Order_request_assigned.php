<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_request_assigned extends App_mail_template
{
    protected $order_request_id;

    protected $staff_email;

    protected $for = 'staff';

    public $slug = 'order-request-assigned';

    public $rel_type = 'order_request';

    public function __construct($order_request_id, $staff_email)
    {
        parent::__construct();
        $this->order_request_id = $order_request_id;
        $this->staff_email = $staff_email;
    }

    public function build()
    {
        $this->to($this->staff_email)
            ->set_rel_id($this->order_request_id)
            ->set_merge_fields('order_request_merge_fields', $this->order_request_id);
    }
}
