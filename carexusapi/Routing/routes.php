<?php
class Routes {
    public function redirect($action, $file) {
        switch ($action) {
            case 'register':
            case 'login':
                require_once $file;
                break;
            default:
                echo json_encode(['status' => false, 'message' => 'Invalid action']);
        }
    }
}
?>
