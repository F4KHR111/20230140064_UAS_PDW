<?php
session_start();

// Hapus semua variabel session
$_SESSION = array();

// Hancurkan session
session_destroy();

// Redirect ke halaman login
header("Location: /20230140064_UAS_PDW/login.php");
exit;
?>