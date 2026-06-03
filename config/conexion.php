<?php
// config/conexion.php
// Conexión real con PDO a MySQL

$host = "localhost";
$dbname = "biblioteca";   // nombre de tu base de datos
$user = "root";           // usuario de MySQL
$pass = "";               // contraseña de MySQL (en XAMPP suele estar vacía)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    // Configuramos atributos de PDO
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ Error de conexión a la base de datos: " . $e->getMessage());
}
?>
