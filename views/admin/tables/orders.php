<?php

defined('BASEPATH') or exit('No direct script access allowed');

$project_id = $this->ci->input->post('project_id');

$aColumns = [
    'number',
    'total',
    'total_tax',
    'YEAR(date) as year',
    get_sql_select_client_company(),
    db_prefix() . 'projects.name as project_name',
    '(SELECT GROUP_CONCAT(name SEPARATOR ",") FROM ' . db_prefix() . 'taggables JOIN ' . db_prefix() . 'tags ON ' . db_prefix() . 'taggables.tag_id = ' . db_prefix() . 'tags.id WHERE rel_id = ' . db_prefix() . 'orders.id and rel_type="order" ORDER by tag_order ASC) as tags',
    'date',
    'expirydate',
    'reference_no',
    db_prefix() . 'orders.status',
    ];

$join = [
    'LEFT JOIN ' . db_prefix() . 'clients ON ' . db_prefix() . 'clients.userid = ' . db_prefix() . 'orders.clientid',
    'LEFT JOIN ' . db_prefix() . 'currencies ON ' . db_prefix() . 'currencies.id = ' . db_prefix() . 'orders.currency',
    'LEFT JOIN ' . db_prefix() . 'projects ON ' . db_prefix() . 'projects.id = ' . db_prefix() . 'orders.project_id',
];

$sIndexColumn = 'id';
$sTable       = db_prefix() . 'orders';

$custom_fields = get_table_custom_fields('order');

foreach ($custom_fields as $key => $field) {
    $selectAs = (is_cf_date($field) ? 'date_picker_cvalue_' . $key : 'cvalue_' . $key);
    array_push($customFieldsColumns, $selectAs);
    array_push($aColumns, 'ctable_' . $key . '.value as ' . $selectAs);
    array_push($join, 'LEFT JOIN ' . db_prefix() . 'customfieldsvalues as ctable_' . $key . ' ON ' . db_prefix() . 'orders.id = ctable_' . $key . '.relid AND ctable_' . $key . '.fieldto="' . $field['fieldto'] . '" AND ctable_' . $key . '.fieldid=' . $field['id']);
}

$where  = [];
$filter = [];

if ($this->ci->input->post('not_sent')) {
    array_push($filter, 'OR (sent= 0 AND ' . db_prefix() . 'orders.status NOT IN (2,3,4))');
}
if ($this->ci->input->post('invoiced')) {
    array_push($filter, 'OR invoiceid IS NOT NULL');
}

if ($this->ci->input->post('not_invoiced')) {
    array_push($filter, 'OR invoiceid IS NULL');
}
$statuses  = $this->ci->orders_model->get_statuses();
$statusIds = [];
foreach ($statuses as $status) {
    if ($this->ci->input->post('orders_' . $status)) {
        array_push($statusIds, $status);
    }
}
if (count($statusIds) > 0) {
    array_push($filter, 'AND ' . db_prefix() . 'orders.status IN (' . implode(', ', $statusIds) . ')');
}

$agents    = $this->ci->orders_model->get_sale_agents();
$agentsIds = [];
foreach ($agents as $agent) {
    if ($this->ci->input->post('sale_agent_' . $agent['sale_agent'])) {
        array_push($agentsIds, $agent['sale_agent']);
    }
}
if (count($agentsIds) > 0) {
    array_push($filter, 'AND sale_agent IN (' . implode(', ', $agentsIds) . ')');
}

$years      = $this->ci->orders_model->get_orders_years();
$yearsArray = [];
foreach ($years as $year) {
    if ($this->ci->input->post('year_' . $year['year'])) {
        array_push($yearsArray, $year['year']);
    }
}
if (count($yearsArray) > 0) {
    array_push($filter, 'AND YEAR(date) IN (' . implode(', ', $yearsArray) . ')');
}

if (count($filter) > 0) {
    array_push($where, 'AND (' . prepare_dt_filter($filter) . ')');
}

if ($clientid != '') {
    array_push($where, 'AND ' . db_prefix() . 'orders.clientid=' . $this->ci->db->escape_str($clientid));
}

if ($project_id) {
    array_push($where, 'AND project_id=' . $this->ci->db->escape_str($project_id));
}

if (!has_permission('orders', '', 'view')) {
    $userWhere = 'AND ' . get_orders_where_sql_for_staff(get_staff_user_id());
    array_push($where, $userWhere);
}

$aColumns = hooks()->apply_filters('orders_table_sql_columns', $aColumns);

// Fix for big queries. Some hosting have max_join_limit
if (count($custom_fields) > 4) {
    @$this->ci->db->query('SET SQL_BIG_SELECTS=1');
}

$result = data_tables_init($aColumns, $sIndexColumn, $sTable, $join, $where, [
    db_prefix() . 'orders.id',
    db_prefix() . 'orders.clientid',
    db_prefix() . 'orders.invoiceid',
    db_prefix() . 'currencies.name as currency_name',
    'project_id',
    'deleted_customer_name',
    'hash',
    db_prefix() . 'orders.status',
]);

$output  = $result['output'];
$rResult = $result['rResult'];

foreach ($rResult as $aRow) {
    $row = [];

    $numberOutput = '';
    // If is from client area table or projects area request
    if (is_numeric($clientid) || $project_id) {
        $numberOutput = '<a href="' . admin_url('orders/list_orders/' . $aRow['id']) . '" target="_blank">' . format_order_number($aRow['id']) . '</a>';
    } else {
        $numberOutput = '<a href="' . admin_url('orders/list_orders/' . $aRow['id']) . '" onclick="init_order(' . $aRow['id'] . '); return false;">' . format_order_number($aRow['id']) . '</a>';
    }

    $numberOutput .= '<div class="row-options">';

    $numberOutput .= '<a href="' . site_url('order/' . $aRow['id'] . '/' . $aRow['hash']) . '" target="_blank">' . _l('view') . '</a>';
    if (has_permission('orders', '', 'edit')) {
        $numberOutput .= ' | <a href="' . admin_url('orders/order/' . $aRow['id']) . '">' . _l('edit') . '</a>';
    }
    $numberOutput .= '</div>';

    $row[] = $numberOutput;

    $amount = app_format_money($aRow['total'], $aRow['currency_name']);

    if ($aRow['invoiceid']) {
        $amount .= '<br /><span class="hide"> - </span><span class="text-success tw-text-sm">' . _l('order_invoiced') . '</span>';
    }

    $row[] = $amount;

    $row[] = app_format_money($aRow['total_tax'], $aRow['currency_name']);

    $row[] = $aRow['year'];

    if (empty($aRow['deleted_customer_name'])) {
        $row[] = '<a href="' . admin_url('clients/client/' . $aRow['clientid']) . '">' . $aRow['company'] . '</a>';
    } else {
        $row[] = $aRow['deleted_customer_name'];
    }

    $row[] = '<a href="' . admin_url('projects/view/' . $aRow['project_id']) . '">' . $aRow['project_name'] . '</a>';

    $row[] = render_tags($aRow['tags']);

    $row[] = _d($aRow['date']);

    $row[] = _d($aRow['expirydate']);

    $row[] = $aRow['reference_no'];


            $span = '';
                //if (!$locked) {
                    $span .= '<div class="dropdown inline-block mleft5 table-export-exclude">';
                    $span .= '<a href="#" style="font-size:14px;vertical-align:middle;" class="dropdown-toggle text-dark" id="tableLeadsStatus-' . $aRow['id'] . '" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">';
                    $span .= '<span data-toggle="tooltip" title="' . _l('ticket_single_change_status') . '"><i class="fa fa-caret-down" aria-hidden="true"></i></span>';
                    $span .= '</a>';

                    $span .= '<ul class="dropdown-menu dropdown-menu-right" aria-labelledby="tableLeadsStatus-' . $aRow['id'] . '">';
                    foreach ($statuses as $orderChangeStatus) {
                        if ($aRow['status'] != $orderChangeStatus) {
                            $span .= '<li>
                          <a href="#" onclick="order_mark_as(' . $orderChangeStatus . ',' . $aRow['id'] . '); return false;">
                             ' . _l('order_mark_as',format_order_status($orderChangeStatus,'',false)) . '
                          </a>
                       </li>';
                        }
                    }
                    $span .= '</ul>';
                    $span .= '</div>';
                //}
                $span .= '</span>';
/*
            $outputStatus = '<span class="label label-danger inline-block">' . _l('order_status_draft') . $span;

            if ($aRow['status'] == 1) {
                $outputStatus = '<span class="label label-default inline-block">' . _l('order_status_draft') . $span;
            } elseif ($aRow['status'] == 2) {
                $outputStatus = '<span class="label label-danger inline-block">' . _l('order_status_declined') . $span;
            } elseif ($aRow['status'] == 3) {
                $outputStatus = '<span class="label label-success inline-block">' . _l('order_status_accepted') . $span;
            } elseif ($aRow['status'] == 4) {
                $outputStatus = '<span class="label label-info inline-block">' . _l('order_status_sent') . $span;
            } elseif ($aRow['status'] == 5) {
                $outputStatus = '<span class="label label-warning inline-block">' . _l('order_status_expired') . $span;
            } elseif ($aRow['status'] == 6) {
                $outputStatus = '<span class="label label-success inline-block">' . _l('order_status_approved') . '</span>';
            }
*/
            $outputStatus = '<span>' . format_order_status($aRow['status'],'',true) . $span;

    $row[] = $outputStatus;

    //$row[] = format_order_status($aRow[db_prefix() . 'orders.status']);

    // Custom fields add values
    foreach ($customFieldsColumns as $customFieldColumn) {
        $row[] = (strpos($customFieldColumn, 'date_picker_') !== false ? _d($aRow[$customFieldColumn]) : $aRow[$customFieldColumn]);
    }

    $row['DT_RowClass'] = 'has-row-options';

    $row = hooks()->apply_filters('orders_table_row_data', $row, $aRow);

    $output['aaData'][] = $row;
}

echo json_encode($output);
die();