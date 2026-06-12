<?php
require_once 'Conexion.php';

class Usuario {
    private $pdo;

    public function __construct() {
        $conexion = new Conexion();
        $this->pdo = $conexion->pdo;
    }

    public function crear($id_carrera, $carnet, $nombre, $telefono, $correo, $contrasenia, $tipo) {
        $hash = password_hash($contrasenia, PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("INSERT INTO Usuario (id_carrera, carnet_codigo, nombre_completo, telefono, correo, contrasenia, tipo_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$id_carrera, $carnet, $nombre, $telefono, $correo, $hash, $tipo]);
    }

    public function listar() {
        return $this->pdo->query("SELECT u.*, c.nombre_carrera 
                                  FROM Usuario u 
                                  LEFT JOIN Carrera c ON u.id_carrera = c.id_carrera 
                                  ORDER BY u.id_usuario DESC")->fetchAll();
    }

    public function editar($id, $id_carrera, $nombre, $telefono, $correo, $tipo) {
        $stmt = $this->pdo->prepare("UPDATE Usuario SET id_carrera=?, nombre_completo=?, telefono=?, correo=?, tipo_usuario=? WHERE id_usuario=?");
        return $stmt->execute([$id_carrera, $nombre, $telefono, $correo, $tipo, $id]);
    }

    public function eliminar($id) {
        $stmt = $this->pdo->prepare("DELETE FROM Usuario WHERE id_usuario=?");
        return $stmt->execute([$id]);
    }

    public function login($correo, $contrasenia) {
        $stmt = $this->pdo->prepare("SELECT * FROM Usuario WHERE correo=?");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();
        if ($usuario && password_verify($contrasenia, $usuario['contrasenia'])) {
            return $usuario;
        }
        return false;
    }
}
