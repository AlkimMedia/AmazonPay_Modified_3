<?php
include 'includes/application_top.php';

global $order, $order_totals, $shipping_modules, $order_total_modules;
require_once DIR_WS_CLASSES . 'payment.php';
require_once DIR_WS_CLASSES . 'shipping.php';
$shipping_modules = new shipping($_SESSION['shipping']);
require_once DIR_WS_CLASSES . 'order.php';
$order = new order();
require_once DIR_WS_CLASSES . 'order_total.php';
$order_total_modules = new order_total();
$order_totals = $order_total_modules->process();

echo '<div id="amazon-pay-button-hidden" style="display: none;"></div>';

include 'includes/application_bottom.php';