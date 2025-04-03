<?php
session_start();
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "artifyx_new";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>