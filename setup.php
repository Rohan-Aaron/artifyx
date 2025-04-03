<?php
/* Artifyx Database & System Setup */
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "artifyx_new";

// Create connection
$conn = new mysqli($host, $user, $pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create database
if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname") === TRUE) {
    echo "<p>Database created successfully</p>";
} else {
    die("Error creating database: " . $conn->error);
}

// Select database
$conn->select_db($dbname);

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('client', 'editor', 'admin') NOT NULL,
        token_balance INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )",

    "CREATE TABLE IF NOT EXISTS profiles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        bio TEXT,
        skills VARCHAR(255),
        portfolio_url VARCHAR(255),
        availability ENUM('available', 'busy', 'available_soon') DEFAULT 'available',
        hourly_rate DECIMAL(10,2),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS services (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        token_cost INT NOT NULL
    )",

    "CREATE TABLE IF NOT EXISTS projects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        editor_id INT,
        service_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES users(id),
        FOREIGN KEY (editor_id) REFERENCES users(id),
        FOREIGN KEY (service_id) REFERENCES services(id)
    )",

    "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    content TEXT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
)",

    "CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT UNIQUE NOT NULL,
        rating INT CHECK (rating BETWEEN 1 AND 5),
        comment TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (project_id) REFERENCES projects(id)
    )",
    "CREATE TABLE IF NOT EXISTS professional_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    professional_id INT NOT NULL,
    service_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (professional_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    UNIQUE KEY prof_service (professional_id, service_id)
)",
    "CREATE TABLE IF NOT EXISTS services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    token_cost INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)",
"CREATE TABLE IF NOT EXISTS portfolios (
        portfolio_id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(100) NOT NULL,
        description TEXT,
        file_path VARCHAR(255) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $sql) {
    if (!$conn->query($sql)) {
        die("Error creating table: " . $conn->error);
    }
}

// Populate initial data
if ($conn->query("SELECT COUNT(*) FROM users")->fetch_row()[0] == 0) {
    // Insert services
    $services = [
        ['Video Editing', 'Professional video editing services', 5],
        ['Photo Editing', 'Expert photo editing', 3],
        ['Logo Design', 'Custom logo creation', 4],
        ['Animation', '2D/3D animation services', 7],
        ['Flyer Design', 'Marketing material design', 2]
    ];

    $stmt = $conn->prepare("INSERT INTO services (name, description, token_cost) VALUES (?, ?, ?)");
    foreach ($services as $s) {
        $stmt->bind_param('ssi', $s[0], $s[1], $s[2]);
        $stmt->execute();
    }

    // Create users
    $users = [
        ['admin', 'admin@artifyx.com', password_hash('admin123', PASSWORD_DEFAULT), 'admin', 100],
        ['client1', 'client@example.com', password_hash('password123', PASSWORD_DEFAULT), 'client', 50],
        ['editor1', 'editor@example.com', password_hash('password123', PASSWORD_DEFAULT), 'editor', 10]
    ];

    $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, token_balance) VALUES (?, ?, ?, ?, ?)");
    foreach ($users as $u) {
        $stmt->bind_param('ssssi', $u[0], $u[1], $u[2], $u[3], $u[4]);
        $stmt->execute();
    }
}
    $conn->query("INSERT IGNORE INTO profiles (user_id, skills, availability) 
        VALUES (3, 'Video Editing, Photo Editing', 'available')");

    // Insert Services
    $conn->query("INSERT IGNORE INTO services (name, description, token_cost) VALUES
    ('Video Editing', 'Professional video editing services', 5),
    ('Photo Editing', 'Expert photo editing', 3),
    ('Logo Design', 'Custom logo creation', 4),
    ('Animation', '2D/3D animation services', 7),
    ('Flyer Design', 'Marketing material design', 2)");


    $conn->query("INSERT IGNORE INTO professional_services (professional_id, service_id) VALUES
    (1, 1), -- Assign professional_id 1 to Video Editing
    (2, 2), -- Assign professional_id 2 to Photo Editing
    (3, 3)  -- Assign professional_id 3 to Logo Design
    ");

    $conn->query("INSERT INTO portfolios (user_id, title, description, file_path) VALUES
(1, 'Wedding Video', 'A beautifully edited wedding video.', 'uploads/portfolios/wedding.mp4'),
(2, 'Company Logo', 'A modern logo design for a tech company.', 'uploads/portfolios/logo.png'),
(3, 'App Explainer', '2D animation for a mobile app.', 'uploads/portfolios/animation.mp4')");
    $conn->commit(); // Commit the transaction

echo "<h2>Setup Complete!</h2>
<p>Access Credentials:</p>
<ul>
    <li>Admin: admin@artifyx.com / admin123</li>
    <li>Client: client@example.com / password123</li>
    <li>Editor: editor@example.com / password123</li>
</ul>";

$conn->close();
?>