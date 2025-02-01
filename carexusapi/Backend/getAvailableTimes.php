<?php
require_once '../Routing/routes.php';
require_once '../Connection/connection.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = $_GET['date'] ?? null;

    if ($date) {
        $userHandler = new UserHandler();
        $response = $userHandler->getAvailableTimes($date); // Implement this method in UserHandler
        echo json_encode($response);
    } else {
        echo json_encode(['status' => false, 'message' => 'Date is required']);
    }
}
?> 