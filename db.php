<?php
$servername = "localhost";
$username = "root";
$password = "12345678";
$database = "travel_system";

$conn = mysqli_connect($servername, $username, $password, $database);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>
