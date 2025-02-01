<?php
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module = $_GET['module'];
    $action = $_GET['action'];

    if ($module === 'carexus') {
        require_once './Backend/carexus.php';
    } elseif ($module === 'systemb') {
        require_once './Backend/systemb.php';
    }
}
?>
