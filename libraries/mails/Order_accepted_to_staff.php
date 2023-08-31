<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_accepted_to_staff extends App_mail_template
{
    protected $for = 'staff';

    protected $order;

    protected $staff_email;

    protected $contact_id;

    public $slug = 'order-accepted-to-staff';

    public $rel_type = 'order';

    public function __construct($order, $staff_email, $contact_id)
    {
        parent::__construct();

        $this->order    = $order;
        $this->staff_email = $staff_email;
        $this->contact_id  = $contact_id;
    }

    public function build()
    {

        $this->to($this->staff_email)
        ->set_rel_id($this->order->id)
        ->set_merge_fields('client_merge_fields', $this->order->clientid, $this->contact_id)
        ->set_merge_fields('order_merge_fields', $this->order->id);
    }
}
