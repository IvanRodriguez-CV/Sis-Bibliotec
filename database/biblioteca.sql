CREATE DATABASE biblioteca;
USE biblioteca;

-- Tabla de Categorías
CREATE TABLE Categoria (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
) ENGINE=INNODB;

-- Tabla de Géneros
CREATE TABLE Genero (
  id_genero INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL
) ENGINE=INNODB;

-- Tabla intermedia Categoria_Genero (muchos-a-muchos)
CREATE TABLE Categoria_Genero (
  id_categoria INT NOT NULL,
  id_genero INT NOT NULL,
  PRIMARY KEY (id_categoria, id_genero),
  FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_genero) REFERENCES Genero(id_genero)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=INNODB;

-- Tabla de Carreras
CREATE TABLE Carrera (
  id_carrera INT AUTO_INCREMENT PRIMARY KEY,
  nombre_carrera VARCHAR(150) NOT NULL
) ENGINE=INNODB;

-- Tabla de Autores
CREATE TABLE Autores (
  id_autor INT AUTO_INCREMENT PRIMARY KEY,
  codigo_autor INT,
  nombre VARCHAR(255) NOT NULL,
  apellido VARCHAR(255) NOT NULL,
  anio_nacimiento YEAR,
  genero_frecuente VARCHAR(100)
) ENGINE=INNODB;

-- Tabla de Usuarios
CREATE TABLE Usuario (
  id_usuario INT AUTO_INCREMENT PRIMARY KEY,
  carnet_codigo VARCHAR(50) UNIQUE NOT NULL,
  nombre_completo VARCHAR(200) NOT NULL,
  id_carrera INT NULL,
  telefono VARCHAR(20),
  correo VARCHAR(150) UNIQUE NOT NULL,
  contrasenia VARCHAR(255) NOT NULL,
  tipo_usuario ENUM('Estudiante','Docente','admin') NOT NULL,
  FOREIGN KEY (id_carrera) REFERENCES Carrera(id_carrera)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=INNODB;

-- Tabla de Libros
CREATE TABLE Libro (
  id_libro INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE NOT NULL,
  titulo VARCHAR(250) NOT NULL,
  editorial VARCHAR(250),
  descripcion TEXT NULL,
  anio_publicacion YEAR,
  existencias_totales INT NOT NULL DEFAULT 0,
  estado ENUM('Disponible','Prestado','Perdido') NOT NULL DEFAULT 'Disponible',
  imagen VARCHAR(255) NULL,
  id_categoria INT NOT NULL,
  FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=INNODB;

-- Relación muchos-a-muchos entre Libros y Autores
CREATE TABLE Libro_Autor (
  id_libro INT NOT NULL,
  id_autor INT NOT NULL,
  PRIMARY KEY (id_libro, id_autor),
  FOREIGN KEY (id_libro) REFERENCES Libro(id_libro)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_autor) REFERENCES Autores(id_autor)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=INNODB;

-- Tabla de relación Libros-Géneros (muchos-a-muchos)
CREATE TABLE Libro_Genero (
  id_libro INT NOT NULL,
  id_genero INT NOT NULL,
  PRIMARY KEY (id_libro, id_genero),
  FOREIGN KEY (id_libro) REFERENCES Libro(id_libro)
    ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (id_genero) REFERENCES Genero(id_genero)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=INNODB;

-- Tabla de Préstamos
CREATE TABLE Prestamo (
  id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
  id_libro INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_prestamo DATE NOT NULL,
  fecha_entrega DATE NOT NULL,
  fecha_devolucion DATE NULL,
  estado_prestamo ENUM('Activo','Devuelto','Vencido','Perdido') NOT NULL DEFAULT 'Activo',
  renovaciones INT NOT NULL DEFAULT 0,
  fecha_renovacion DATE NULL,
  FOREIGN KEY (id_libro) REFERENCES Libro(id_libro)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_renovaciones CHECK (renovaciones <= 2)
) ENGINE=INNODB;

-- Tabla de Reservas
CREATE TABLE Reserva (
  id_reserva INT AUTO_INCREMENT PRIMARY KEY,
  id_libro INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_reserva TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('Pendiente','Disponible','Reclamada','Cancelada','Expirada') NOT NULL DEFAULT 'Pendiente',
  posicion_cola INT NOT NULL,
  fecha_disponibilidad TIMESTAMP NULL,
  fecha_expiracion TIMESTAMP NULL,
  FOREIGN KEY (id_libro) REFERENCES Libro(id_libro) ON DELETE CASCADE,
  FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario) ON DELETE CASCADE,
  INDEX idx_usuario (id_usuario),
  INDEX idx_libro (id_libro),
  INDEX idx_estado (estado)
) ENGINE=INNODB DEFAULT CHARSET=utf8mb4;

-- Trigger para descontar existencias al prestar
DELIMITER $$
CREATE TRIGGER descontar_existencias
AFTER INSERT ON Prestamo
FOR EACH ROW
BEGIN
  UPDATE Libro
  SET existencias_totales = existencias_totales - 1
  WHERE id_libro = NEW.id_libro
    AND existencias_totales > 0;
END$$

-- Trigger para aumentar existencias al devolver
CREATE TRIGGER aumentar_existencias
AFTER UPDATE ON Prestamo
FOR EACH ROW
BEGIN
  IF NEW.estado_prestamo = 'Devuelto' AND OLD.estado_prestamo <> 'Devuelto' THEN
    UPDATE Libro
    SET existencias_totales = existencias_totales + 1
    WHERE id_libro = NEW.id_libro;
  END IF;
END$$
DELIMITER ;
