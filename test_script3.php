<?php
$history = new stdClass();

// Testing object handling
$items = (is_array($history) && isset($history['items'])) ? $history['items'] : array();
$total_items = (is_array($history) && isset($history['total'])) ? (int) $history['total'] : 0;

echo "Success\n";
