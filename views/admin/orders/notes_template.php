<?php defined('BASEPATH') or exit('No direct script access allowed');
   ob_start();
   $len = count($notes);
   $i = 0;
   foreach ($notes as $note) { ?>
<div class="col-md-12 note-item" data-noteid="<?php echo $note['id']; ?>">
   <?php if($note['staffid'] != 0){ ?>
   <a href="<?php echo admin_url('profile/' . $note['staffid']); ?>">
   <?php
      echo staff_profile_image($note['staffid'], array(
        'staff-profile-image-small',
        'media-object img-circle pull-left mright10'
        ));
        ?>
   </a>
   <?php } ?>
   <?php if (($note['staffid'] == get_staff_user_id()) || is_admin()) { ?>
      <a href="#" class="pull-right" onclick="remove_order_note(<?php echo $note['id']; ?>); return false;">
      <i class="fa fa-times text-danger"></i>
         <a href="#" class="pull-right mright5" onclick="toggle_order_note_edit(<?php echo $note['id']; ?>);return false;"><i class="fa fa-pencil-square"></i></a>
      </a>
   <?php } ?>
   <div class="media-body">
      <div class="mtop5">
      <?php if($note['staffid'] != 0){ ?>
         <a href="<?php echo admin_url('profile/' . $note['staffid']); ?>"><?php echo get_staff_full_name($note['staffid']); ?></a>
      <?php } else { ?>
         <?php echo '<b>' . _l('is_customer_indicator') . '</b>'; ?>
      <?php } ?>
         <small class="text-muted text-has-action" data-toggle="tooltip" data-title="<?php echo _dt($note['dateadded']); ?>"> - <?php echo time_ago($note['dateadded']); ?></small>
      </div>
      <br />
      <div data-order-note="<?php echo $note['id']; ?>">
         <?php echo check_for_links($note['content']); ?>
      </div>
      <div data-order-note-edit-textarea="<?php echo $note['id']; ?>" class="hide mtop15">
         <?php echo render_textarea('note-content','',$note['content']); ?>
         <?php if($note['staffid'] == get_staff_user_id() || is_admin()){ ?>
         <div class="text-right">
            <button type="button" class="btn btn-default" onclick="toggle_order_note_edit(<?php echo $note['id']; ?>);return false;"><?php echo _l('cancel'); ?></button>
            <button type="button" class="btn btn-info" onclick="edit_order_note(<?php echo $note['id']; ?>);"><?php echo _l('update_note'); ?></button>
         </div>
         <?php } ?>
      </div>
   </div>
   <?php if ($i >= 0 && $i != $len - 1) {
      echo '<hr />';
      }
      ?>
</div>
<?php $i++; } ?>
