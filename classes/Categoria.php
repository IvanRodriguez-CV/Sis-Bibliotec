<?php
require_once 'Conexion.php';

class Categoria {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($nombre, $generos) {
        $stmt = $this->pdo->prepare("INSERT INTO Categoria (nombre, generos) VALUES (?, ?)");
        return $stmt->execute([$nombre, $generos]);
    }

    public function listar() {
        return $this->pdo->query("SELECT * FROM Categoria ORDER BY id_categoria DESC")->fetchAll();
    }

    public function editar($id, $nombre, $generos) {
        $stmt = $this->pdo->prepare("UPDATE Categoria SET nombre=?, generos=? WHERE id_categoria=?");
        return $stmt->execute([$nombre, $generos, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Categoria WHERE id_categoria=?");
        return $stmt->execute([$id]);
    }
}
