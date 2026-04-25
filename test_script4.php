<?php
$history = new stdClass();
$items = (is_array($history) && isset($history['items'])) ? $history['items'] : array();
var_dump($items);
