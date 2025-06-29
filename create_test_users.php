<?php
// create_test_users.php - Run this once to create test users
require_once 'includes/db.php';

// Test users data
$test_users = [
    [
        'username' => '2021001',
        'password' => 'password123',
        'first_name' => 'Maria',
        'last_name' => 'Theresa',
        'email' => 'maria@student.edu'
    ],
    [
        'username' => '2021002', 
        'password' => 'password123',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@student.edu'
    ],
    [
        'username' => '2021003',
        'password' => 'password123', 
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane@student.edu'
    ]
];

try {
    foreach ($test_users as $user) {
        // Hash the password
        $hashed_password = password_hash($user['password'], PASSWORD_DEFAULT);
        
        // Check if user already exists
        $check_stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $check_stmt->execute([$user['username']]);
        
        if (!$check_stmt->fetch()) {
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, first_name, last_name, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $user['username'],
                $hashed_password,
                $user['first_name'],
                $user['last_name'],
                $user['email']
            ]);
            echo "Created user: " . $user['username'] . " (" . $user['first_name'] . " " . $user['last_name'] . ")<br>";
        } else {
            echo "User " . $user['username'] . " already exists<br>";
        }
    }
    echo "<br>Test users created successfully!<br>";
    echo "<strong>Login credentials:</strong><br>";
    echo "Student Number: 2021001, Password: password123<br>";
    echo "Student Number: 2021002, Password: password123<br>";
    echo "Student Number: 2021003, Password: password123<br>";
    
} catch (PDOException $e) {
    echo "Error creating users: " . $e->getMessage();
}
?>