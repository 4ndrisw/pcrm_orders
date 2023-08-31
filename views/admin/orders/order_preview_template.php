<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php echo form_hidden('_attachment_sale_id', $order->id); ?>
<?php echo form_hidden('_attachment_sale_type', 'order'); ?>
<div class="col-md-12 no-padding">
    <div class="panel_s">
        <div class="panel-body">
            <div class="horizontal-scrollable-tabs preview-tabs-top panel-full-width-tabs">
                <div class="scroller arrow-left"><i class="fa fa-angle-left"></i></div>
                <div class="scroller arrow-right"><i class="fa fa-angle-right"></i></div>
                <div class="horizontal-tabs">
                    <ul class="nav nav-tabs nav-tabs-horizontal mbot15" role="tablist">
                        <li role="presentation" class="active">
                            <a href="#tab_order" aria-controls="tab_order" role="tab" data-toggle="tab">
                                <?php echo _l('order'); ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab_tasks"
                                onclick="init_rel_tasks_table(<?php echo $order->id; ?>,'order'); return false;"
                                aria-controls="tab_tasks" role="tab" data-toggle="tab">
                                <?php echo _l('tasks'); ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab_activity" aria-controls="tab_activity" role="tab" data-toggle="tab">
                                <?php echo _l('order_view_activity_tooltip'); ?>
                            </a>
                        </li>
                        <li role="presentation">
                            <a href="#tab_reminders"
                                onclick="initDataTable('.table-reminders', admin_url + 'misc/get_reminders/' + <?php echo $order->id ; ?> + '/' + 'order', undefined, undefined, undefined,[1,'asc']); return false;"
                                aria-controls="tab_reminders" role="tab" data-toggle="tab">
                                <?php echo _l('order_reminders'); ?>
                                <?php
                        $total_reminders = total_rows(
    db_prefix() . 'reminders',
    [
                           'isnotified' => 0,
                           'staff'      => get_staff_user_id(),
                           'rel_type'   => 'order',
                           'rel_id'     => $order->id,
                           ]
);
                        if ($total_reminders > 0) {
                            echo '<span class="badge">' . $total_reminders . '</span>';
                        }
                        ?>
                            </a>
                        </li>
                        <li role="presentation" class="tab-separator">
                            <a href="#tab_notes"
                                onclick="get_sales_notes(<?php echo $order->id; ?>,'orders'); return false"
                                aria-controls="tab_notes" role="tab" data-toggle="tab">
                                <?php echo _l('order_notes'); ?>
                                <span class="notes-total">
                                    <?php if ($totalNotes > 0) { ?>
                                    <span class="badge"><?php echo $totalNotes; ?></span>
                                    <?php } ?>
                                </span>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip" title="<?php echo _l('emails_tracking'); ?>"
                            class="tab-separator">
                            <a href="#tab_emails_tracking" aria-controls="tab_emails_tracking" role="tab"
                                data-toggle="tab">
                                <?php if (!is_mobile()) { ?>
                                <i class="fa-regular fa-envelope-open" aria-hidden="true"></i>
                                <?php } else { ?>
                                <?php echo _l('emails_tracking'); ?>
                                <?php } ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip" data-title="<?php echo _l('view_tracking'); ?>"
                            class="tab-separator">
                            <a href="#tab_views" aria-controls="tab_views" role="tab" data-toggle="tab">
                                <?php if (!is_mobile()) { ?>
                                <i class="fa fa-eye"></i>
                                <?php } else { ?>
                                <?php echo _l('view_tracking'); ?>
                                <?php } ?>
                            </a>
                        </li>
                        <li role="presentation" data-toggle="tooltip" data-title="<?php echo _l('toggle_full_view'); ?>"
                            class="tab-separator toggle_view">
                            <a href="#" onclick="small_table_full_view(); return false;">
                                <i class="fa fa-expand"></i></a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="row mtop20">
                <div class="col-md-3">
                    <?php echo format_order_status($order->status, 'mtop5 inline-block'); ?>
                </div>
                <div class="col-md-9">
                    <div class="visible-xs">
                        <div class="mtop10"></div>
                    </div>
                    <div class="pull-right _buttons">
                        <?php if (staff_can('edit', 'orders')) { ?>
                        <a href="<?php echo admin_url('orders/order/' . $order->id); ?>"
                            class="btn btn-default btn-with-tooltip" data-toggle="tooltip"
                            title="<?php echo _l('edit_order_tooltip'); ?>" data-placement="bottom"><i
                                class="fa-regular fa-pen-to-square"></i></a>
                        <?php } ?>
                        <div class="btn-group">
                            <a href="#" class="btn btn-default dropdown-toggle" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false"><i class="fa-regular fa-file-pdf"></i><?php if (is_mobile()) {
                            echo ' PDF';
                        } ?> <span class="caret"></span></a>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li class="hidden-xs"><a
                                        href="<?php echo admin_url('orders/pdf/' . $order->id . '?output_type=I'); ?>"><?php echo _l('view_pdf'); ?></a>
                                </li>
                                <li class="hidden-xs"><a
                                        href="<?php echo admin_url('orders/pdf/' . $order->id . '?output_type=I'); ?>"
                                        target="_blank"><?php echo _l('view_pdf_in_new_window'); ?></a></li>
                                <li><a
                                        href="<?php echo admin_url('orders/pdf/' . $order->id); ?>"><?php echo _l('download'); ?></a>
                                </li>
                                <li>
                                    <a href="<?php echo admin_url('orders/pdf/' . $order->id . '?print=true'); ?>"
                                        target="_blank">
                                        <?php echo _l('print'); ?>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <?php
                     $_tooltip              = _l('order_sent_to_email_tooltip');
                     $_tooltip_already_send = '';
                     if ($order->sent == 1) {
                         $_tooltip_already_send = _l('order_already_send_to_client_tooltip', time_ago($order->datesend));
                     }
                     ?>
                        <?php if (!empty($order->clientid)) { ?>
                        <a href="#" class="order-send-to-client btn btn-default btn-with-tooltip"
                            data-toggle="tooltip" title="<?php echo $_tooltip; ?>" data-placement="bottom"><span
                                data-toggle="tooltip" data-title="<?php echo $_tooltip_already_send; ?>"><i
                                    class="fa-regular fa-envelope"></i></span></a>
                        <?php } ?>
                        <div class="btn-group">
                            <button type="button" class="btn btn-default pull-left dropdown-toggle"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <?php echo _l('more'); ?> <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-right">
                                <li>
                                    <a href="<?php echo site_url('order/' . $order->id . '/' . $order->hash) ?>"
                                        target="_blank">
                                        <?php echo _l('view_order_as_client'); ?>
                                    </a>
                                </li>
                                <?php hooks()->do_action('after_order_view_as_client_link', $order); ?>
                                <?php if ((!empty($order->expirydate) && date('Y-m-d') < $order->expirydate && ($order->status == 2 || $order->status == 5)) && is_orders_expiry_reminders_enabled()) { ?>
                                <li>
                                    <a
                                        href="<?php echo admin_url('orders/send_expiry_reminder/' . $order->id); ?>">
                                        <?php echo _l('send_expiry_reminder'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <li>
                                    <a href="#" data-toggle="modal"
                                        data-target="#sales_attach_file"><?php echo _l('invoice_attach_file'); ?></a>
                                </li>
                                <?php if (staff_can('create', 'projects') && $order->project_id == 0) { ?>
                                <li>
                                    <a
                                        href="<?php echo admin_url("projects/project?via_order_id={$order->id}&customer_id={$order->clientid}") ?>">
                                        <?php echo _l('order_convert_to_project'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php if ($order->invoiceid == null) {
                         if (staff_can('edit', 'orders')) {
                             foreach ($order_statuses as $status) {
                                 if ($order->status != $status) { ?>
                                <li>
                                    <a
                                        href="<?php echo admin_url() . 'orders/mark_action_status/' . $status . '/' . $order->id; ?>">
                                        <?php echo _l('order_mark_as', format_order_status($status, '', false)); ?></a>
                                </li>
                                <?php }
                             } ?>
                                <?php
                         } ?>
                                <?php
                     } ?>
                                <?php if (staff_can('create', 'orders')) { ?>
                                <li>
                                    <a href="<?php echo admin_url('orders/copy/' . $order->id); ?>">
                                        <?php echo _l('copy_order'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php if (!empty($order->signature) && staff_can('delete', 'orders')) { ?>
                                <li>
                                    <a href="<?php echo admin_url('orders/clear_signature/' . $order->id); ?>"
                                        class="_delete">
                                        <?php echo _l('clear_signature'); ?>
                                    </a>
                                </li>
                                <?php } ?>
                                <?php if (staff_can('delete', 'orders')) { ?>
                                <?php
                           if ((get_option('delete_only_on_last_order') == 1 && is_last_order($order->id)) || (get_option('delete_only_on_last_order') == 0)) { ?>
                                <li>
                                    <a href="<?php echo admin_url('orders/delete/' . $order->id); ?>"
                                        class="text-danger delete-text _delete"><?php echo _l('delete_order_tooltip'); ?></a>
                                </li>
                                <?php
                           }
                           }
                           ?>
                            </ul>
                        </div>
                        <?php if ($order->invoiceid == null) { ?>
                        <?php if (staff_can('create', 'invoices') && !empty($order->clientid)) { ?>
                        <div class="btn-group pull-right mleft5">
                            <button type="button" class="btn btn-success dropdown-toggle" data-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <?php echo _l('order_convert_to_invoice'); ?> <span class="caret"></span>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a
                                        href="<?php echo admin_url('orders/convert_to_invoice/' . $order->id . '?save_as_draft=true'); ?>"><?php echo _l('convert_and_save_as_draft'); ?></a>
                                </li>
                                <li class="divider">
                                <li><a
                                        href="<?php echo admin_url('orders/convert_to_invoice/' . $order->id); ?>"><?php echo _l('convert'); ?></a>
                                </li>
                                </li>
                            </ul>
                        </div>
                        <?php } ?>
                        <?php } else { ?>
                        <a href="<?php echo admin_url('invoices/list_invoices/' . $order->invoice->id); ?>"
                            data-placement="bottom" data-toggle="tooltip"
                            title="<?php echo _l('order_invoiced_date', _dt($order->invoiced_date)); ?>"
                            class="btn btn-primary mleft10"><?php echo format_invoice_number($order->invoice->id); ?></a>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <div class="clearfix"></div>
            <hr class="hr-panel-separator" />
            <div class="tab-content">
                <div role="tabpanel" class="tab-pane ptop10 active" id="tab_order">
                    <?php if (isset($order->scheduled_email) && $order->scheduled_email) { ?>
                    <div class="alert alert-warning">
                        <?php echo _l('invoice_will_be_sent_at', _dt($order->scheduled_email->scheduled_at)); ?>
                        <?php if (staff_can('edit', 'orders') || $order->addedfrom == get_staff_user_id()) { ?>
                        <a href="#"
                            onclick="edit_order_scheduled_email(<?php echo $order->scheduled_email->id; ?>); return false;">
                            <?php echo _l('edit'); ?>
                        </a>
                        <?php } ?>
                    </div>
                    <?php } ?>
                    <div id="order-preview">
                        <div class="row">
                            <?php if ($order->status == 4 && !empty($order->acceptance_firstname) && !empty($order->acceptance_lastname) && !empty($order->acceptance_email)) { ?>
                            <div class="col-md-12">
                                <div class="alert alert-info mbot15">
                                    <?php echo _l('accepted_identity_info', [
                              _l('order_lowercase'),
                              '<b>' . $order->acceptance_firstname . ' ' . $order->acceptance_lastname . '</b> (<a href="mailto:' . $order->acceptance_email . '">' . $order->acceptance_email . '</a>)',
                              '<b>' . _dt($order->acceptance_date) . '</b>',
                              '<b>' . $order->acceptance_ip . '</b>' . (is_admin() ? '&nbsp;<a href="' . admin_url('orders/clear_acceptance_info/' . $order->id) . '" class="_delete text-muted" data-toggle="tooltip" data-title="' . _l('clear_this_information') . '"><i class="fa fa-remove"></i></a>' : ''),
                              ]); ?>
                                </div>
                            </div>
                            <?php } ?>
                            <?php if ($order->project_id != 0) { ?>
                            <div class="col-md-12">
                                <h4 class="font-medium mbot15"><?php echo _l('related_to_project', [
                           _l('order_lowercase'),
                           _l('project_lowercase'),
                           '<a href="' . admin_url('projects/view/' . $order->project_id) . '" target="_blank">' . $order->project_data->name . '</a>',
                           ]); ?></h4>
                            </div>
                            <?php } ?>
                            <div class="col-md-6 col-sm-6">
                                <h4 class="bold">
                                    <?php
                              $tags = get_tags_in($order->id, 'order');
                              if (count($tags) > 0) {
                                  echo '<i class="fa fa-tag" aria-hidden="true" data-toggle="tooltip" data-title="' . html_escape(implode(', ', $tags)) . '"></i>';
                              }
                              ?>
                                    <a href="<?php echo admin_url('orders/order/' . $order->id); ?>">
                                        <span id="order-number">
                                            <?php echo format_order_number($order->id); ?>
                                        </span>
                                    </a>
                                </h4>
                                <address class="tw-text-neutral-500">
                                    <?php echo format_organization_info(); ?>
                                </address>
                            </div>
                            <div class="col-sm-6 text-right">
                                <span class="bold"><?php echo _l('order_to'); ?></span>
                                <address class="tw-text-neutral-500">
                                    <?php echo format_customer_info($order, 'order', 'billing', true); ?>
                                </address>
                                <?php if ($order->include_shipping == 1 && $order->show_shipping_on_order == 1) { ?>
                                <span class="bold"><?php echo _l('ship_to'); ?></span>
                                <address class="tw-text-neutral-500">
                                    <?php echo format_customer_info($order, 'order', 'shipping'); ?>
                                </address>
                                <?php } ?>
                                <p class="no-mbot">
                                    <span class="bold">
                                        <?php echo _l('order_data_date'); ?>:
                                    </span>
                                    <?php echo $order->date; ?>
                                </p>
                                <?php if (!empty($order->expirydate)) { ?>
                                <p class="no-mbot">
                                    <span class="bold"><?php echo _l('order_data_expiry_date'); ?>:</span>
                                    <?php echo $order->expirydate; ?>
                                </p>
                                <?php } ?>
                                <?php if (!empty($order->reference_no)) { ?>
                                <p class="no-mbot">
                                    <span class="bold"><?php echo _l('reference_no'); ?>:</span>
                                    <?php echo $order->reference_no; ?>
                                </p>
                                <?php } ?>
                                <?php if ($order->sale_agent != 0 && get_option('show_sale_agent_on_orders') == 1) { ?>
                                <p class="no-mbot">
                                    <span class="bold"><?php echo _l('sale_agent_string'); ?>:</span>
                                    <?php echo get_staff_full_name($order->sale_agent); ?>
                                </p>
                                <?php } ?>
                                <?php if ($order->project_id != 0 && get_option('show_project_on_order') == 1) { ?>
                                <p class="no-mbot">
                                    <span class="bold"><?php echo _l('project'); ?>:</span>
                                    <?php echo get_project_name_by_id($order->project_id); ?>
                                </p>
                                <?php } ?>
                                <?php $pdf_custom_fields = get_custom_fields('order', ['show_on_pdf' => 1]);
                           foreach ($pdf_custom_fields as $field) {
                               $value = get_custom_field_value($order->id, $field['id'], 'order');
                               if ($value == '') {
                                   continue;
                               } ?>
                                <p class="no-mbot">
                                    <span class="bold"><?php echo $field['name']; ?>: </span>
                                    <?php echo $value; ?>
                                </p>
                                <?php
                           } ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="table-responsive">
                                    <?php
                                        $items = get_items_table_data($order, 'order', 'html', true);
                                        echo $items->table();
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-5 col-md-offset-7">
                                <table class="table text-right">
                                    <tbody>
                                        <tr id="subtotal">
                                            <td>
                                                <span class="bold tw-text-neutral-700">
                                                    <?php echo _l('order_subtotal'); ?>
                                                </span>
                                            </td>
                                            <td class="subtotal">
                                                <?php echo app_format_money($order->subtotal, $order->currency_name); ?>
                                            </td>
                                        </tr>
                                        <?php if (is_sale_discount_applied($order)) { ?>
                                        <tr>
                                            <td>
                                                <span
                                                    class="bold tw-text-neutral-700"><?php echo _l('order_discount'); ?>
                                                    <?php if (is_sale_discount($order, 'percent')) { ?>
                                                    (<?php echo app_format_number($order->discount_percent, true); ?>%)
                                                    <?php } ?>
                                                </span>
                                            </td>
                                            <td class="discount">
                                                <?php echo '-' . app_format_money($order->discount_total, $order->currency_name); ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                        <?php
                                            foreach ($items->taxes() as $tax) {
                                                echo '<tr class="tax-area"><td class="bold !tw-text-neutral-700">' . $tax['taxname'] . ' (' . app_format_number($tax['taxrate']) . '%)</td><td>' . app_format_money($tax['total_tax'], $order->currency_name) . '</td></tr>';
                                            }
                                        ?>
                                        <?php if ((int)$order->adjustment != 0) { ?>
                                        <tr>
                                            <td>
                                                <span class="bold tw-text-neutral-700">
                                                    <?php echo _l('order_adjustment'); ?>
                                                </span>
                                            </td>
                                            <td class="adjustment">
                                                <?php echo app_format_money($order->adjustment, $order->currency_name); ?>
                                            </td>
                                        </tr>
                                        <?php } ?>
                                        <tr>
                                            <td>
                                                <span class="bold tw-text-neutral-700">
                                                    <?php echo _l('order_total'); ?>
                                                </span>
                                            </td>
                                            <td class="total">
                                                <?php echo app_format_money($order->total, $order->currency_name); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <?php if (count($order->attachments) > 0) { ?>
                            <div class="clearfix"></div>
                            <hr />
                            <div class="col-md-12">
                                <p class="bold text-muted"><?php echo _l('order_files'); ?></p>
                            </div>
                            <?php foreach ($order->attachments as $attachment) {
                                            $attachment_url = site_url('download/file/sales_attachment/' . $attachment['attachment_key']);
                                            if (!empty($attachment['external'])) {
                                                $attachment_url = $attachment['external_link'];
                                            } ?>
                            <div class="mbot15 row col-md-12" data-attachment-id="<?php echo $attachment['id']; ?>">
                                <div class="col-md-8">
                                    <div class="pull-left"><i
                                            class="<?php echo get_mime_class($attachment['filetype']); ?>"></i></div>
                                    <a href="<?php echo $attachment_url; ?>"
                                        target="_blank"><?php echo $attachment['file_name']; ?></a>
                                    <br />
                                    <small class="text-muted"> <?php echo $attachment['filetype']; ?></small>
                                </div>
                                <div class="col-md-4 text-right tw-space-x-2">
                                    <?php if ($attachment['visible_to_customer'] == 0) {
                                                $icon    = 'fa fa-toggle-off';
                                                $tooltip = _l('show_to_customer');
                                            } else {
                                                $icon    = 'fa fa-toggle-on';
                                                $tooltip = _l('hide_from_customer');
                                            } ?>
                                    <a href="#" data-toggle="tooltip"
                                        onclick="toggle_file_visibility(<?php echo $attachment['id']; ?>,<?php echo $order->id; ?>,this); return false;"
                                        data-title="<?php echo $tooltip; ?>"><i class="<?php echo $icon; ?> fa-lg"
                                            aria-hidden="true"></i></a>
                                    <?php if ($attachment['staffid'] == get_staff_user_id() || is_admin()) { ?>
                                    <a href="#" class="text-danger"
                                        onclick="delete_order_attachment(<?php echo $attachment['id']; ?>); return false;"><i
                                            class="fa fa-times fa-lg"></i></a>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php
                                        } ?>
                            <?php } ?>
                            <?php if ($order->clientnote != '') { ?>
                            <div class="col-md-12 mtop15">
                                <p class="bold text-muted"><?php echo _l('order_note'); ?></p>
                                <p><?php echo $order->clientnote; ?></p>
                            </div>
                            <?php } ?>
                            <?php if ($order->terms != '') { ?>
                            <div class="col-md-12 mtop15">
                                <p class="bold text-muted"><?php echo _l('terms_and_conditions'); ?></p>
                                <p><?php echo $order->terms; ?></p>
                            </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_tasks">
                    <?php init_relation_tasks_table(['data-new-rel-id' => $order->id, 'data-new-rel-type' => 'order']); ?>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_reminders">
                    <a href="#" data-toggle="modal" class="btn btn-primary"
                        data-target=".reminder-modal-order-<?php echo $order->id; ?>"><i
                            class="fa-regular fa-bell"></i>
                        <?php echo _l('order_set_reminder_title'); ?></a>
                    <hr />
                    <?php render_datatable([ _l('reminder_description'), _l('reminder_date'), _l('reminder_staff'), _l('reminder_is_notified')], 'reminders'); ?>
                    <?php $this->load->view('admin/includes/modals/reminder', ['id' => $order->id, 'name' => 'order', 'members' => $members, 'reminder_title' => _l('order_set_reminder_title')]); ?>
                </div>
                <div role="tabpanel" class="tab-pane ptop10" id="tab_emails_tracking">
                    <?php
                  $this->load->view(
                                                'admin/includes/emails_tracking',
                                                [
                     'tracked_emails' => get_tracked_emails($order->id, 'order'), ]
                                            );
                  ?>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_notes">
                    <?php echo form_open(admin_url('orders/add_note/' . $order->id), ['id' => 'sales-notes', 'class' => 'order-notes-form']); ?>
                    <?php echo render_textarea('description'); ?>
                    <div class="text-right">
                        <button type="submit"
                            class="btn btn-primary mtop15 mbot15"><?php echo _l('order_add_note'); ?></button>
                    </div>
                    <?php echo form_close(); ?>
                    <hr />
                    <div class="mtop20" id="sales_notes_area">
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane" id="tab_activity">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="activity-feed">
                                <?php foreach ($activity as $activity) {
                      $_custom_data = false; ?>
                                <div class="feed-item" data-sale-activity-id="<?php echo $activity['id']; ?>">
                                    <div class="date">
                                        <span class="text-has-action" data-toggle="tooltip"
                                            data-title="<?php echo _dt($activity['date']); ?>">
                                            <?php echo time_ago($activity['date']); ?>
                                        </span>
                                    </div>
                                    <div class="text">
                                        <?php if (is_numeric($activity['staffid']) && $activity['staffid'] != 0) { ?>
                                        <a href="<?php echo admin_url('profile/' . $activity['staffid']); ?>">
                                            <?php echo staff_profile_image($activity['staffid'], ['staff-profile-xs-image pull-left mright5']);
                                 ?>
                                        </a>
                                        <?php } ?>
                                        <?php
                                 $additional_data = '';
                      if (!empty($activity['additional_data'])) {
                          $additional_data = app_unserialize($activity['additional_data']);
                          $i               = 0;
                          foreach ($additional_data as $data) {
                              if (strpos($data, '<original_status>') !== false) {
                                  $original_status     = get_string_between($data, '<original_status>', '</original_status>');
                                  $additional_data[$i] = format_order_status($original_status, '', false);
                              } elseif (strpos($data, '<new_status>') !== false) {
                                  $new_status          = get_string_between($data, '<new_status>', '</new_status>');
                                  $additional_data[$i] = format_order_status($new_status, '', false);
                              } elseif (strpos($data, '<status>') !== false) {
                                  $status              = get_string_between($data, '<status>', '</status>');
                                  $additional_data[$i] = format_order_status($status, '', false);
                              } elseif (strpos($data, '<custom_data>') !== false) {
                                  $_custom_data = get_string_between($data, '<custom_data>', '</custom_data>');
                                  unset($additional_data[$i]);
                              }
                              $i++;
                          }
                      }
                      $_formatted_activity = _l($activity['description'], $additional_data);
                      if ($_custom_data !== false) {
                          $_formatted_activity .= ' - ' . $_custom_data;
                      }
                      if (!empty($activity['full_name'])) {
                          $_formatted_activity = $activity['full_name'] . ' - ' . $_formatted_activity;
                      }
                      echo $_formatted_activity;
                      if (is_admin()) {
                          echo '<a href="#" class="pull-right text-danger" onclick="delete_sale_activity(' . $activity['id'] . '); return false;"><i class="fa fa-remove"></i></a>';
                      } ?>
                                    </div>
                                </div>
                                <?php
                  } ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div role="tabpanel" class="tab-pane ptop10" id="tab_views">
                    <?php
                  $views_activity = get_views_tracking('order', $order->id);
                  if (count($views_activity) === 0) {
                      echo '<h4 class="tw-m-0 tw-text-base tw-font-medium tw-text-neutral-500">' . _l('not_viewed_yet', _l('order_lowercase')) . '</h4>';
                  }
                  foreach ($views_activity as $activity) { ?>
                    <p class="text-success no-margin">
                        <?php echo _l('view_date') . ': ' . _dt($activity['date']); ?>
                    </p>
                    <p class="text-muted">
                        <?php echo _l('view_ip') . ': ' . $activity['view_ip']; ?>
                    </p>
                    <hr />
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
init_items_sortable(true);
init_btn_with_tooltips();
init_datepicker();
init_selectpicker();
init_form_reminder();
init_tabs_scrollable();
<?php if ($send_later) { ?>
schedule_order_send(<?php echo $order->id; ?>);
<?php } ?>
</script>
<?php $this->load->view('admin/orders/order_send_to_client'); ?>