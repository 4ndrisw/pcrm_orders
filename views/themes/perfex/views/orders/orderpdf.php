<?php

defined('BASEPATH') or exit('No direct script access allowed');

$dimensions = $pdf->getPageDimensions();

$pdf->ln(25);

// Estimate to
$customer_info = '<b>' . _l('order_to') . '</b>';
$customer_info .= '<div style="color:#424242;">';
$customer_info .= format_order_info($order, 'order');
$customer_info .= '</div>';

if (!empty($order->reference_no)) {
    $customer_info .= _l('reference_no') . ': ' . $order->reference_no . '<br />';
}

$organization_info = '<div style="color:#424242;">';
    //$organization_info .= format_organization_info();
//    $organization_info .= '<span style = "width:300px;">Nomor</span><span>:</span> </span>' .format_order_number($order->id) . '</div>';
//    $organization_info .= '<span >Nomor</span><span>:</span> </span>' ._d($order->date) . '</div>';


    $organization_info .=  '<table width=100%>';
    $organization_info .=  '<tr>
                                <td width="25%"><strong>Nomor</strong></td>
                                <td width="5%">:</td>
                                <td width="70%">' .format_order_number($order->id) . '</td>
                            </tr>';
    $organization_info .=  '<tr>
                                <td width="25%"><strong>Tanggal</strong></td>
                                <td width="5%">:</td>
                                <td width="70%">' .getDay($order->date) .' '.getMonth($order->date).' '.getYear($order->date) .'</td>
                            </tr>';
    $organization_info .=  '<tr>
                                <td width="25%"><strong>Perihal</strong></td>
                                <td width="5%">:</td>
                                <td width="70%">' . format_order_number($order->id) . '</td>
                            </tr>';

    if (!empty($order->reference_no)) {
        $customer_info .= _l('reference_no') . ': ' . $order->reference_no . '<br />';
        $organization_info .=  '<tr>
                                <td width="25%">'._l('reference_no') .'</td>
                                <td width="5%">:</td>
                                <td width="70%">' . $order->reference_no . '</td>
                            </tr>';

    }

    $organization_info .=  '</table>';


$organization_info .= '</div>';

// set margins
$pdf->SetMargins(PDF_MARGIN_LEFT - 5, PDF_MARGIN_TOP + 10, PDF_MARGIN_RIGHT - 5);
//$pdf->SetHeaderMargin(PDF_MARGIN_HEADER + 30);

$right_info  = $swap == '1' ? $customer_info : $organization_info;
$left_info = $swap == '1' ? $organization_info : $customer_info;

pdf_multi_row($left_info, $right_info, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);

$pdf->Ln(4);
$prefix = 'Dengan Hormat, <br />';
$prefix .= 'Berdasarkan permintaan harga sertifikasi peralatan K3, berikut ini Kami sampaikan penawaran harga pekerjaan tersebut untuk '. get_company_name($order->clientid) .' dengan perincian berikut.';

$pdf->writeHTMLCell('', '', '', '', $prefix, 0, 1, false, true, 'L', true);

// The Table
$pdf->Ln(hooks()->apply_filters('pdf_info_and_table_separator', 3));

// The items table
$items = get_items_table_data($order, 'order', 'pdf');

$tblhtml = $items->table();

$pdf->writeHTML($tblhtml, true, false, false, false, '');

$pdf->Ln(1);
$tbltotal = '';
$tbltotal .= '<table cellpadding="6" style="font-size:' . ($font_size + 4) . 'px">';
$tbltotal .= '
<tr>
    <td align="right" width="75%"><strong>' . _l('order_subtotal') . '</strong></td>
    <td align="right" width="25%">' . app_format_money($order->subtotal, $order->currency_name) . '</td>
</tr>';

if (is_sale_discount_applied($order)) {
    $tbltotal .= '
    <tr>
        <td align="right" width="75%"><strong>' . _l('order_discount');
    if (is_sale_discount($order, 'percent')) {
        $tbltotal .= ' (' . app_format_number($order->discount_percent, true) . '%)';
    }
    $tbltotal .= '</strong>';
    $tbltotal .= '</td>';
    $tbltotal .= '<td align="right" width="25%">-' . app_format_money($order->discount_total, $order->currency_name) . '</td>
    </tr>';
}

foreach ($items->taxes() as $tax) {
    $tbltotal .= '<tr>
    <td align="right" width="75%"><strong>' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)' . '</strong></td>
    <td align="right" width="25%">' . app_format_money($tax['total_tax'], $order->currency_name) . '</td>
</tr>';
}

if ((int)$order->adjustment != 0) {
    $tbltotal .= '<tr>
    <td align="right" width="75%"><strong>' . _l('order_adjustment') . '</strong></td>
    <td align="right" width="25%">' . app_format_money($order->adjustment, $order->currency_name) . '</td>
</tr>';
}

$tbltotal .= '
<tr style="background-color:#f0f0f0;">
    <td align="right" width="75%"><strong>' . _l('order_total') . '</strong></td>
    <td align="right" width="25%">' . app_format_money($order->total, $order->currency_name) . '</td>
</tr>';

$tbltotal .= '</table>';

$pdf->writeHTML($tbltotal, true, false, false, false, '');

if (get_option('total_to_words_enabled') == 1) {
    // Set the font bold
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->writeHTMLCell('', '', '', '', _l('num_word') . ': ' . $CI->numberword->convert($order->total, $order->currency_name), 0, 1, false, true, 'C', true);
    // Set the font again to normal like the rest of the pdf
    $pdf->SetFont($font_name, '', $font_size);
}

$pdf->Ln(4);
$prefix = 'Demikianlah penawaran harga ini Kami sampaikan, bila diperlukan diskusi lebih lanjut terkait dengan penawaran ini bisa menghubungi nomor '. get_staff_phonenumber($order->sale_agent).'  a.n '. get_staff_full_name($order->sale_agent) .', atas kesempatan yang berikan, kami mengucapkan terima kasih.';

$pdf->writeHTMLCell('', '', '', '', $prefix, 0, 1, false, true, 'L', true);

$pdf->ln(6);

/*
$sale_agent_path = <<<EOF
        <img width="150" height="150" src="$order->sale_agent_path">
    EOF;    
*/
$sale_agent_info = '<div style="text-align:center;">';
    $sale_agent_info .= get_option('invoice_company_name') . '<br />';
    //$sale_agent_info .= $sale_agent_path . '<br />';

if ($order->sale_agent != 0 && get_option('show_sale_agent_on_orders') == 1) {
    $style = array(
        'border' => 0,
        'vpadding' => 'auto',
        'hpadding' => 'auto',
        'fgcolor' => array(0, 0, 0),
        'bgcolor' => false, //array(255,255,255)
        'module_width' => 1, // width of a single module in points
        'module_height' => 1 // height of a single module in points
     );
    $text = format_order_number($order->id)  .' - ' . $order->order_to;
    $sale_agent_info .= $pdf->write2DBarcode($text, 'QRCODE,L', 37, $pdf->getY(), 40, 40, $style);

    $sale_agent_info .=  '<br /> <br /> <br /> <br /> <br /> <br /><br />';   
    $sale_agent_info .= get_staff_full_name($order->sale_agent);
}
$sale_agent_info .= '</div>';

$client_info = '<div style="text-align:center;">';
    $client_info .= strtoupper( get_company_name($order->clientid) ) .'<br />';

if ($order->signed != 0) {
    $client_info .= _l('order_signed_by') . ": {$order->acceptance_firstname} {$order->acceptance_lastname}" . '<br />';
    $client_info .= _l('order_signed_date') . ': ' . _dt($order->acceptance_date_string) . '<br />';
    $client_info .= _l('order_signed_ip') . ": {$order->acceptance_ip}" . '<br />';

    $client_info .= $acceptance_path;
    $client_info .= '<br />';
}
$client_info .= '</div>';


$left_info  = $swap == '1' ? $client_info : $sale_agent_info;
$right_info = $swap == '1' ? $sale_agent_info : $client_info;
pdf_multi_row($left_info, $right_info, $pdf, ($dimensions['wk'] / 2) - $dimensions['lm']);
/*
if (!empty($order->note)) {
    $pdf->Ln(2);
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('order_note'), 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $order->note, 0, 1, false, true, 'L', true);
}

if (!empty($order->term)) {
    $pdf->Ln(2);
    if($pdf->getY() > 238){
        $pdf->AddPage();
    }
    $pdf->SetFont($font_name, 'B', $font_size);
    $pdf->Cell(0, 0, _l('terms_and_conditions') . ":", 0, 1, 'L', 0, '', 0);
    $pdf->SetFont($font_name, '', $font_size);
    $pdf->Ln(2);
    $pdf->writeHTMLCell('', '', '', '', $order->term, 0, 1, false, true, 'L', true);
}
*/

$pdf->Ln(4);

$notice = '<table>';
$notice .= '<tr>';

$notice .=     '<td align="left" width="50%">';
$notice .=     '<strong>' . _l('order_note') .'</strong>';
$notice .= $order->clientnote;
$notice .=      '</td>';

$notice .=     '<td align="left" width="50%">';
$notice .=     '<strong>' ._l('terms_and_conditions') . '</strong>';
$notice .= $order->adminnote;
$notice .=      '</td>';

$notice .= '</tr>';
$notice .= '</table>';

$pdf->writeHTML($notice, true, false, false, false, '');


$text = 'Dokumen ini diterbitkan melalui aplikasi `CRM` PT. Cipta Mas Jaya tidak memerlukan tanda tangan basah dan stempel.';
$pdf->Ln(2);
$pdf->SetY('266');
$pdf->writeHTMLCell('', '', '', '', $text, 0, 1, false, true, 'C', true);

