<?php
require_once "Conexion.php";

class Usuario {
    private $conn;

    public function __construct() {
        $db = new Conexion();
        $this->conn = $db->conn;
    }

    public function login($correo, $contrasenia) {
        $sql = "SELECT * FROM Usuario WHERE correo=? AND contrasenia=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ss", $correo, $contrasenia);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    public function listar() {
        $sql = "SELECT * FROM Usuario";
        return $this->conn->query($sql);
    }

    public function crear($carnet, $nombre, $id_carrera, $telefono, $correo, $contrasenia, $tipo) {
        $sql = "INSERT INTO Usuario (carnet_codigo, nombre_completo, id_carrera, telefono, correo, contrasenia, tipo_usuario) 
                VALUES (?,?,?,?,?,?,?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("ssissss", $carnet, $nombre, $id_carrera, $telefono, $correo, $contrasenia, $tipo);
        return $stmt->execute();
    }

    public function eliminar($id) {
        $sql = "DELETE FROM Usuario WHERE id_usuario=?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
?>
