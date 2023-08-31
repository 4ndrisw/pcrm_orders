// Init single order
function init_order(id) {
    load_small_table_item(id, '#order', 'orderid', 'orders/get_order_data_ajax', '.table-orders');
}


// Validates order add/edit form
function validate_order_form(selector) {

    selector = typeof (selector) == 'undefined' ? '#order-form' : selector;

    appValidateForm($(selector), {
        clientid: {
            required: {
                depends: function () {
                    var customerRemoved = $('select#clientid').hasClass('customer-removed');
                    return !customerRemoved;
                }
            }
        },
        date: 'required',
        office_id: 'required',
        number: {
            required: true
        }
    });

    $("body").find('input[name="number"]').rules('add', {
        remote: {
            url: admin_url + "orders/validate_order_number",
            type: 'post',
            data: {
                number: function () {
                    return $('input[name="number"]').val();
                },
                isedit: function () {
                    return $('input[name="number"]').data('isedit');
                },
                original_number: function () {
                    return $('input[name="number"]').data('original-number');
                },
                date: function () {
                    return $('body').find('.order input[name="date"]').val();
                },
            }
        },
        messages: {
            remote: app.lang.order_number_exists,
        }
    });

}


function add_order_note() {
    var note = $('#note').val();
    if (note == '') {
        return;
    }
    var data = {};
    data.content = note;
    data.orderid = order_id;
    $('body').append('<div class="dt-loader"></div>');
    $.post(admin_url + 'orders/add_order_note', data).done(function (response) {
        response = JSON.parse(response);
        $('body').find('.dt-loader').remove();
        if (response.success == true) {
            $('#note').val('');
            get_order_notes();
        }
    });
}

function get_order_notes() {
    if (typeof (order_id) == 'undefined') {
        return;
    }
    requestGet('orders/get_order_notes/' + order_id).done(function (response) {
        $('body').find('#order-notes').html(response);
        update_notes_count('order')
    });
}

function remove_order_note(noteid) {
    if (confirm_delete()) {
        requestGetJSON('orders/remove_note/' + noteid).done(function (response) {
            if (response.success == true) {
                $('[data-noteid="' + noteid + '"]').remove();
                update_notes_count('order')
            }
        });
    }
}

function edit_order_note(id) {
    var content = $('body').find('[data-order-note-edit-textarea="' + id + '"] textarea').val();
    if (content != '') {
        $.post(admin_url + 'orders/edit_note/' + id, {
            content: content
        }).done(function (response) {
            response = JSON.parse(response);
            if (response.success == true) {
                alert_float('success', response.message);
                $('body').find('[data-order-note="' + id + '"]').html(nl2br(content));
            }
        });
        toggle_order_note_edit(id);
    }
}

function toggle_order_note_edit(id) {
    $('body').find('[data-order-note="' + id + '"]').toggleClass('hide');
    $('body').find('[data-order-note-edit-textarea="' + id + '"]').toggleClass('hide');
}



function update_notes_count() {
  var count = $(".note-item").length;
  $(".total_notes").text(count);
  if (count === 0) {
    $(".total_notes").addClass("hide");
  } else {
    $(".total_notes").removeClass("hide");
  }
}

// Get the preview main values
function get_order_item_preview_values() {
    var response = {};
    response.description = $('.main textarea[name="description"]').val();
    response.long_description = $('.main textarea[name="long_description"]').val();
    response.qty = $('.main input[name="quantity"]').val();
    return response;
}

// Append the added items to the preview to the table as items
function add_order_item_to_table(data, itemid){

  // If not custom data passed get from the preview
  data = typeof (data) == 'undefined' || data == 'undefined' ? get_order_item_preview_values() : data;
  if (data.description === "" && data.long_description === "") {
     return;
  }

  var table_row = '';
  var item_key = lastAddedItemKey ? lastAddedItemKey += 1 : $("body").find('tbody .item').length + 1;
  lastAddedItemKey = item_key;

  table_row += '<tr class="sortable item">';

  table_row += '<td class="dragger">';

  // Check if quantity is number
  if (isNaN(data.qty)) {
     data.qty = 1;
  }

  $("body").append('<div class="dt-loader"></div>');
  var regex = /<br[^>]*>/gi;

     table_row += '<input type="hidden" class="order" name="newitems[' + item_key + '][order]">';

     table_row += '</td>';

     table_row += '<td class="bold description"><textarea name="newitems[' + item_key + '][description]" class="form-control" rows="5">' + data.description + '</textarea></td>';

     table_row += '<td><textarea name="newitems[' + item_key + '][long_description]" class="form-control item_long_description" rows="5">' + data.long_description.replace(regex, "\n") + '</textarea></td>';
   //table_row += '<td><textarea name="newitems[' + item_key + '][long_description]" class="form-control item_long_description" rows="5">' + data.long_description + '</textarea></td>';


     table_row += '<td><input type="number" min="0" onblur="calculate_total();" onchange="calculate_total();" data-quantity name="newitems[' + item_key + '][qty]" value="' + data.qty + '" class="form-control">';

     if (!data.unit || typeof (data.unit) == 'undefined') {
        data.unit = '';
     }

     table_row += '<input type="text" placeholder="' + app.lang.unit + '" name="newitems[' + item_key + '][unit]" class="form-control input-transparent text-right" value="' + data.unit + '">';

     table_row += '</td>';


     table_row += '<td><a href="#" class="btn btn-danger pull-left" onclick="delete_item(this,' + itemid + '); return false;"><i class="fa fa-trash"></i></a></td>';

     table_row += '</tr>';

     $('table.items tbody').append(table_row);

     $(document).trigger({
        type: "item-added-to-table",
        data: data,
        row: table_row
     });


     clear_item_preview_values();
     reorder_items();

     $('body').find('#items-warning').remove();
     $("body").find('.dt-loader').remove();

  return false;
}


// From order table mark as
function order_mark_as(status_id, order_id) {
    var data = {};
    data.status = status_id;
    data.orderid = order_id;
    $.post(admin_url + 'orders/update_order_status', data).done(function (response) {
        //table_orders.DataTable().ajax.reload(null, false);
        reload_orders_tables();
    });
}

// Reload all orders possible table where the table data needs to be refreshed after an action is performed on task.
function reload_orders_tables() {
    var av_orders_tables = ['.table-orders', '.table-rel-orders'];
    $.each(av_orders_tables, function (i, selector) {
        if ($.fn.DataTable.isDataTable(selector)) {
            $(selector).DataTable().ajax.reload(null, false);
        }
    });
}
