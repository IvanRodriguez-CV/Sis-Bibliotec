<?php
require_once 'Conexion.php';

class Reserva {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($id_usuario, $id_libro) {
        $stmt = $this->pdo->prepare("INSERT INTO Reserva (id_usuario, id_libro, estado, posicion_cola) 
                                    VALUES (?, ?, 'Pendiente', (SELECT COUNT(*) + 1 FROM Reserva WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')))");
        return $stmt->execute([$id_usuario, $id_libro, $id_libro]);
    }

    public function listar() {
        return $this->pdo->query("SELECT r.*, l.titulo AS libro_titulo, u.nombre_completo AS usuario_nombre, u.carnet_codigo
                                  FROM Reserva r
                                  JOIN Libro l ON r.id_libro = l.id_libro
                                  JOIN Usuario u ON r.id_usuario = u.id_usuario
                                  ORDER BY r.posicion_cola ASC, r.fecha_reserva ASC")->fetchAll();
    }

    public function listarPorUsuario($id_usuario) {
        $stmt = $this->pdo->prepare("SELECT r.*, l.titulo AS libro_titulo, l.codigo
                                     FROM Reserva r
                                     JOIN Libro l ON r.id_libro = l.id_libro
                                     WHERE r.id_usuario = ?
                                     ORDER BY r.estado DESC, r.posicion_cola ASC");
        $stmt->execute([$id_usuario]);
        return $stmt->fetchAll();
    }

    public function listarPorLibro($id_libro) {
        $stmt = $this->pdo->prepare("SELECT r.*, u.nombre_completo, u.carnet_codigo
                                     FROM Reserva r
                                     JOIN Usuario u ON r.id_usuario = u.id_usuario
                                     WHERE r.id_libro = ? AND r.estado IN ('Pendiente', 'Disponible')
                                     ORDER BY r.posicion_cola ASC");
        $stmt->execute([$id_libro]);
        return $stmt->fetchAll();
    }

    public function actualizarEstado($id_reserva, $estado, $fecha_disponibilidad = null, $fecha_expiracion = null) {
        $stmt = $this->pdo->prepare("UPDATE Reserva SET estado = ?, fecha_disponibilidad = ?, fecha_expiracion = ? WHERE id_reserva = ?");
        return $stmt->execute([$estado, $fecha_disponibilidad, $fecha_expiracion, $id_reserva]);
    }

    public function obtenerPorId($id_reserva) {
        $stmt = $this->pdo->prepare("SELECT * FROM Reserva WHERE id_reserva = ?");
        $stmt->execute([$id_reserva]);
        return $stmt->fetch();
    }

    public function cancelar($id_reserva) {
        return $this->actualizarEstado($id_reserva, 'Cancelada');
    }

    public function reposicionarCola($id_libro) {
        $stmt = $this->pdo->prepare("UPDATE Reserva 
                                     SET posicion_cola = (SELECT COUNT(*) FROM (
                                         SELECT * FROM Reserva r2 
                                         WHERE r2.id_libro = ? AND r2.estado IN ('Pendiente', 'Disponible') AND r2.fecha_reserva <= Reserva.fecha_reserva
                                     ) AS conteo)
                                     WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')");
        return $stmt->execute([$id_libro, $id_libro]);
    }

    public function eliminar($id_reserva) {
        $stmt = $this->pdo->prepare("DELETE FROM Reserva WHERE id_reserva = ?");
        return $stmt->execute([$id_reserva]);
    }

    public function obtenerProximaReserva($id_libro) {
        $stmt = $this->pdo->prepare("SELECT * FROM Reserva 
                                     WHERE id_libro = ? AND estado IN ('Pendiente', 'Disponible')
                                     ORDER BY posicion_cola ASC LIMIT 1");
        $stmt->execute([$id_libro]);
        return $stmt->fetch();
    }

    public function expirarReservasVencidas() {
        $stmt = $this->pdo->prepare("UPDATE Reserva 
                                     SET estado = 'Expirada'
                                     WHERE estado = 'Disponible' AND fecha_expiracion < NOW()");
        return $stmt->execute();
    }
}
