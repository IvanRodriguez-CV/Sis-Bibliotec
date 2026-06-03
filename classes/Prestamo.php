<?php
require_once 'Conexion.php';

class Prestamo {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($id_usuario, $id_libro, $fecha_entrega, $fecha_limite) {
        $stmt = $this->pdo->prepare("INSERT INTO Prestamo (id_usuario, id_libro, fecha_entrega, fecha_limite, estado_prestamo) VALUES (?, ?, ?, ?, 'Activo')");
        return $stmt->execute([$id_usuario, $id_libro, $fecha_entrega, $fecha_limite]);
    }

    public function listar() {
        return $this->pdo->query("SELECT p.*, l.titulo as libro_titulo, u.nombre_completo as usuario_nombre, u.carnet_codigo 
                                  FROM Prestamo p 
                                  JOIN Libro l ON p.id_libro = l.id_libro 
                                  JOIN Usuario u ON p.id_usuario = u.id_usuario 
                                  ORDER BY p.id_prestamo DESC")->fetchAll();
    }

    public function actualizarEstado($id_prestamo, $estado, $fecha_devolucion=null) {
        if ($estado === 'Devuelto') {
            $stmt = $this->pdo->prepare("UPDATE Prestamo SET estado_prestamo=?, fecha_devolucion=? WHERE id_prestamo=?");
            return $stmt->execute([$estado, $fecha_devolucion, $id_prestamo]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE Prestamo SET estado_prestamo=? WHERE id_prestamo=?");
            return $stmt->execute([$estado, $id_prestamo]);
        }
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Prestamo WHERE id_prestamo=?");
        return $stmt->execute([$id]);
    }
}
