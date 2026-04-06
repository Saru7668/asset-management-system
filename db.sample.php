<?php
// db.php
$host = 'localhost';
$db = '';
$user = ''; 
$pass = ''; // ???? ????? ???????? ????? ??? ???? ??? ????

// MySQLi Connection
$conn = mysqli_connect($host, $user, $pass, $db);

// Check connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
