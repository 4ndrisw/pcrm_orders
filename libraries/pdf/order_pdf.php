<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(__DIR__ . '/App_pdf.php');

class Billing_pdf extends App_pdf
{
    protected $order;

    private $order_number;

    public function __construct($order, $tag = '')
    {
        $this->load_language($order->clientid);

        $order                = hooks()->apply_filters('order_html_pdf_data', $order);
        $GLOBALS['order_pdf'] = $order;

        parent::__construct();

        $this->tag             = $tag;
        $this->order        = $order;
        $this->order_number = format_order_number($this->order->id);

        $this->SetTitle($this->order_number);
    }

    public function prepare()
    {
        $this->with_number_to_word($this->order->clientid);

        $this->set_view_vars([
            'status'          => $this->order->status,
            'order_number' => $this->order_number,
            'order'        => $this->order,
        ]);

        return $this->build();
    }

    protected function type()
    {
        return 'order';
    }

    protected function file_path()
    {
        $customPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/my_orderpdf.php';
        $actualPath = APPPATH . 'views/themes/' . active_clients_theme() . '/views/orderpdf.php';

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
