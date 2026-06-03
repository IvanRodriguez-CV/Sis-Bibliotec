<?php
// config/config.php
// Archivo de configuración global del sistema

// URL base del proyecto (ajústala según tu carpeta en htdocs)
define("BASE_URL", "http://localhost/proyecto_php/public/");

// Nombre del sistema
define("APP_NAME", "Sistema de Biblioteca");

// Configuración de idioma y zona horaria
date_default_timezone_set("America/El_Salvador");
setlocale(LC_TIME, "es_ES");

// Opciones de seguridad
define("HASH_ALGO", PASSWORD_BCRYPT); // Algoritmo para password_hash
define("SESSION_NAME", "biblioteca_sesion"); // Nombre de la sesión

// Conexión a la base de datos (opcional si quieres centralizar)
define("DB_HOST", "localhost");
define("DB_NAME", "biblioteca");
define("DB_USER", "root");
define("DB_PASS", "");
?>
