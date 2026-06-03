<?php
require_once "Conexion.php";

class Autor {
    private $conn;

    public function __construct() {
        $db = new Conexion();
        $this->conn = $db->conn;
    }

    public function listar() {
        $sql = "SELECT * FROM Autor";
        return $this->conn->query($sql);
    }

    public function crear($nombre, $apellido, $anio_nacimiento, $genero) {
        $sql = "INSERT INTO Autor (nombre, apellido, anio_nacimiento, genero) VALUES (?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssis", $nombre, $apellido, $anio_nacimiento, $genero);
        return $stmt->execute();
    }

    public function eliminar($id) {
        $sql = "DELETE FROM Autor WHERE id_autor=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
