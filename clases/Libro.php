<?php
require_once "Conexion.php";

class Libro {
    private $conn;

    public function __construct() {
        $db = new Conexion();
        $this->conn = $db->conn;
    }

    public function listar() {
        $sql = "SELECT l.*, c.nombre AS categoria, a.nombre AS autor, a.apellido AS apellido_autor
                FROM Libro l 
                JOIN Categoria c ON l.id_categoria = c.id_categoria 
                JOIN Autores a ON l.id_autor = a.id_autor"; // ← usa Autores en plural
        try {
            return $this->conn->query($sql);
        } catch (mysqli_sql_exception $e) {
            echo "Error en la consulta: " . $e->getMessage();
            return false;
        }
    }

    public function crear($codigo, $titulo, $id_categoria, $id_autor, $editorial, $anio, $existencias) {
        $sql = "INSERT INTO Libro (codigo, titulo, id_categoria, id_autor, editorial, anio_publicacion, existencias_totales) 
                VALUES (?,?,?,?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssiissi", $codigo, $titulo, $id_categoria, $id_autor, $editorial, $anio, $existencias);
        return $stmt->execute();
    }

    public function eliminar($id) {
        $sql = "DELETE FROM Libro WHERE id_libro=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
