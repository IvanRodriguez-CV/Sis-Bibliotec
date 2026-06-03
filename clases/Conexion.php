<?php
class Conexion {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $database = "biblioteca";
    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->database);
        if ($this->conn->connect_error) {
            die("Error de conexión: " . $this->conn->connect_error);
        }
        $this->conn->set_charset("utf8");
    }
}
?>
