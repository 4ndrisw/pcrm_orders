<?php defined('BASEPATH') or exit('No direct script access allowed');
$i                   = 0;
$has_permission_edit = has_permission('orders', '', 'edit');
foreach ($order_statuses as $status) {
    $kanBan = new \app\services\orders\OrdersPipeline($status);
    $kanBan->search($this->input->get('search'))
    ->sortBy($this->input->get('sort_by'), $this->input->get('sort'));
    if ($this->input->get('refresh')) {
        $kanBan->refresh($this->input->get('refresh')[$status] ?? null);
    }
    $orders       = $kanBan->get();
    $total_orders = count($orders);
    $total_pages     = $kanBan->totalPages(); ?>
<ul class="kan-ban-col" data-col-status-id="<?php echo $status; ?>" data-total-pages="<?php echo $total_pages; ?>"
    data-total="<?php echo $total_orders; ?>">
    <li class="kan-ban-col-wrapper">
        <div class="panel_s panel-<?php echo order_status_color_class($status); ?> no-mbot">
            <div class="panel-heading">
                <?php echo order_status_by_id($status); ?> -
                <span class="tw-text-sm">
                    <?php echo $kanBan->countAll() . ' ' . _l('orders') ?>
                </span>
            </div>
            <div class="kan-ban-content-wrapper">
                <div class="kan-ban-content">
                    <ul class="sortable<?php if ($has_permission_edit) {
        echo ' status pipeline-status';
    } ?>" data-status-id="<?php echo $status; ?>">
                        <?php
            foreach ($orders as $order) {
                $this->load->view('admin/orders/pipeline/_kanban_card', ['order' => $order, 'status' => $status]);
            } ?>
                        <?php if ($total_orders > 0) { ?>
                        <li class="text-center not-sortable kanban-load-more" data-load-status="<?php echo $status; ?>">
                            <a href="#" class="btn btn-default btn-block<?php if ($total_pages <= 1 || $kanBan->getPage() === $total_pages) {
                echo ' disabled';
            } ?>" data-page="<?php echo $kanBan->getPage(); ?>"
                                onclick="kanban_load_more(<?php echo $status; ?>,this,'orders/pipeline_load_more',310,360); return false;"
                                ;><?php echo _l('load_more'); ?></a>
                        </li>
                        <?php } ?>
                        <li class="text-center not-sortable mtop30 kanban-empty<?php if ($total_orders > 0) {
                echo ' hide';
            } ?>">
                            <h4>
                                <i class="fa-solid fa-circle-notch" aria-hidden="true"></i><br /><br />
                                <?php echo _l('no_orders_found'); ?>
                            </h4>
                        </li>
                    </ul>
                </div>
            </div>
    </li>
</ul>
<?php $i++;
} ?>