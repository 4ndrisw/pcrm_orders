<?php defined('BASEPATH') or exit('No direct script access allowed');
   if ($order['status'] == $status) { ?>
<li data-order-id="<?php echo $order['id']; ?>" class="<?php if ($order['invoiceid'] != null) {
       echo 'not-sortable';
   } ?>">
    <div class="panel-body">
        <div class="row">
            <div class="col-md-12">
                <h4 class="tw-font-semibold tw-text-base pipeline-heading tw-mb-0.5">
                    <a href="<?php echo admin_url('orders/list_orders/' . $order['id']); ?>"
                        class="tw-text-neutral-700 hover:tw-text-neutral-900 active:tw-text-neutral-900"
                        onclick="order_pipeline_open(<?php echo $order['id']; ?>); return false;">
                        <?php echo format_order_number($order['id']); ?>
                    </a>
                    <?php if (has_permission('orders', '', 'edit')) { ?>
                    <a href="<?php echo admin_url('orders/order/' . $order['id']); ?>" target="_blank"
                        class="pull-right">
                        <small>
                            <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>
                        </small>
                    </a>
                    <?php } ?>
                </h4>
                <span class="tw-inline-block tw-w-full tw-mb-2">
                    <a href="<?php echo admin_url('clients/client/' . $order['clientid']); ?>" target="_blank">
                        <?php echo $order['company']; ?>
                    </a>
                </span>
            </div>
            <div class="col-md-12">
                <div class="tw-flex">
                    <div class="tw-grow">
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?php echo _l('order_total'); ?>:
                            </span>
                            <?php echo app_format_money($order['total'], $order['currency_name']); ?>
                        </p>
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?php echo _l('order_data_date'); ?>:
                            </span>
                            <?php echo _d($order['date']); ?>
                        </p>
                        <?php if (is_date($order['expirydate']) || !empty($order['expirydate'])) { ?>
                        <p class="tw-mb-0 tw-text-sm tw-text-neutral-700">
                            <span class="tw-text-neutral-500">
                                <?php echo _l('order_data_expiry_date'); ?>:
                            </span>
                            <?php echo _d($order['expirydate']); ?>
                        </p>
                        <?php } ?>
                    </div>
                    <div class="tw-shrink-0 text-right">
                        <small>
                            <i class="fa fa-paperclip"></i>
                            <?php echo _l('order_notes'); ?>:
                            <?php echo total_rows(db_prefix() . 'notes', [
                        'rel_id'   => $order['id'],
                        'rel_type' => 'order',
                    ]); ?>
                        </small>
                    </div>
                    <?php $tags = get_tags_in($order['id'], 'order'); ?>
                    <?php if (count($tags) > 0) { ?>
                    <div class="kanban-tags tw-text-sm tw-inline-flex">
                        <?php echo render_tags($tags); ?>
                    </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</li>
<?php } ?>