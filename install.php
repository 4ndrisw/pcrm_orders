<?php

defined('BASEPATH') or exit('No direct script access allowed');


require_once('install/orders.php');
require_once('install/order_activity.php');
require_once('install/order_comments.php');


$CI->db->query("
INSERT INTO `tblemailtemplates` ( `type`, `slug`, `language`, `name`, `subject`, `message`, `fromname`, `fromemail`, `plaintext`, `active`, `order`) VALUES
('order', 'order-send-to-client', 'english', 'Send Invoice to Customer', 'Invoice with number {order_number} created', '<span style=\"font-size: 12pt;\">Dear {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">We have prepared the following order for you: <strong># {order_number}</strong></span><br /><br /><span style=\"font-size: 12pt;\"><strong>Invoice status</strong>: {order_status}</span><br /><br /><span style=\"font-size: 12pt;\">You can view the order on the following link: <a href=\"{order_link}\">{order_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">Please contact us for more information.</span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('order', 'order-payment-recorded', 'english', 'Invoice Payment Recorded (Sent to Customer)', 'Invoice Payment Recorded', '<span style=\"font-size: 12pt;\">Hello {contact_firstname}&nbsp;{contact_lastname}<br /><br /></span>Thank you for the payment. Find the payment details below:<br /><br />-------------------------------------------------<br /><br />Amount:&nbsp;<strong>{payment_total}<br /></strong>Date:&nbsp;<strong>{payment_date}</strong><br />Invoice number:&nbsp;<span style=\"font-size: 12pt;\"><strong># {order_number}<br /><br /></strong></span>-------------------------------------------------<br /><br />You can always view the order for this payment at the following link:&nbsp;<a href=\"{order_link}\"><span style=\"font-size: 12pt;\">{order_number}</span></a><br /><br />We are looking forward working with you.<br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('order', 'order-overdue-notice', 'english', 'Invoice Overdue Notice', 'Invoice Overdue Notice - {order_number}', '<span style=\"font-size: 12pt;\">Hi {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">This is an overdue notice for order <strong># {order_number}</strong></span><br /><br /><span style=\"font-size: 12pt;\">This order was due: {order_duedate}</span><br /><br /><span style=\"font-size: 12pt;\">You can view the order on the following link: <a href=\"{order_link}\">{order_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 0, 0),
('order', 'order-already-send', 'english', 'Invoice Already Sent to Customer', 'Invoice # {order_number} ', '<span style=\"font-size: 12pt;\">Hi {contact_firstname} {contact_lastname}</span><br /><br /><span style=\"font-size: 12pt;\">At your request, here is the order with number <strong># {order_number}</strong></span><br /><br /><span style=\"font-size: 12pt;\">You can view the order on the following link: <a href=\"{order_link}\">{order_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">Please contact us for more information.</span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('order', 'order-payment-recorded-to-staff', 'english', 'Invoice Payment Recorded (Sent to Staff)', 'New Invoice Payment', '<span style=\"font-size: 12pt;\">Hi</span><br /><br /><span style=\"font-size: 12pt;\">Customer recorded payment for order <strong># {order_number}</strong></span><br /> <br /><span style=\"font-size: 12pt;\">You can view the order on the following link: <a href=\"{order_link}\">{order_number}</a></span><br /> <br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 1, 0),
('order', 'order-due-notice', 'english', 'Invoice Due Notice', 'Your {order_number} will be due soon', '<span style=\"font-size: 12pt;\">Hi {contact_firstname} {contact_lastname}<br /><br /></span>You order <span style=\"font-size: 12pt;\"><strong># {order_number} </strong>will be due on <strong>{order_duedate}</strong></span><br /><br /><span style=\"font-size: 12pt;\">You can view the order on the following link: <a href=\"{order_link}\">{order_number}</a></span><br /><br /><span style=\"font-size: 12pt;\">Kind Regards,</span><br /><span style=\"font-size: 12pt;\">{email_signature}</span>', '{companyname} | CRM', '', 0, 0, 0),
('order', 'orders-batch-payments', 'english', 'Invoices Payments Recorded in Batch (Sent to Customer)', 'We have received your payments', 'Hello {contact_firstname} {contact_lastname}<br><br>Thank you for the payments. Please find the payments details below:<br><br>{batch_payments_list}<br><br>We are looking forward working with you.<br><br>Kind Regards,<br><br>{email_signature}', '{companyname} | CRM', '', 0, 0, 0),
('order', 'order-send-to-client', 'indonesia', 'Send Invoice to Customer [indonesia]', 'Invoice with number {order_number} created', '', '{companyname} | CRM', NULL, 0, 1, 0),
('order', 'order-payment-recorded', 'indonesia', 'Invoice Payment Recorded (Sent to Customer) [indonesia]', 'Invoice Payment Recorded', '', '{companyname} | CRM', NULL, 0, 1, 0),
('order', 'order-overdue-notice', 'indonesia', 'Invoice Overdue Notice [indonesia]', 'Invoice Overdue Notice - {order_number}', '', '{companyname} | CRM', NULL, 0, 0, 0),
('order', 'order-already-send', 'indonesia', 'Invoice Already Sent to Customer [indonesia]', 'Invoice # {order_number} ', '', '{companyname} | CRM', NULL, 0, 1, 0),
('order', 'order-payment-recorded-to-staff', 'indonesia', 'Invoice Payment Recorded (Sent to Staff) [indonesia]', 'New Invoice Payment', '', '{companyname} | CRM', NULL, 0, 1, 0),
('order', 'order-due-notice', 'indonesia', 'Invoice Due Notice [indonesia]', 'Your {order_number} will be due soon', '', '{companyname} | CRM', NULL, 0, 0, 0),
('order', 'orders-batch-payments', 'indonesia', 'Invoices Payments Recorded in Batch (Sent to Customer) [indonesia]', 'We have received your payments', '', '{companyname} | CRM', NULL, 0, 0, 0);



");

// Add options for orders
add_option('delete_only_on_last_order', 1);
add_option('order_prefix', 'INV-');
add_option('next_order_number', 1);
add_option('default_order_assigned', 9);
add_option('order_number_decrement_on_delete', 0);
add_option('order_number_format', 4);
add_option('order_year', date('Y'));
add_option('exclude_order_from_client_area_with_draft_status', 1);
add_option('predefined_client_note_order', '- Staf diatas untuk melakukan riksa uji pada peralatan tersebut.
- Staf diatas untuk membuat dokumentasi riksa uji sesuai kebutuhan.');
add_option('predefined_terms_order', '- Pelaksanaan riksa uji harus mengikuti prosedur yang ditetapkan perusahaan pemilik alat.
- Dilarang membuat dokumentasi tanpa seizin perusahaan pemilik alat.
- Dokumen ini diterbitkan dari sistem CRM, tidak memerlukan tanda tangan dari PT. Cipta Mas Jaya');
add_option('order_due_after', 1);
add_option('allow_staff_view_orders_assigned', 1);
add_option('show_assigned_on_orders', 1);
add_option('require_client_logged_in_to_view_order', 0);

add_option('show_project_on_order', 1);
add_option('orders_pipeline_limit', 1);
add_option('default_orders_pipeline_sort', 1);
add_option('order_accept_identity_confirmation', 1);
add_option('order_qrcode_size', '160');
add_option('order_send_telegram_message', 0);


/*

DROP TABLE `tblorders`, `tblorder_activity`, `tblorder_comments`, `tblorderpaymentrecords`;

delete FROM `tbloptions` WHERE `name` LIKE '%order%';
DELETE FROM `tblemailtemplates` WHERE `type` LIKE 'order';



*/