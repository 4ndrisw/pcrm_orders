<?php

defined('BASEPATH') or exit('No direct script access allowed');

include_once(LIBSPATH . 'pdf/App_pdf.php');

class Order_pdf extends App_pdf
{
    protected $order;

    private $order_number;

    public function __construct($order, $tag = '')
    {
        /*
        if ($order->clientid != null && $order->rel_type == 'customer') {
            $this->load_language($order->clientid);
        } else if ($order->clientid != null && $order->rel_type == 'lead') {
            $CI = &get_instance();

            $this->load_language($order->clientid);
            $CI->db->select('default_language')->where('id', $order->clientid);
            $language = $CI->db->get('leads')->row()->default_language;

            load_pdf_language($language);
        }
        */
        $this->load_language($order->clientid);

        $order                = hooks()->apply_filters('order_html_pdf_data', $order);
        $GLOBALS['order_pdf'] = $order;

        parent::__construct();

        $this->tag      = $tag;
        $this->order = $order;


        # Don't remove these lines - important for the PDF layout
        //$this->order->content = $this->fix_editor_html($this->order->content);
        $this->order_status_color = order_status_color_pdf($this->order->status);
        $this->order_status = format_order_status($this->order->status);

        $this->order_number = format_order_number($this->order->id);

        $this->SetTitle($this->order_number .'-'. get_company_name($order->clientid));
        //$this->SetTitle($this->order_number .'-'. $this->order->order_to);
        $this->SetDisplayMode('default', 'OneColumn');
    }

    //Page header
    public function Header() {

        $dimensions = $this->getPageDimensions();

        $order                = hooks()->apply_filters('order_html_pdf_data', $this->order);
        if(isset($order)){
            $order_pdf = $order;
        }

        $right = pdf_right_logo_url();
        
        // Add logo
        $left = pdf_logo_url();
        $this->ln(5);

        $page_start = $this->getPage();
        $y_start    = $this->GetY();
        $left_width = 40;
        // Write top left logo and right column info/text

        // write the left cell
        $this->MultiCell($left_width, 0, $left, 0, 'L', 0, 2, '', '', true, 0, true);

        $page_end_1 = $this->getPage();
        $y_end_1    = $this->GetY();

        $this->setPage($page_start);

        // write the right cell
        $this->MultiCell(185, 0, $right, 0, 'R', 0, 1, 0, $y_start, true, 0, true);

        //pdf_multi_row($info_right_column, '', $this, ($dimensions['wk'] / 1) - $dimensions['lm']);
        //pdf_multi_row($info_left_column, $info_right_column, $this, ($dimensions['wk'] / 1) - $dimensions['lm']);

        //$this->ln(5);
    }

    public function prepare()
    {
        /*
        $number_word_lang_rel_id = 'unknown';

        if ($this->order->rel_type == 'customer') {
            $number_word_lang_rel_id = $this->order->rel_id;
        }
        */

        $this->with_number_to_word($this->order->clientid);

        $total = '';
        if ($this->order->total != 0) {
            $total = app_format_money($this->order->total, get_currency($this->order->currency));
            $total = _l('order_total') . ': ' . $total;
        }

        $this->set_view_vars([
            'number'       => $this->order_number,
            'order'     => $this->order,
            'total'        => $total,
            'order_url' => site_url('order/' . $this->order->id . '/' . $this->order->hash),
        ]);

        return $this->build();
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-25);
        // Set font
        $this->SetFont('helvetica', 'B', 10);
        

        $tbl = <<<EOD
        <table cellspacing="0" cellpadding="5" border="0">
            <tr>
                <td width ="75%" align="center" style="line-height: 200%; vertical-align:middle; background-color:#00008B;color:#FFF;">
                    Jl. Raya Taktakan Ruko Golden Paradise No.7, Lontarbaru Serang, Banten<BR />
                    Web : www.ciptamasjaya.co.id - Email : info@ciptamasjaya.co.id 
                </td>
                <td width ="25%"  align="center" style="font-size:20px; line-height: 100%; vertical-align:middle; background-color:#FF0000; color:#FFF;">TAMASYA <BR />TOTAL SOLUTION FOR SAFETY</td>
            </tr>
        </table>
        EOD;

        $this->writeHTML($tbl, true, false, false, false, '');

    }

    protected function type()
    {
        return 'order';
    }

    protected function file_path()
    {
        $filePath = 'my_orderpdf.php';
        $customPath = module_views_path('orders','themes/' . active_clients_theme() . '/views/orders/' . $filePath);
        $actualPath = module_views_path('orders','themes/' . active_clients_theme() . '/views/orders/orderpdf.php');

        if (file_exists($customPath)) {
            $actualPath = $customPath;
        }

        return $actualPath;
    }
}
