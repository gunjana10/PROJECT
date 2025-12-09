<?php
session_start();
include("db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $destination = mysqli_real_escape_string($conn, $_POST['destination']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $travelers = mysqli_real_escape_string($conn, $_POST['travelers']);
    $price = mysqli_real_escape_string($conn, str_replace('RS.', '', $_POST['price']));
    $special_requests = mysqli_real_escape_string($conn, $_POST['special_requests']);

    $query = "INSERT INTO bookings (full_name, email, phone, destination, start_date, end_date, travelers, price, special_requests) 
              VALUES ('$full_name', '$email', '$phone', '$destination', '$start_date', '$end_date', '$travelers', '$price', '$special_requests')";

    if (mysqli_query($conn, $query)) {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=success");
    } else {
        header("Location: " . $_SERVER['HTTP_REFERER'] . "?booking=error");
    }
    exit();
}
?>