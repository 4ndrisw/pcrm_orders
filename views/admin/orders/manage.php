<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<?php init_head(); ?>
<div id="wrapper">
    <div class="content">
        <div class="row">
            <div class="panel-table-full">
                <?php $this->load->view('admin/orders/list_template'); ?>
            </div>
        </div>
    </div>
</div>
<?php $this->load->view('admin/includes/modals/sales_attach_file'); ?>
<script>
var hidden_columns = [2, 5, 6, 8, 9];
</script>
<?php init_tail(); ?>
<script>
    /*
$(function() {
    init_order();
});
*/
   var order_id;
   $(function(){
     var Orders_ServerParams = {};
     $.each($('._hidden_inputs._filters input'),function(){
       Orders_ServerParams[$(this).attr('name')] = '[name="'+$(this).attr('name')+'"]';
     });
     initDataTable('.table-orders', admin_url+'orders/table', ['undefined'], ['undefined'], Orders_ServerParams, [7, 'desc']);
     init_order();
   });
</script>
</script>
</body>

</html>