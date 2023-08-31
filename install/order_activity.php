<?php defined('BASEPATH') or exit('No direct script access allowed');


if (!$CI->db->table_exists(db_prefix() . 'order_activity')) {
    $CI->db->query('CREATE TABLE `' . db_prefix() . "order_activity` (
      `id` int(11) NOT NULL,
      `rel_type` varchar(20) DEFAULT NULL,
      `rel_id` int(11) NOT NULL,
      `description` text NOT NULL,
      `additional_data` text DEFAULT NULL,
      `staffid` varchar(11) DEFAULT NULL,
      `full_name` varchar(100) DEFAULT NULL,
      `date` datetime NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=" . $CI->db->char_set . ';');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_activity`
        ADD PRIMARY KEY (`id`),
        ADD KEY `date` (`date`);
    ');

    $CI->db->query('ALTER TABLE `' . db_prefix() . 'order_activity`
      MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1');
}