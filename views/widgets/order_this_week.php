<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php
    $CI = &get_instance();
    $CI->load->model('orders/orders_model');
    $orders = $CI->orders_model->get_orders_this_week(get_staff_user_id());

?>

<div class="widget" id="widget-<?php echo create_widget_id(); ?>" data-name="<?php echo _l('order_this_week'); ?>">
    <?php if(staff_can('view', 'orders') || staff_can('view_own', 'orders')) { ?>
    <div class="panel_s orders-expiring">
        <div class="panel-body padding-10">
            <p class="padding-5"><?php echo _l('order_this_week'); ?></p>
            <hr class="hr-panel-heading-dashboard">
            <?php if (!empty($orders)) { ?>
                <div class="table-vertical-scroll">
                    <a href="<?php echo admin_url('orders'); ?>" class="mbot20 inline-block full-width"><?php echo _l('home_widget_view_all'); ?></a>
                    <table id="widget-<?php echo create_widget_id(); ?>" class="table dt-table dt-inline dataTable no-footer" data-order-col="3" data-order-type="desc">
                        <thead>
                            <tr>
                                <th><?php echo _l('order_number'); ?> #</th>
                                <th class="<?php echo (isset($client) ? 'not_visible' : ''); ?>"><?php echo _l('order_list_client'); ?></th>
                                <th><?php echo _l('order_list_project'); ?></th>
                                <th><?php echo _l('order_list_date'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order) { ?>
                                <tr class="<?= 'order_status_' . $order['status']?>">
                                    <td>
                                        <?php echo '<a href="' . admin_url("orders/order/" . $order["id"]) . '">' . format_order_number($order["id"]) . '</a>'; ?>
                                    </td>
                                    <td>
                                        <?php echo '<a href="' . admin_url("clients/client/" . $order["userid"]) . '">' . $order["company"] . '</a>'; ?>
                                    </td>
                                    <td>
                                        <?php echo '<a href="' . admin_url("projects/view/" . $order["projects_id"]) . '">' . $order['name'] . '</a>'; ?>
                                    </td>
                                    <td>
                                        <?php echo _d($order['date']); ?>
                                    </td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            <?php } else { ?>
                <div class="text-center padding-5">
                    <i class="fa fa-check fa-5x" aria-hidden="true"></i>
                    <h4><?php echo _l('no_order_this_week',["7"]) ; ?> </h4>
                </div>
            <?php } ?>
        </div>
    </div>
    <?php } ?>
</div>
