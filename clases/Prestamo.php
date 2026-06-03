<?php
require_once "Conexion.php";

class Prestamo {
    private $conn;

    public function __construct() {
        $db = new Conexion();
        $this->conn = $db->conn;
    }

    public function listar() {
        $sql = "SELECT p.*, l.titulo, u.nombre_completo 
                FROM Prestamo p 
                JOIN Libro l ON p.id_libro=l.id_libro 
                JOIN Usuario u ON p.id_usuario=u.id_usuario";
        return $this->conn->query($sql);
    }

    public function crear($id_libro, $id_usuario, $fecha_prestamo, $fecha_devolucion, $estado) {
        $sql = "INSERT INTO Prestamo (id_libro, id_usuario, fecha_prestamo, fecha_devolucion, estado_prestamo) 
                VALUES (?,?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("iisss", $id_libro, $id_usuario, $fecha_prestamo, $fecha_devolucion, $estado);
        return $stmt->execute();
    }

    public function marcarDevuelto($id) {
        $sql = "UPDATE Prestamo SET estado_prestamo='Devuelto' WHERE id_prestamo=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
