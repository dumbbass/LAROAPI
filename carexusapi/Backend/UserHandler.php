<?php

class UserHandler {
    public function getAvailableTimes($date) {
        $query = "SELECT time_slot FROM doctor_schedules WHERE available_date = :date";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => true,
                'times' => array_column($times, 'time_slot') // Extract time_slot values
            ];
        } catch (PDOException $e) {
            return [
                'status' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
} 