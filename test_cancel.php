<?php
require 'config.php';
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'customer';
$_POST['action'] = 'cancel_order';
$data = ['order_id' => 39, 'action' => 'cancel_order'];
require 'api/orders.php';
