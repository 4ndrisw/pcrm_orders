<?php

defined('BASEPATH') or exit('No direct script access allowed');

class Order_expiration_reminder extends App_mail_template
{
    protected $for = 'customer';

    protected $order;

    protected $contact;

    public $slug = 'order-expiry-reminder';

    public $rel_type = 'order';

    public function __construct($order, $contact)
    {
        parent::__construct();

        $this->order = $order;
        $this->contact  = $contact;

        // For SMS
        $this->set_merge_fields('client_merge_fields', $this->order->clientid, $this->contact['id']);
        $this->set_merge_fields('order_merge_fields', $this->order->id);
    }

    public function build()
    {
        $this->to($this->contact['email'])
        ->set_rel_id($this->order->id);
    }
}
