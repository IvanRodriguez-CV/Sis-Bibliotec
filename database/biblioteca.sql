CREATE DATABASE biblioteca;
USE biblioteca;

CREATE TABLE Categoria (
  id_categoria INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  generos VARCHAR(200)
) ENGINE=INNODB;

CREATE TABLE Carrera (
  id_carrera INT AUTO_INCREMENT PRIMARY KEY,
  nombre_carrera VARCHAR(150) NOT NULL
) ENGINE=INNODB;

CREATE TABLE Autores (
  id_autor INT AUTO_INCREMENT PRIMARY KEY,
  codigo_autor INT,
  nombre VARCHAR(255) NOT NULL,
  apellido VARCHAR(255) NOT NULL,
  anio_nacimiento YEAR,
  genero VARCHAR(50)
) ENGINE=INNODB;

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

CREATE TABLE Libro (
  id_libro INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(50) UNIQUE NOT NULL,
  titulo VARCHAR(250) NOT NULL,
  autor VARCHAR(250),
  editorial VARCHAR(250),
  anio_publicacion YEAR,
  existencias_totales INT NOT NULL DEFAULT 0,
  estado ENUM('Disponible','Prestado','Perdido') NOT NULL DEFAULT 'Disponible',
  imagen VARCHAR(255) NULL,
  id_categoria INT NOT NULL,
  id_autor INT NOT NULL,
  FOREIGN KEY (id_categoria) REFERENCES Categoria(id_categoria)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_autor) REFERENCES Autores(id_autor)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=INNODB;

CREATE TABLE Prestamo (
  id_prestamo INT AUTO_INCREMENT PRIMARY KEY,
  id_libro INT NOT NULL,
  id_usuario INT NOT NULL,
  fecha_prestamo DATE NOT NULL,
  fecha_entrega DATE NOT NULL,
  fecha_devolucion DATE,
  estado_prestamo ENUM('Activo','Devuelto','Vencido','Perdido') NOT NULL DEFAULT 'Activo',
  FOREIGN KEY (id_libro) REFERENCES Libro(id_libro)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=INNODB;
