<?php
require_once 'Conexion.php';

class Categoria {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($nombre, $generos) {
        // $generos expected as array of id_genero
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("INSERT INTO Categoria (nombre) VALUES (?)");
            $stmt->execute([$nombre]);
            $id = $this->pdo->lastInsertId();
            if (!empty($generos) && is_array($generos)) {
                $link = $this->pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
                foreach ($generos as $id_genero) {
                    $link->execute([$id, $id_genero]);
                }
            }
            $this->pdo->commit();
            return $id;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function listar() {
        // retorna categorias con sus géneros como arreglo
        $cats = $this->pdo->query("SELECT * FROM Categoria ORDER BY id_categoria DESC")->fetchAll(PDO::FETCH_ASSOC);
        $stmt = $this->pdo->prepare("SELECT g.id_genero, g.nombre FROM Genero g
            JOIN Categoria_Genero cg ON g.id_genero = cg.id_genero
            WHERE cg.id_categoria = ?");
        foreach ($cats as &$c) {
            $stmt->execute([$c['id_categoria']]);
            $c['generos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        return $cats;
    }

    public function editar($id, $nombre, $generos) {
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare("UPDATE Categoria SET nombre=? WHERE id_categoria=?");
            $stmt->execute([$nombre, $id]);
            // actualizar relaciones: eliminar existentes y volver a insertar
            $del = $this->pdo->prepare("DELETE FROM Categoria_Genero WHERE id_categoria = ?");
            $del->execute([$id]);
            if (!empty($generos) && is_array($generos)) {
                $link = $this->pdo->prepare("INSERT INTO Categoria_Genero (id_categoria, id_genero) VALUES (?, ?)");
                foreach ($generos as $id_genero) {
                    $link->execute([$id, $id_genero]);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Categoria WHERE id_categoria=?");
        return $stmt->execute([$id]);
    }
}
