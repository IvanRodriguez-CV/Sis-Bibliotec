<?php
require_once 'Conexion.php';

class Libro {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($id_categoria, $id_autor, $codigo, $titulo, $editorial, $anio, $existencias) {
        $stmt = $this->pdo->prepare("INSERT INTO Libro (id_categoria, id_autor, codigo, titulo, editorial, anio_publicacion, existencias_totales) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$id_categoria, $id_autor, $codigo, $titulo, $editorial, $anio, $existencias]);
    }

    public function listar() {
        return $this->pdo->query("SELECT l.*, c.nombre as cat_nombre, CONCAT(a.nombre,' ',a.apellido) as aut_nombre 
                                  FROM Libro l 
                                  JOIN Categoria c ON l.id_categoria = c.id_categoria 
                                  JOIN Autores a ON l.id_autor = a.id_autor 
                                  ORDER BY l.id_libro DESC")->fetchAll();
    }

    public function editar($id, $id_categoria, $id_autor, $codigo, $titulo, $editorial, $anio, $existencias) {
        $stmt = $this->pdo->prepare("UPDATE Libro SET id_categoria=?, id_autor=?, codigo=?, titulo=?, editorial=?, anio_publicacion=?, existencias_totales=? WHERE id_libro=?");
        return $stmt->execute([$id_categoria, $id_autor, $codigo, $titulo, $editorial, $anio, $existencias, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro WHERE id_libro=?");
        return $stmt->execute([$id]);
    }
}
