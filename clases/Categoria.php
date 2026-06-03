<?php
require_once "Conexion.php";

class Categoria {
    private $conn;

    public function __construct() {
        $db = new Conexion();
        $this->conn = $db->conn;
    }

    public function listar() {
        $sql = "SELECT * FROM Categoria";
        return $this->conn->query($sql);
    }

    public function crear($nombre, $generos) {
        $sql = "INSERT INTO Categoria (nombre, generos) VALUES (?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $nombre, $generos);
        return $stmt->execute();
    }

    public function eliminar($id) {
        $sql = "DELETE FROM Categoria WHERE id_categoria=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
