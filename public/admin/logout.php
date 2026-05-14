<?php
session_start();

/* Hapus hanya session admin, session user tidak ikut terhapus */
unset($_SESSION['admin']);

header('Location: login.php');
exit;