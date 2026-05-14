<?php
require '../../app/config/koneksi.php';

$token = $_POST['token'];
$password = password_hash($_POST['password'], PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users 
SET password=?, reset_token=NULL, reset_expired=NULL 
WHERE reset_token=?");

$stmt->bind_param("ss",$password,$token);
$stmt->execute();

header("Location: ../join-us.php?mode=login&reset=success");