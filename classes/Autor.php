<?php
require_once 'Conexion.php';

class Autor {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($codigoAutor, $nombre, $apellido, $anioNacimiento, $generoFrecuente) {
        $stmt = $this->pdo->prepare("INSERT INTO Autores (codigo_autor, nombre, apellido, anio_nacimiento, genero_frecuente) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$codigoAutor, $nombre, $apellido, $anioNacimiento, $generoFrecuente]);
    }

    public function listar() {
        return $this->pdo->query("SELECT * FROM Autores ORDER BY id_autor DESC")->fetchAll();
    }

    public function editar($id, $codigoAutor, $nombre, $apellido, $anioNacimiento, $generoFrecuente) {
        $stmt = $this->pdo->prepare("UPDATE Autores SET codigo_autor=?, nombre=?, apellido=?, anio_nacimiento=?, genero_frecuente=? WHERE id_autor=?");
        return $stmt->execute([$codigoAutor, $nombre, $apellido, $anioNacimiento, $generoFrecuente, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Autores WHERE id_autor=?");
        return $stmt->execute([$id]);
    }
}
