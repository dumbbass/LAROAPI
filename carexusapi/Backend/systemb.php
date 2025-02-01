<?php
require_once '../Routing/routes.php';
require_once '../Connection/connection.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Set CORS headers for preflight request
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    exit(0);  // Exit after responding to OPTIONS request
}

$routes = new Routes();
$data = json_decode(file_get_contents("php://input"), true);
$action = $_GET['action'] ?? '';

class AdminHandler {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    // Function to check if the email already exists
    public function checkEmail($email) {
        try {
            $query = "SELECT COUNT(*) as count FROM users WHERE email = :email";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // If email exists, return true, else false
            if ($result['count'] > 0) {
                return ['exists' => true];
            } else {
                return ['exists' => false];
            }
        } catch (PDOException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function register($data) {
        $firstName = $data['firstName'];
        $lastName = $data['lastName'];
        $dob = $data['dob'];
        $gender = $data['gender'];
        $homeAddress = $data['homeAddress'];
        $contactNumber = $data['contactNumber'];
        $email = $data['email'];
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Set role as 'admin' for admin users
        $role = 'admin';
    
        try {
            // Validate required fields
            if (empty($firstName) || empty($lastName) || empty($dob) || empty($gender) || empty($homeAddress) || empty($contactNumber) || empty($email) || empty($password)) {
                return ['status' => false, 'message' => 'All fields are required'];
            }
    
            // Check if email already exists
            $emailCheck = $this->checkEmail($email);
            if ($emailCheck['exists']) {
                return ['status' => false, 'message' => 'Email already exists'];
            }
    
            // Insert into users table
            $query = "INSERT INTO users (firstname, lastname, date_of_birth, gender, home_address, contact_number, email, password, role) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$firstName, $lastName, $dob, $gender, $homeAddress, $contactNumber, $email, $password, $role]);
    
            // Get the last inserted user ID (for reference in the doctors table)
            $userId = $this->conn->lastInsertId();
    
            // Insert into doctors table
            $query = "INSERT INTO doctors (id, firstname, lastname, gender, email, created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$userId, $firstName, $lastName, $gender, $email]);
    
            // Return success message
            return ['status' => true, 'message' => 'Admin registered and added to doctors successfully'];
        } catch (Exception $e) {
            // Log the error and return failure message
            error_log('Registration error: ' . $e->getMessage());
            return ['status' => false, 'message' => 'Failed to register admin and add to doctors: ' . $e->getMessage()];
        }
    }    

    // Function for login
    public function login($data) {
        $query = "SELECT id, firstname, lastname, email, role, password FROM users WHERE email = :email";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $data['email']);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
            // Check if user exists and password is correct
            if ($user && password_verify($data['password'], $user['password'])) {
                // Only allow login if the user is an admin
                if ($user['role'] === 'admin') {
                    return [
                        'status' => true,
                        'dashboard' => 'Admin Dashboard',
                        'user' => $user
                    ];
                } else {
                    // Deny login for non-admin users (e.g., users with role 'user')
                    return ['status' => false, 'message' => 'Access denied for non-admin users'];
                }
            }
            return ['status' => false, 'message' => 'Invalid email or password'];
        } catch (PDOException $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }    
}

// Route admin actions
$adminHandler = new AdminHandler();
$data = json_decode(file_get_contents("php://input"), true); // Get the data from the POST request
$action = $_GET['action'] ?? ''; // Get the action from the query string (e.g., action=register)

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'register') {
        // Directly call the register method without needing a loggedInUserId
        echo json_encode($adminHandler->register($data)); 
    } elseif ($action === 'login') {
        // If the action is 'login', call the login function
        echo json_encode($adminHandler->login($data));
    } else {
        // Handle invalid actions
        echo json_encode(['status' => false, 'message' => 'Invalid action']);
    }
} else {
    // Handle unsupported request methods
    echo json_encode(['status' => false, 'message' => 'Invalid request method']);
}

// Handle GET request for email check
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'checkEmail') {
    $email = $_GET['email'] ?? '';
    if ($email) {
        echo json_encode($adminHandler->checkEmail($email));
    } else {
        echo json_encode(['status' => false, 'message' => 'Email parameter is required']);
    }
}
?>
