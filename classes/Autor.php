<?php
require_once 'Conexion.php';

class Autor {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($nombre, $apellido, $anio, $genero) {
        $stmt = $this->pdo->prepare("INSERT INTO Autores (nombre, apellido, anio_nacimiento, genero) VALUES (?, ?, ?, ?)");
        return $stmt->execute([$nombre, $apellido, $anio, $genero]);
    }

    public function listar() {
        return $this->pdo->query("SELECT * FROM Autores ORDER BY id_autor DESC")->fetchAll();
    }

    public function editar($id, $nombre, $apellido, $anio, $genero) {
        $stmt = $this->pdo->prepare("UPDATE Autores SET nombre=?, apellido=?, anio_nacimiento=?, genero=? WHERE id_autor=?");
        return $stmt->execute([$nombre, $apellido, $anio, $genero, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Autores WHERE id_autor=?");
        return $stmt->execute([$id]);
    }
}
