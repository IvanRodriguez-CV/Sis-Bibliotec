<?php
require_once 'Conexion.php';

class Libro {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($id_categoria, $codigo, $titulo, $editorial, $descripcion, $anio, $existencias, $imagen = null) {
        $stmt = $this->pdo->prepare("INSERT INTO Libro (id_categoria, codigo, titulo, editorial, descripcion, anio_publicacion, existencias_totales, imagen, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Disponible')");
        return $stmt->execute([$id_categoria, $codigo, $titulo, $editorial, $descripcion, $anio, $existencias, $imagen]);
    }

    public function agregarAutor($id_libro, $id_autor) {
        $stmt = $this->pdo->prepare("INSERT INTO Libro_Autor (id_libro, id_autor) VALUES (?, ?)");
        return $stmt->execute([$id_libro, $id_autor]);
    }

    public function agregarGenero($id_libro, $id_genero) {
        $stmt = $this->pdo->prepare("INSERT INTO Libro_Genero (id_libro, id_genero) VALUES (?, ?)");
        return $stmt->execute([$id_libro, $id_genero]);
    }

    public function listar() {
        return $this->pdo->query("SELECT l.*, c.nombre as cat_nombre
                                  FROM Libro l 
                                  JOIN Categoria c ON l.id_categoria = c.id_categoria 
                                  ORDER BY l.id_libro DESC")->fetchAll();
    }

    public function obtenerPorId($id) {
        $stmt = $this->pdo->prepare("SELECT l.*, c.nombre as cat_nombre FROM Libro l 
                                     JOIN Categoria c ON l.id_categoria = c.id_categoria 
                                     WHERE l.id_libro = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function obtenerAutores($id_libro) {
        $stmt = $this->pdo->prepare("SELECT a.* FROM Autores a 
                                     JOIN Libro_Autor la ON a.id_autor = la.id_autor 
                                     WHERE la.id_libro = ?");
        $stmt->execute([$id_libro]);
        return $stmt->fetchAll();
    }

    public function obtenerGeneros($id_libro) {
        $stmt = $this->pdo->prepare("SELECT g.* FROM Genero g 
                                     JOIN Libro_Genero lg ON g.id_genero = lg.id_genero 
                                     WHERE lg.id_libro = ?");
        $stmt->execute([$id_libro]);
        return $stmt->fetchAll();
    }

    public function editar($id, $id_categoria, $codigo, $titulo, $editorial, $descripcion, $anio, $existencias, $imagen = null) {
        if ($imagen !== null) {
            $stmt = $this->pdo->prepare("UPDATE Libro SET id_categoria=?, codigo=?, titulo=?, editorial=?, descripcion=?, anio_publicacion=?, existencias_totales=?, imagen=? WHERE id_libro=?");
            return $stmt->execute([$id_categoria, $codigo, $titulo, $editorial, $descripcion, $anio, $existencias, $imagen, $id]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE Libro SET id_categoria=?, codigo=?, titulo=?, editorial=?, descripcion=?, anio_publicacion=?, existencias_totales=? WHERE id_libro=?");
            return $stmt->execute([$id_categoria, $codigo, $titulo, $editorial, $descripcion, $anio, $existencias, $id]);
        }
    }

    public function cambiarEstado($id, $estado) {
        $stmt = $this->pdo->prepare("UPDATE Libro SET estado=? WHERE id_libro=?");
        return $stmt->execute([$estado, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro WHERE id_libro=?");
        return $stmt->execute([$id]);
    }

    public function eliminarAutor($id_libro, $id_autor) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro=? AND id_autor=?");
        return $stmt->execute([$id_libro, $id_autor]);
    }

    public function eliminarGenero($id_libro, $id_genero) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro_Genero WHERE id_libro=? AND id_genero=?");
        return $stmt->execute([$id_libro, $id_genero]);
    }

    public function eliminarTodosAutores($id_libro) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro_Autor WHERE id_libro=?");
        return $stmt->execute([$id_libro]);
    }

    public function eliminarTodosGeneros($id_libro) {
        $stmt = $this->pdo->prepare("DELETE FROM Libro_Genero WHERE id_libro=?");
        return $stmt->execute([$id_libro]);
    }
}
