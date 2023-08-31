<?php

defined('BASEPATH') or exit('No direct script access allowed');

//$route['statements/schedule/(:num)/(:any)'] = 'schedule/index/$1/$2';

/**
 * @since 2.0.0
 */
//$route['statements/list'] = 'mystatements';
//$route['statements/show/(:num)/(:any)'] = 'mystatement/show/$1/$2';
//$route['statements/pdf/(:num)'] = 'myschedule/pdf/$1';

$route['statements/myremittances']               = 'Mystatement/index'; //OK

$route['statements/payment/(:num)'] = 'remittances/payment/$1';
$route['statements/remittance/pdf/(:num)'] = 'remittances/pdf/$1';
