    <?php
    require_once '../Routing/routes.php';
    require_once '../Connection/connection.php';

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    header('Content-Type: application/json');

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

    class UserHandler {
        private $conn;

        public function __construct() {
            $database = new Database();
            $this->conn = $database->getConnection();
        }

        public function getUserProfile($userId) {
            if (!is_numeric($userId) || $userId <= 0) {
                return [
                    'status' => false,
                    'message' => 'Invalid user ID'
                ];
            }
        
            // Query patients table directly using the id field
            $query = "SELECT * FROM patients WHERE id = :id";
            
            try {
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':id', $userId);
                $stmt->execute();
        
                $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($patient) {
                    return [
                        'status' => true,
                        'user' => $patient // We keep 'user' as the key for backward compatibility
                    ];
                } else {
                    return [
                        'status' => false,
                        'message' => 'Patient not found'
                    ];
                }
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }

        public function register($data) {
            $firstName = $data['firstName'];
            $lastName = $data['lastName'];
            $date_of_birth = $data['date_of_birth'];
            $gender = $data['gender'];
            $home_address = $data['home_address'];
            $contact_number = $data['contact_number'];
            $email = $data['email'];
            $password = password_hash($data['password'], PASSWORD_DEFAULT);
            $birthplace = $data['birthplace'];
            $nationality = $data['nationality'];
            $religion = $data['religion'];
            $civilStatus = $data['civilStatus'];
            $age = $data['age'];
        
            // Check if height and weight are set, otherwise use 0
            $height = isset($data['height']) ? $data['height'] : 0;
            $weight = isset($data['weight']) ? $data['weight'] : 0;
            
            $medications = $data['medications'];
            $role = 'user';
        
            try {
                // Validate required fields (including new fields)
                if (empty($firstName) || empty($lastName) || empty($date_of_birth) || empty($gender) || empty($home_address) || empty($contact_number) || empty($email) || empty($password) || empty($medications) || empty($birthplace) || empty($nationality) || empty($religion) || empty($civilStatus) || empty($age)) {
                    echo json_encode(['status' => false, 'message' => 'All fields are required']);
                    return;
                }
        
                // Insert into users table with new fields
                $query = "INSERT INTO users (firstname, lastname, date_of_birth, gender, home_address, contact_number, email, password, role, birthplace, nationality, religion, civil_status, age) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$firstName, $lastName, $date_of_birth, $gender, $home_address, $contact_number, $email, $password, $role, $birthplace, $nationality, $religion, $civilStatus, $age]);
        
                // Get the last inserted user ID
                $userId = $this->conn->lastInsertId();
        
                // Insert into patients table with new fields
                $query = "INSERT INTO patients (id, firstname, lastname, gender, date_of_birth, home_address, contact_number, email, height, weight, medications, birthplace, nationality, religion, civil_status, age, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
                $stmt = $this->conn->prepare($query);
                $stmt->execute([$userId, $firstName, $lastName, $gender, $date_of_birth, $home_address, $contact_number, $email, $height, $weight, $medications, $birthplace, $nationality, $religion, $civilStatus, $age]);
        
                echo json_encode(['status' => true, 'message' => 'User registered successfully']);
            } catch (Exception $e) {
                error_log('Registration error: ' . $e->getMessage());
                echo json_encode(['status' => false, 'message' => 'Failed to register user: ' . $e->getMessage()]);
            }
        }    
        
        public function login($data) {
            $query = "SELECT id, firstname, lastname, email, role, password, height, weight, medications FROM users WHERE email = :email";

            try {
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':email', $data['email']);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($data['password'], $user['password'])) {
                    // Generate a token (optional)
                    $token = bin2hex(random_bytes(16));

                    // Check user role and set the correct dashboard
                    if ($user['role'] === 'admin') {
                        $dashboard = 'Admin Dashboard';
                    } elseif ($user['role'] === 'user') {
                        $dashboard = 'User Dashboard';
                    } else {
                        // Deny login if role is unknown
                        return ['status' => false, 'message' => 'Access denied for this role'];
                    }

                    return [
                        'status' => true,
                        'message' => 'Login successful',
                        'dashboard' => $dashboard,
                        'token' => $token,
                        'user' => [
                            'id' => $user['id'],
                            'firstname' => $user['firstname'],
                            'lastname' => $user['lastname'],
                            'email' => $user['email'],
                            'role' => $user['role']
                        ]
                    ];
                }

                return ['status' => false, 'message' => 'Invalid email or password'];
            } catch (PDOException $e) {
                return ['status' => false, 'message' => $e->getMessage()];
            }
        }


        public function assignRoleToUser($userId, $firstName, $lastName, $gender, $email, $role) {
            // Database connection
            $db = new Database();
            $conn = $db->getConnection();
        
            // Check if the role is doctor or patient
            if ($role === 'admin') {
                // Insert doctor details into doctors table (admin is treated as doctor)
                $query = "INSERT INTO doctors (id, first_name, last_name, gender, email, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $userId, PDO::PARAM_INT);
                $stmt->bindParam(2, $firstName, PDO::PARAM_STR);
                $stmt->bindParam(3, $lastName, PDO::PARAM_STR);
                $stmt->bindParam(4, $gender, PDO::PARAM_STR);
                $stmt->bindParam(5, $email, PDO::PARAM_STR);
        
                if ($stmt->execute()) {
                    return ['status' => true, 'message' => 'Admin assigned as doctor successfully'];
                } else {
                    return ['status' => false, 'message' => 'Failed to assign admin as doctor'];
                }
            } elseif ($role === 'patient') {
                // Insert patient details into patients table
                $query = "INSERT INTO patients (id, first_name, last_name, gender, email, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, NOW(), NOW())";
        
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $userId, PDO::PARAM_INT);
                $stmt->bindParam(2, $firstName, PDO::PARAM_STR);
                $stmt->bindParam(3, $lastName, PDO::PARAM_STR);
                $stmt->bindParam(4, $gender, PDO::PARAM_STR);
                $stmt->bindParam(5, $email, PDO::PARAM_STR);
        
                if ($stmt->execute()) {
                    return ['status' => true, 'message' => 'Patient assigned successfully'];
                } else {
                    return ['status' => false, 'message' => 'Failed to assign patient'];
                }
            } else {
                return ['status' => false, 'message' => 'Invalid role'];
            }
        }    
    


        //scheduling lineeeee.........

        public function getDoctors() {
            // Modify query to include user_id (assuming there's a relation between users and doctors)
            $query = "
                SELECT 
                    doctors.doctor_id, 
                    doctors.firstname, 
                    doctors.lastname, 
                    doctors.email, 
                    users.id AS user_id
                FROM doctors
                JOIN users ON users.email = doctors.email"; // Assuming 'email' is the link between users and doctors
            
            try {
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
            
                $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
                if ($doctors) {
                    // Handle null values and ensure consistency
                    foreach ($doctors as &$doctor) {
                        $doctor['firstname'] = $doctor['firstname'] ?? 'Unknown';
                        $doctor['lastname'] = $doctor['lastname'] ?? 'Unknown';
                    }
            
                    return [
                        'status' => true,
                        'doctors' => $doctors
                    ];
                } else {
                    return [
                        'status' => false,
                        'message' => 'No doctors found'
                    ];
                }
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        
        
        public function getPatients() {
            // Modify query to include user_id (assuming a relation exists)
            $query = "
                SELECT 
                    patients.patient_id, 
                    patients.firstname, 
                    patients.lastname, 
                    patients.email, 
                    users.id 
                FROM patients
                JOIN users ON users.email = patients.email"; // Assuming 'email' is the link between patients and users
        
            try {
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
        
                $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                if ($patients) {
                    // Handle null values and ensure consistency
                    foreach ($patients as &$patient) {
                        $patient['firstname'] = $patient['firstname'] ?? 'Unknown';
                        $patient['lastname'] = $patient['lastname'] ?? 'Unknown';
                    }
        
                    return [
                        'status' => true,
                        'patients' => $patients
                    ];
                } else {
                    return [
                        'status' => false,
                        'message' => 'No patients found'
                    ];
                }
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        } 
        
        
        public function setAppointmentTime($appointmentId, $time) {
            $db = new Database();
            $conn = $db->getConnection();
        
            $query = "UPDATE appointments SET appointment_time = ?, status = 'accepted', updated_at = NOW() WHERE appointment_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bindParam("si", $time, $appointmentId);  
        
            if ($stmt->execute()) {
                return ['status' => true, 'message' => 'Appointment time set successfully'];
            } else {
                return ['status' => false, 'message' => 'Failed to set appointment time: ' . $stmt->errorInfo()[2]];
            }
        }    

        public function updateAppointmentStatus($data) {
            try {
                error_log('Updating appointment status: ' . json_encode($data));
                
                $appointment_id = $data['appointment_id'];
                $status = $data['status'];
                $remarks = $data['remarks'] ?? null;
        
                if (empty($appointment_id) || empty($status)) {
                    return [
                        'status' => false,
                        'message' => 'Appointment ID and status are required.'
                    ];
                }
        
                // Start transaction
                $this->conn->beginTransaction();
        
                // Update the appointment
                $query = "UPDATE appointments 
                         SET status = :status, 
                             remarks = :remarks,
                             updated_at = NOW() 
                         WHERE appointment_id = :appointment_id";
                
                $stmt = $this->conn->prepare($query);
                $params = [
                    ':status' => $status,
                    ':remarks' => $remarks,
                    ':appointment_id' => $appointment_id
                ];
                
                $stmt->execute($params);
        
                if ($stmt->rowCount() > 0) {
                    // If status is approved or declined, update the doctor_schedules table
                    if ($status === 'approved' || $status === 'declined') {
                        $updateScheduleQuery = "UPDATE doctor_schedules ds
                                             JOIN appointments a ON ds.schedule_id = a.schedule_id
                                             SET ds.status = CASE 
                                                 WHEN :status = 'approved' THEN 'booked'
                                                 WHEN :status = 'declined' THEN 'available'
                                                 ELSE ds.status
                                             END
                                             WHERE a.appointment_id = :appointment_id";
                        $scheduleStmt = $this->conn->prepare($updateScheduleQuery);
                        $scheduleStmt->execute([
                            ':status' => $status,
                            ':appointment_id' => $appointment_id
                        ]);
                    }
        
                    $this->conn->commit();
                    return [
                        'status' => true,
                        'message' => 'Appointment updated successfully'
                    ];
                } else {
                    $this->conn->rollBack();
                    return [
                        'status' => false,
                        'message' => 'No changes made to appointment'
                    ];
                }
            } catch (PDOException $e) {
                $this->conn->rollBack();
                error_log('Database error in updateAppointmentStatus: ' . $e->getMessage());
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        
        public function getUsers() {
            $query = "SELECT id, firstname, lastname, gender, email,  contact_number, home_address, birthplace, age, nationality, religion, civil_status FROM users";
            
            try {
                $stmt = $this->conn->prepare($query);
                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'status' => true,
                    'users' => $users
                ];
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }

        public function getAppointments($patientId) {
            $query = "
                SELECT 
                    a.appointment_id, 
                    ds.available_date as appointment_date,  /* Using available_date from doctor_schedules */
                    ds.time_slot as appointment_time,       /* Using time_slot from doctor_schedules */
                    a.purpose, 
                    a.remarks,
                    a.status, 
                    d.firstname AS doctor_firstname, 
                    d.lastname AS doctor_lastname
                FROM 
                    appointments a
                JOIN 
                    doctors d ON a.doctor_id = d.doctor_id
                JOIN 
                    doctor_schedules ds ON a.schedule_id = ds.schedule_id
                WHERE 
                    a.patient_id = :patientId
                ORDER BY 
                    ds.available_date, ds.time_slot
            ";
        
            try {
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':patientId', $patientId, PDO::PARAM_INT);
                $stmt->execute();
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
                return [
                    'status' => true,
                    'appointments' => $appointments
                ];
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }

        public function updateUserProfile($data) {
            if (!isset($data['id']) || !is_numeric($data['id'])) {
                return [
                    'status' => false,
                    'message' => 'Invalid user ID'
                ];
            }
        
            try {
                // Update only the patients table
                $query = "UPDATE patients SET 
                    email = :email,
                    height = :height,
                    weight = :weight,
                    medications = :medications,
                    birthplace = :birthplace,
                    nationality = :nationality,
                    religion = :religion,
                    civil_status = :civilStatus,
                    date_of_birth = :date,
                    contact_number = :contact_number,
                    home_address = :home_address,
                    age = :age,
                    updated_at = NOW()
                    WHERE id = :id";
        
                $stmt = $this->conn->prepare($query);
                
                $params = [
                    ':id' => $data['id'],
                    ':email' => $data['email'],
                    ':height' => $data['height'],
                    ':weight' => $data['weight'],
                    ':medications' => $data['medications'],
                    ':birthplace' => $data['birthplace'],
                    ':nationality' => $data['nationality'],
                    ':religion' => $data['religion'],
                    ':civilStatus' => $data['civil_status'],
                    ':date' => $data['date_of_birth'],
                    ':contact_number' => $data['contact_number'],
                    ':home_address' => $data['home_address'],
                    ':age' => $data['age']
                ];
        
                $stmt->execute($params);
        
                if ($stmt->rowCount() > 0) {
                    return [
                        'status' => true,
                        'message' => 'Profile updated successfully'
                    ];
                } else {
                    return [
                        'status' => false,
                        'message' => 'No changes made or patient not found'
                    ];
                }
        
            } catch (PDOException $e) {
                return [
                    'status' => false,
                    'message' => 'Database error: ' . $e->getMessage()
                ];
            }
        }
        // Add this to your UserHandler class

    public function getPatientHistory($patientId) {
        if (!is_numeric($patientId) || $patientId <= 0) {
            return [
                'status' => false,
                'message' => 'Invalid patient ID'
            ];
        }

        $query = "SELECT * FROM patient_history WHERE patient_id = :patientId";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':patientId', $patientId, PDO::PARAM_INT);
            $stmt->execute();
            $history = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($history) {
                return [
                    'status' => true,
                    'history' => $history
                ];
            } else {
                return [
                    'status' => false,
                    'message' => 'No history found for this patient'
                ];
            }
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function updatePatientHistory($data) {
        if (!isset($data['patient_id']) || !is_numeric($data['patient_id'])) {
            return [
                'status' => false,
                'message' => 'Invalid patient ID'
            ];
        }

        $query = "INSERT INTO patient_history (
            patient_id, 
            medical_history, 
            surgical_history, 
            medications, 
            allergies, 
            injuries_accidents, 
            special_needs, 
            blood_transfusion, 
            present_history
        ) VALUES (
            :patient_id,
            :medical_history,
            :surgical_history,
            :medications,
            :allergies,
            :injuries_accidents,
            :special_needs,
            :blood_transfusion,
            :present_history
        ) ON DUPLICATE KEY UPDATE 
            medical_history = VALUES(medical_history),
            surgical_history = VALUES(surgical_history),
            medications = VALUES(medications),
            allergies = VALUES(allergies),
            injuries_accidents = VALUES(injuries_accidents),
            special_needs = VALUES(special_needs),
            blood_transfusion = VALUES(blood_transfusion),
            present_history = VALUES(present_history)";

        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute([
                ':patient_id' => $data['patient_id'],
                ':medical_history' => $data['medical_history'] ?? null,
                ':surgical_history' => $data['surgical_history'] ?? null,
                ':medications' => $data['medications'] ?? null,
                ':allergies' => $data['allergies'] ?? null,
                ':injuries_accidents' => $data['injuries_accidents'] ?? null,
                ':special_needs' => $data['special_needs'] ?? null,
                ':blood_transfusion' => $data['blood_transfusion'] ?? 'No',
                ':present_history' => $data['present_history'] ?? null
            ]);

            return [
                'status' => true,
                'message' => 'Patient history updated successfully'
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function getDoctorId($userId) {
        // Modified query to get doctor_id using the user's id from the doctors table
        $query = "SELECT doctor_id FROM doctors WHERE id = :userId LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['doctor_id'])) {
                return [
                    'status' => true,
                    'doctor_id' => $result['doctor_id']
                ];
            } else {
                // Add logging for debugging
                error_log("No doctor found for user ID: " . $userId);
                return [
                    'status' => false,
                    'message' => 'No doctor record found for this user'
                ];
            }
        } catch (PDOException $e) {
            error_log("Database error in getDoctorId: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    public function saveSchedule($data) {
        try {
            // Begin transaction
            $this->conn->beginTransaction();
            
            $doctorId = $data['doctor_id'];
            $date = $data['date'];
            $timeSlots = $data['time_slot'];
            
            // First, delete existing schedules for this date and doctor
            $deleteQuery = "DELETE FROM doctor_schedules WHERE doctor_id = ? AND available_date = ?";
            $deleteStmt = $this->conn->prepare($deleteQuery);
            $deleteStmt->execute([$doctorId, $date]);
            
            // Then insert new time slots
            $insertQuery = "INSERT INTO doctor_schedules (doctor_id, available_date, time_slot, status) VALUES (?, ?, ?, 'available')";
            $insertStmt = $this->conn->prepare($insertQuery);
            
            foreach ($timeSlots as $timeSlot) {
                $insertStmt->execute([$doctorId, $date, $timeSlot]);
            }
            
            // Commit transaction
            $this->conn->commit();
            
            return [
                'status' => true,
                'message' => 'Schedule saved successfully'
            ];
            
        } catch (PDOException $e) {
            // Rollback transaction on error
            $this->conn->rollBack();
            
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    public function getDoctorSchedules($doctorId, $date = null) {
        try {
            $query = "SELECT 
                        ds.schedule_id,
                        ds.doctor_id,
                        ds.available_date,
                        ds.time_slot,
                        ds.status,
                        d.firstname as doctor_firstname,
                        d.lastname as doctor_lastname
                    FROM doctor_schedules ds
                    JOIN doctors d ON ds.doctor_id = d.doctor_id
                    WHERE ds.doctor_id = :doctor_id 
                    AND ds.status = 'available'";
            
            $params = [':doctor_id' => $doctorId];
            
            if ($date) {
                $query .= " AND ds.available_date = :date";
                $params[':date'] = $date;
            }
            
            $query .= " ORDER BY ds.available_date, ds.time_slot";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'status' => true,
                'schedules' => $schedules
            ];
            
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    public function scheduleAppointment($data) {
        try {
            $this->conn->beginTransaction();
            
            $patientId = $data['patient_id'];
            $scheduleId = $data['schedule_id'];
            $purpose = $data['purpose'];
    
            // First verify the schedule exists and is available
            $query = "SELECT ds.*, d.doctor_id 
                     FROM doctor_schedules ds 
                     JOIN doctors d ON ds.doctor_id = d.doctor_id 
                     WHERE ds.schedule_id = ? AND ds.status = 'available'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$scheduleId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
    
            if (!$schedule) {
                $this->conn->rollBack();
                return [
                    'status' => false,
                    'message' => 'Selected time slot is no longer available'
                ];
            }
    
            // Update schedule status to booked
            $updateQuery = "UPDATE doctor_schedules SET status = 'booked' WHERE schedule_id = ?";
            $stmt = $this->conn->prepare($updateQuery);
            $stmt->execute([$scheduleId]);
    
            // Create the appointment
            $insertQuery = "INSERT INTO appointments 
                (patient_id, doctor_id, schedule_id, purpose, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW(), NOW())";
            
            $stmt = $this->conn->prepare($insertQuery);
            $stmt->execute([
                $patientId,
                $schedule['doctor_id'],
                $scheduleId,
                $purpose
            ]);
    
            $this->conn->commit();
            return [
                'status' => true,
                'message' => 'Appointment scheduled successfully'
            ];
    
        } catch (PDOException $e) {
            $this->conn->rollBack();
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function getDoctorAppointments($doctorId) {
        try {
            // First, get the doctor_id from the users table if needed
            $doctorQuery = "SELECT doctor_id FROM doctors WHERE id = :userId";
            $stmt = $this->conn->prepare($doctorQuery);
            $stmt->bindParam(':userId', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $doctorResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $actualDoctorId = $doctorResult ? $doctorResult['doctor_id'] : $doctorId;
            
            // Debug log
            error_log("User ID: $doctorId, Actual Doctor ID: $actualDoctorId");
    
            $query = "SELECT 
                a.appointment_id,
                CONCAT(p.firstname, ' ', p.lastname) as patient_name,
                p.patient_id,
                ds.available_date as appointment_date,
                ds.time_slot as appointment_time,
                a.purpose,
                a.status,
                a.remarks
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
            WHERE a.doctor_id = :doctorId
            AND a.status = 'pending'  -- Only show pending appointments in current list
            ORDER BY ds.available_date ASC, ds.time_slot ASC";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doctorId', $actualDoctorId, PDO::PARAM_INT);
            $stmt->execute();
    
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug log
            error_log("Found " . count($appointments) . " appointments for doctor");
    
            return [
                'status' => true,
                'appointments' => $appointments
            ];
        } catch (PDOException $e) {
            error_log("Database error in getDoctorAppointments: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    public function getAppointmentHistory($doctorId) {
        try {
            // First, get the doctor_id from the users table if needed
            $doctorQuery = "SELECT doctor_id FROM doctors WHERE id = :userId";
            $stmt = $this->conn->prepare($doctorQuery);
            $stmt->bindParam(':userId', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
            $doctorResult = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $actualDoctorId = $doctorResult ? $doctorResult['doctor_id'] : $doctorId;
            
            // Debug log
            error_log("User ID: $doctorId, Actual Doctor ID: $actualDoctorId");
    
            $query = "SELECT 
                a.appointment_id,
                CONCAT(p.firstname, ' ', p.lastname) as patient_name,
                p.patient_id,
                ds.available_date as appointment_date,
                ds.time_slot as appointment_time,
                a.purpose,
                a.status,
                a.remarks,
                a.updated_at
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
            WHERE a.doctor_id = :doctorId
            AND a.status IN ('approved', 'declined', 'completed')  -- Include 'approved' status
            ORDER BY a.updated_at DESC, ds.available_date DESC, ds.time_slot DESC";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doctorId', $actualDoctorId, PDO::PARAM_INT);
            $stmt->execute();
    
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Debug log
            error_log("Found " . count($appointments) . " appointments in history for doctor");
    
            return [
                'status' => true,
                'appointments' => $appointments
            ];
        } catch (PDOException $e) {
            error_log("Database error in getAppointmentHistory: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    public function getModifiedAppointments($doctorId) {
        try {
            $query = "SELECT 
                a.appointment_id,
                CONCAT(p.firstname, ' ', p.lastname) as patient_name,
                p.patient_id,
                ds.available_date as appointment_date,
                ds.time_slot as appointment_time,
                a.purpose,
                a.status,
                a.remarks,
                a.updated_at
            FROM appointments a
            JOIN patients p ON a.patient_id = p.patient_id
            JOIN doctor_schedules ds ON a.schedule_id = ds.schedule_id
            WHERE a.doctor_id = :doctorId
            AND a.status = 'rescheduled'
            ORDER BY a.updated_at DESC";
    
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':doctorId', $doctorId, PDO::PARAM_INT);
            $stmt->execute();
    
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
            return [
                'status' => true,
                'appointments' => $appointments
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    public function getPatientId($userId) {
        // Modified query to get patient_id using the user's id from the patients table
        $query = "SELECT patient_id FROM patients WHERE id = :userId LIMIT 1";
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['patient_id'])) {
                return [
                    'status' => true,
                    'patient_id' => $result['patient_id']
                ];
            } else {
                // Add logging for debugging
                error_log("No patient found for user ID: " . $userId);
                return [
                    'status' => false,
                    'message' => 'No patient record found for this user'
                ];
            }
        } catch (PDOException $e) {
            error_log("Database error in getPatientId: " . $e->getMessage());
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    // 1. Doctor sets available schedule
    public function setAvailableSchedule($data) {
        $doctorId = $data['doctor_id'] ?? null;
        $date = $data['date'] ?? null;
        $timeSlots = $data['time_slot'] ?? [];

        if (!$doctorId || !$date || empty($timeSlots)) {
            return ['status' => false, 'message' => 'Doctor ID, date, and time slots are required'];
        }

        $query = "INSERT INTO doctor_schedules (doctor_id, available_date, time_slot) VALUES (?, ?, ?)";
        $stmt = $this->conn->prepare($query);

        foreach ($timeSlots as $timeSlot) {
            $stmt->bindParam(1, $doctorId, PDO::PARAM_INT);
            $stmt->bindParam(2, $date, PDO::PARAM_STR);
            $stmt->bindParam(3, $timeSlot, PDO::PARAM_STR);

            if (!$stmt->execute()) {
                return ['status' => false, 'message' => 'Error inserting schedule'];
            }
        }

        return ['status' => true, 'message' => 'Schedule set successfully'];
    }


    // 2. Fetch available schedules for patients
    public function getAvailableSchedules($doctorId) {
        $query = "SELECT * FROM doctor_schedule WHERE doctor_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $doctorId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 3. Patient books an available slot
    public function bookAppointment($data) {
        $patientId = $data['patient_id'];
        $doctorId = $data['doctor_id'];
        $date = $data['date'];
        $time = $data['time'];
        $purpose = $data['purpose'];

        // Check if the slot is still available
        $query = "SELECT time_slots FROM doctor_schedule WHERE doctor_id = ? AND date = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $doctorId, PDO::PARAM_INT);
        $stmt->bindParam(2, $date, PDO::PARAM_STR);
        $stmt->execute();
        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$schedule) {
            return ['status' => false, 'message' => 'No available schedule for this doctor.'];
        }

        $availableSlots = json_decode($schedule['time_slots'], true);
        if (!in_array($time, $availableSlots)) {
            return ['status' => false, 'message' => 'Selected time slot is no longer available.'];
        }

        // Remove booked slot and update schedule
        $updatedSlots = array_diff($availableSlots, [$time]);
        $query = "UPDATE doctor_schedule SET time_slots = ? WHERE doctor_id = ? AND date = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, json_encode($updatedSlots), PDO::PARAM_STR);
        $stmt->bindParam(2, $doctorId, PDO::PARAM_INT);
        $stmt->bindParam(3, $date, PDO::PARAM_STR);
        $stmt->execute();

        // Insert into appointments table
        $query = "INSERT INTO appointments (patient_id, doctor_id, date, time, purpose, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $patientId, PDO::PARAM_INT);
        $stmt->bindParam(2, $doctorId, PDO::PARAM_INT);
        $stmt->bindParam(3, $date, PDO::PARAM_STR);
        $stmt->bindParam(4, $time, PDO::PARAM_STR);
        $stmt->bindParam(5, $purpose, PDO::PARAM_STR);

        if ($stmt->execute()) {
            return ['status' => true, 'message' => 'Appointment booked successfully'];
        } else {
            return ['status' => false, 'message' => 'Failed to book appointment'];
        }
    }



    }


    // Route user actions
    $userHandler = new UserHandler();

    // Ensure the action is passed through the URL query string (GET method)
    $action = $_GET['action'] ?? null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($action === 'register') {
            // Decode incoming JSON data
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $requiredFields = [
                'firstName', 'lastName', 'date_of_birth', 'gender', 
                'home_address', 'contact_number', 'email', 
                'password', 'medications', 
            ];

            // Check for non-numeric fields (string fields)
            foreach ($requiredFields as $field) {
                if (empty($data[$field]) && !isset($data[$field])) {
                    echo json_encode(['status' => false, 'message' => "$field is required"]);
                    return;
                }
            }

            // Special validation for numeric fields (height and weight)
            if (!isset($data['height'])) {
                echo json_encode(['status' => false, 'message' => "height is required"]);
                return;
            }
            if (!isset($data['weight'])) {
                echo json_encode(['status' => false, 'message' => "weight is required"]);
                return;
            }

            // Call the register method with the full data
            $userHandler->register($data);
        } elseif ($action === 'login') {
            // Login functionality remains the same...
            $data = json_decode(file_get_contents('php://input'), true);

            // Get email and password from the incoming data
            $email = $data['email'] ?? null;
            $password = $data['password'] ?? null;

            // Validate input data
            if ($email && $password) {
                // Database connection
                $db = new Database();
                $conn = $db->getConnection();

                // Query to check if the user exists
                $query = "SELECT id, firstname, lastname, password, role FROM users WHERE email = ?";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(1, $email);
                $stmt->execute();

                // Debug: Check if the query returns any result
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$user) {
                    // Debugging - if no user found
                    echo json_encode(['status' => false, 'message' => 'User not found']);
                    return;
                }

                // Verify password (hash comparison, assuming you hash passwords when storing)
                if (password_verify($password, $user['password'])) {
                    // Password is correct, return user details and role
                    echo json_encode([
                        'status' => true,
                        'message' => 'Login successful',
                        'user' => [
                            'id' => $user['id'],
                            'firstName' => $user['firstname'],
                            'lastName' => $user['lastname'],
                            'role' => $user['role']
                        ]
                    ]);
                } else {
                    // Debugging - if the password doesn't match
                    echo json_encode(['status' => false, 'message' => 'Invalid credentials']);
                }
            } else {
                echo json_encode(['status' => false, 'message' => 'Email and password are required']);
            }
        } elseif ($action === 'updateUserProfile') {
            $data = json_decode(file_get_contents('php://input'), true);
            $response = $userHandler->updateUserProfile($data);
            echo json_encode($response);
            
        } 
        elseif ($action === 'updatePatientHistory') {
            $data = json_decode(file_get_contents('php://input'), true);
            $response = $userHandler->updatePatientHistory($data);
            echo json_encode($response);
        } elseif ($action === 'setAvailableSchedule') {
            // Decode incoming JSON data
            $data = json_decode(file_get_contents('php://input'), true);
        
            // Call the method from the UserHandler instance
            $response = $userHandler->setAvailableSchedule($data);
        
            // Return the response from the method
            echo json_encode($response);
        } else if ($action === 'saveSchedule') {
            // Decode incoming JSON data
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Call the method from the UserHandler instance
            $response = $userHandler->saveSchedule($data);
            
            // Return the response from the method
            echo json_encode($response);
        }  elseif ($action === 'scheduleAppointment') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Call the scheduleAppointment method from UserHandler
            $response = $userHandler->scheduleAppointment($data);
            echo json_encode($response);
            return;
        } elseif ($action === 'updateAppointmentStatus') {  // Add this block
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['appointment_id']) && isset($data['status'])) {
                $response = $userHandler->updateAppointmentStatus($data);
                echo json_encode($response);
            } 
        }else {
            echo json_encode(['status' => false, 'message' => 'Invalid action']);
        }
        
    }


    // Only one block to handle GET requests

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (isset($_GET['action'])) {
            $action = $_GET['action'];

            if ($action === 'getDoctors') {
                $response = $userHandler->getDoctors();
                echo json_encode($response);
            } elseif ($action === 'getUserProfile') {
                $userId = $_GET['id'] ?? null;
                if ($userId) {
                    $response = $userHandler->getUserProfile($userId);
                    echo json_encode($response);
                } else {
                    echo json_encode(['status' => false, 'message' => 'User ID is required']);
                }
            } elseif ($action === 'getPatients') {
                $response = $userHandler->getPatients();
                echo json_encode($response);
            } elseif ($action === 'getUsers') {
                $response = $userHandler->getUsers();
                echo json_encode($response);
            } elseif ($action === 'getAppointments') {
                $patientId = $_GET['patient_id'] ?? null;
                if ($patientId) {
                    $response = $userHandler->getAppointments($patientId);
                    echo json_encode($response);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Patient ID is required']);
                }
            } // Add the new getPatientHistory route here
            elseif ($action === 'getPatientHistory') {
                $patientId = $_GET['patient_id'] ?? null;
                if ($patientId) {
                    $response = $userHandler->getPatientHistory($patientId);
                    echo json_encode($response);
                } else {
                    echo json_encode(['status' => false, 'message' => 'Patient ID is required']);
                }
            }
        }
        if ($action === 'getPatientId') {
            $userId = $_GET['user_id'] ?? null;
            if ($userId) {
                $response = $userHandler->getPatientId($userId);
                echo json_encode($response);
                exit; // Add exit to prevent further execution
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'User ID is required']);
                exit;
            }
        } else if ($action === 'getDoctorId') {
            $userId = $_GET['user_id'] ?? null;
            if ($userId) {
                $response = $userHandler->getDoctorId($userId);
                echo json_encode($response);
                exit; // Add exit to prevent further execution
            } else {
                http_response_code(400);
                echo json_encode(['status' => false, 'message' => 'User ID is required']);
                exit;
            }

    } elseif ($action === 'getDoctorSchedules') {
        $doctorId = $_GET['doctor_id'] ?? null;
        $date = $_GET['date'] ?? null;
        
        if ($doctorId) {
            $response = $userHandler->getDoctorSchedules($doctorId, $date);
            echo json_encode($response);
        } else {
            echo json_encode(['status' => false, 'message' => 'Doctor ID is required']);
        }
    }  elseif ($action === 'getDoctorAppointments') {
        $doctorId = $_GET['doctor_id'] ?? null;
        if ($doctorId) {
            $response = $userHandler->getDoctorAppointments($doctorId);
            echo json_encode($response);
        } else {
            echo json_encode(['status' => false, 'message' => 'Doctor ID is required']);
        }
    
    } elseif ($action === 'getAppointmentHistory') {
        $doctorId = $_GET['doctor_id'] ?? null;
        if ($doctorId) {
            $response = $userHandler->getAppointmentHistory($doctorId);
            echo json_encode($response);
        } else {
            echo json_encode(['status' => false, 'message' => 'Doctor ID is required']);
        }
    } elseif ($action === 'getModifiedAppointments') {
        $doctorId = $_GET['doctor_id'] ?? null;
        if ($doctorId) {
            $response = $userHandler->getModifiedAppointments($doctorId);
            echo json_encode($response);
        } else {
            echo json_encode(['status' => false, 'message' => 'Doctor ID is required']);
        }

    }
    }