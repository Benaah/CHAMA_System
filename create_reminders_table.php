<?php
require_once '../config.php';

try {
    // Create personal_reminders table
    $sql = "CREATE TABLE IF NOT EXISTS personal_reminders (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL REFERENCES users(id),
        title VARCHAR(255) NOT NULL,
        reminder_date DATE NOT NULL,
        reminder_time TIME,
        notes TEXT,
        completed BOOLEAN DEFAULT FALSE,
        completed_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $pdo->exec($sql);
    echo "Personal reminders table created successfully!";
} catch (PDOException $e) {
    echo "Error creating personal reminders table: " . $e->getMessage();
}
?>