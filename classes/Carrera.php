<?php
require_once 'Conexion.php';

class Carrera {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($nombre_carrera) {
        $stmt = $this->pdo->prepare("INSERT INTO Carrera (nombre_carrera) VALUES (?)");
        return $stmt->execute([$nombre_carrera]);
    }

    public function listar() {
        return $this->pdo->query("SELECT * FROM Carrera ORDER BY id_carrera DESC")->fetchAll();
    }

    public function editar($id, $nombre_carrera) {
        $stmt = $this->pdo->prepare("UPDATE Carrera SET nombre_carrera=? WHERE id_carrera=?");
        return $stmt->execute([$nombre_carrera, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Carrera WHERE id_carrera=?");
        return $stmt->execute([$id]);
    }
}
