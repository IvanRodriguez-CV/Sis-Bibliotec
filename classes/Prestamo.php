<?php
require_once 'Conexion.php';

class Prestamo {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    // El admin crea el préstamo para un usuario
    public function crear($id_admin, $id_usuario, $id_libro, $fecha_prestamo = null, $fecha_entrega) {
        if ($fecha_prestamo === null) {
            $fecha_prestamo = date('Y-m-d');
        }

        $stmt = $this->pdo->prepare("INSERT INTO Prestamo (id_admin, id_usuario, id_libro, fecha_prestamo, fecha_entrega, estado_prestamo) VALUES (?, ?, ?, ?, ?, 'Activo')");
        return $stmt->execute([$id_admin, $id_usuario, $id_libro, $fecha_prestamo, $fecha_entrega]);
    }

    public function listar() {
        return $this->pdo->query("SELECT p.*, l.titulo AS libro_titulo, u.nombre_completo AS usuario_nombre, u.carnet_codigo,
                                  a.nombre_completo AS admin_nombre
                                  FROM Prestamo p
                                  JOIN Libro l ON p.id_libro = l.id_libro
                                  JOIN Usuario u ON p.id_usuario = u.id_usuario
                                  LEFT JOIN Usuario a ON p.id_admin = a.id_usuario
                                  ORDER BY p.id_prestamo DESC")->fetchAll();
    }

    public function actualizarEstado($id_prestamo, $estado, $fecha_devolucion = null) {
        if ($estado === 'Devuelto') {
            $stmt = $this->pdo->prepare("UPDATE Prestamo SET estado_prestamo = ?, fecha_devolucion = ? WHERE id_prestamo = ?");
            return $stmt->execute([$estado, $fecha_devolucion, $id_prestamo]);
        }

        $stmt = $this->pdo->prepare("UPDATE Prestamo SET estado_prestamo = ? WHERE id_prestamo = ?");
        return $stmt->execute([$estado, $id_prestamo]);
    }

    public function renovar($id_prestamo, $fecha_renovacion) {
        $stmt = $this->pdo->prepare("UPDATE Prestamo SET renovaciones = renovaciones + 1, fecha_renovacion = ? WHERE id_prestamo = ? AND renovaciones < 2");
        return $stmt->execute([$fecha_renovacion, $id_prestamo]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Prestamo WHERE id_prestamo = ?");
        return $stmt->execute([$id]);
    }
}