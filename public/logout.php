<?php
// logout.php - Cierre de sesión seguro
session_start();
session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
