-- ============================================================
--  Academia de Idiomas · Base de datos (MySQL / MariaDB)
--  Importar:  sudo mysql < academia.sql      (o desde phpMyAdmin)
--  Usuarios de prueba (clave 1234): admin, recepcion, docente, estudiante
-- ============================================================
SET NAMES utf8mb4;
DROP DATABASE IF EXISTS academia;
CREATE DATABASE academia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE academia;

CREATE TABLE rol(id_rol INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE usuario(id_usuario INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(40) NOT NULL UNIQUE, contrasena_hash VARCHAR(255) NOT NULL, id_rol INT NOT NULL, activo TINYINT(1) NOT NULL DEFAULT 1, FOREIGN KEY(id_rol) REFERENCES rol(id_rol)) ENGINE=InnoDB;
CREATE TABLE persona(id_persona INT AUTO_INCREMENT PRIMARY KEY, cedula VARCHAR(11) UNIQUE, nombres VARCHAR(60) NOT NULL, apellidos VARCHAR(60) NOT NULL, fecha_nacimiento DATE NOT NULL, telefono VARCHAR(15), correo VARCHAR(80) UNIQUE, direccion VARCHAR(150), id_usuario INT UNIQUE, FOREIGN KEY(id_usuario) REFERENCES usuario(id_usuario)) ENGINE=InnoDB;
CREATE TABLE estudiante(id_estudiante INT AUTO_INCREMENT PRIMARY KEY, id_persona INT NOT NULL UNIQUE, fecha_registro DATE NOT NULL, estado ENUM('activo','inactivo','retirado') NOT NULL DEFAULT 'activo', FOREIGN KEY(id_persona) REFERENCES persona(id_persona)) ENGINE=InnoDB;
CREATE TABLE docente(id_docente INT AUTO_INCREMENT PRIMARY KEY, id_persona INT NOT NULL UNIQUE, especialidad VARCHAR(60), fecha_contratacion DATE NOT NULL, FOREIGN KEY(id_persona) REFERENCES persona(id_persona)) ENGINE=InnoDB;
CREATE TABLE idioma(id_idioma INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(40) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE nivel(id_nivel INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(5) NOT NULL UNIQUE, descripcion VARCHAR(60)) ENGINE=InnoDB;
CREATE TABLE curso(id_curso INT AUTO_INCREMENT PRIMARY KEY, id_idioma INT NOT NULL, id_nivel INT NOT NULL, nombre VARCHAR(80) NOT NULL, duracion_horas INT NOT NULL CHECK(duracion_horas>0), precio DECIMAL(10,2) NOT NULL CHECK(precio>=0), FOREIGN KEY(id_idioma) REFERENCES idioma(id_idioma), FOREIGN KEY(id_nivel) REFERENCES nivel(id_nivel)) ENGINE=InnoDB;
CREATE TABLE aula(id_aula INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE, capacidad INT NOT NULL CHECK(capacidad>0)) ENGINE=InnoDB;
CREATE TABLE grupo(id_grupo INT AUTO_INCREMENT PRIMARY KEY, id_curso INT NOT NULL, id_docente INT NOT NULL, id_aula INT NOT NULL, cupo_maximo INT NOT NULL CHECK(cupo_maximo>0), fecha_inicio DATE NOT NULL, fecha_fin DATE NOT NULL, estado ENUM('abierto','en curso','cerrado') NOT NULL DEFAULT 'abierto', CHECK(fecha_fin>fecha_inicio), FOREIGN KEY(id_curso) REFERENCES curso(id_curso), FOREIGN KEY(id_docente) REFERENCES docente(id_docente), FOREIGN KEY(id_aula) REFERENCES aula(id_aula)) ENGINE=InnoDB;
CREATE TABLE horario(id_horario INT AUTO_INCREMENT PRIMARY KEY, id_grupo INT NOT NULL, dia_semana ENUM('Lunes','Martes','Miércoles','Jueves','Viernes','Sábado') NOT NULL, hora_inicio TIME NOT NULL, hora_fin TIME NOT NULL, CHECK(hora_fin>hora_inicio), FOREIGN KEY(id_grupo) REFERENCES grupo(id_grupo)) ENGINE=InnoDB;
CREATE TABLE inscripcion(id_inscripcion INT AUTO_INCREMENT PRIMARY KEY, id_estudiante INT NOT NULL, id_grupo INT NOT NULL, fecha_inscripcion DATE NOT NULL, estado ENUM('activa','retirada','finalizada') NOT NULL DEFAULT 'activa', UNIQUE(id_estudiante,id_grupo), FOREIGN KEY(id_estudiante) REFERENCES estudiante(id_estudiante), FOREIGN KEY(id_grupo) REFERENCES grupo(id_grupo)) ENGINE=InnoDB;
CREATE TABLE sesion(id_sesion INT AUTO_INCREMENT PRIMARY KEY, id_grupo INT NOT NULL, fecha DATE NOT NULL, tema VARCHAR(100), FOREIGN KEY(id_grupo) REFERENCES grupo(id_grupo)) ENGINE=InnoDB;
CREATE TABLE asistencia(id_asistencia INT AUTO_INCREMENT PRIMARY KEY, id_inscripcion INT NOT NULL, id_sesion INT NOT NULL, presente TINYINT(1) NOT NULL, UNIQUE(id_inscripcion,id_sesion), FOREIGN KEY(id_inscripcion) REFERENCES inscripcion(id_inscripcion), FOREIGN KEY(id_sesion) REFERENCES sesion(id_sesion)) ENGINE=InnoDB;
CREATE TABLE evaluacion(id_evaluacion INT AUTO_INCREMENT PRIMARY KEY, id_grupo INT NOT NULL, nombre VARCHAR(50) NOT NULL, ponderacion DECIMAL(5,2) NOT NULL CHECK(ponderacion BETWEEN 0 AND 100), FOREIGN KEY(id_grupo) REFERENCES grupo(id_grupo)) ENGINE=InnoDB;
CREATE TABLE calificacion(id_calificacion INT AUTO_INCREMENT PRIMARY KEY, id_inscripcion INT NOT NULL, id_evaluacion INT NOT NULL, nota DECIMAL(5,2) NOT NULL CHECK(nota BETWEEN 0 AND 100), UNIQUE(id_inscripcion,id_evaluacion), FOREIGN KEY(id_inscripcion) REFERENCES inscripcion(id_inscripcion), FOREIGN KEY(id_evaluacion) REFERENCES evaluacion(id_evaluacion)) ENGINE=InnoDB;
CREATE TABLE metodo_pago(id_metodo INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(30) NOT NULL UNIQUE) ENGINE=InnoDB;
CREATE TABLE pago(id_pago INT AUTO_INCREMENT PRIMARY KEY, id_inscripcion INT NOT NULL, id_metodo INT NOT NULL, fecha_pago DATE NOT NULL, monto DECIMAL(10,2) NOT NULL CHECK(monto>0), concepto VARCHAR(50) NOT NULL, num_recibo VARCHAR(20) NOT NULL UNIQUE, FOREIGN KEY(id_inscripcion) REFERENCES inscripcion(id_inscripcion), FOREIGN KEY(id_metodo) REFERENCES metodo_pago(id_metodo)) ENGINE=InnoDB;

-- ---------- Datos de prueba ----------
INSERT INTO rol(nombre) VALUES('Administrador'),('Recepción'),('Docente'),('Estudiante');
INSERT INTO usuario(username,contrasena_hash,id_rol) VALUES
('admin','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',1),('recepcion','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',2),('docente','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',3),('estudiante','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',4),('juan','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',4),('ana','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',4),('marc','$2y$10$oT7V06r4tbfgP.gfmSWeK.e/k.9.xbO862LxBIdH2TOZL4QB4JPdu',3);
INSERT INTO persona(cedula,nombres,apellidos,fecha_nacimiento,telefono,correo,direccion,id_usuario) VALUES
('00100000001','Carla','Rodríguez','1985-03-11','809-555-0101','carla@academia.do','Calle 1, Barahona',1),
('00100000002','Pedro','Díaz','1990-06-21','809-555-0102','pedro@academia.do','Calle 2, Barahona',2),
('00100000003','Laura','Peña','1988-09-15','809-555-0103','laura@academia.do','Calle 3, Barahona',3),
('00100000004','María','Pérez','2001-01-30','809-555-0104','maria@academia.do','Calle 4, Neyba',4),
('00100000005','Juan','Rodríguez','2002-04-12','809-555-0105','juan@academia.do','Calle 5, Neyba',5),
('00100000006','Ana','Martínez','2000-11-02','809-555-0106','ana@academia.do','Calle 6, Barahona',6),
('00100000007','Marc','Dupont','1984-07-07','809-555-0107','marc@academia.do','Calle 7, Barahona',7);
INSERT INTO estudiante(id_persona,fecha_registro) VALUES(4,'2026-09-01'),(5,'2026-09-01'),(6,'2026-09-01');
INSERT INTO docente(id_persona,especialidad,fecha_contratacion) VALUES(3,'Inglés','2025-02-01'),(7,'Francés','2025-02-01');
INSERT INTO idioma(nombre) VALUES('Inglés'),('Francés');
INSERT INTO nivel(codigo,descripcion) VALUES('A2','Básico'),('B1','Intermedio');
INSERT INTO curso(id_idioma,id_nivel,nombre,duracion_horas,precio) VALUES(1,1,'Inglés A2',60,4000),(1,2,'Inglés B1',60,4500),(2,1,'Francés A2',60,4200);
INSERT INTO aula(nombre,capacidad) VALUES('Aula 1',15),('Aula 2',12);
INSERT INTO grupo(id_curso,id_docente,id_aula,cupo_maximo,fecha_inicio,fecha_fin,estado) VALUES(2,1,1,15,'2026-09-01','2026-12-18','en curso'),(3,2,2,12,'2026-09-01','2026-12-18','en curso'),(1,1,2,10,'2026-09-01','2026-12-18','en curso');
INSERT INTO horario(id_grupo,dia_semana,hora_inicio,hora_fin) VALUES(1,'Lunes','18:00','20:00'),(1,'Miércoles','18:00','20:00'),(2,'Martes','19:30','21:30'),(2,'Jueves','19:30','21:30'),(3,'Sábado','08:00','11:00');
INSERT INTO inscripcion(id_estudiante,id_grupo,fecha_inscripcion) VALUES(1,1,'2026-09-01'),(2,1,'2026-09-01'),(3,1,'2026-09-01'),(1,2,'2026-09-01');
INSERT INTO sesion(id_grupo,fecha,tema) VALUES(1,'2026-10-26','Presentaciones');
INSERT INTO asistencia(id_inscripcion,id_sesion,presente) VALUES(1,1,1),(2,1,1),(3,1,0);
INSERT INTO evaluacion(id_grupo,nombre,ponderacion) VALUES(1,'Parcial',30),(1,'Oral',30),(1,'Final',40),(2,'Parcial',30),(2,'Oral',30),(2,'Final',40),(3,'Parcial',30),(3,'Oral',30),(3,'Final',40);
INSERT INTO calificacion(id_inscripcion,id_evaluacion,nota) VALUES(1,1,90),(1,2,85),(1,3,92),(2,1,75),(2,2,80),(2,3,70),(3,1,60),(3,2,70),(3,3,65);
INSERT INTO metodo_pago(nombre) VALUES('Efectivo'),('Transferencia'),('Tarjeta');
INSERT INTO pago(id_inscripcion,id_metodo,fecha_pago,monto,concepto,num_recibo) VALUES(1,1,'2026-09-02',4500,'Matrícula','REC-0001'),(2,1,'2026-09-15',2000,'Matrícula','REC-0002'),(4,1,'2026-10-05',1000,'Matrícula','REC-0003');

-- ---------- Usuario de la aplicación ----------
DROP USER IF EXISTS 'academia'@'localhost';
CREATE USER 'academia'@'localhost' IDENTIFIED BY 'academia123';
GRANT ALL PRIVILEGES ON academia.* TO 'academia'@'localhost';
DROP USER IF EXISTS 'academia'@'127.0.0.1';
CREATE USER 'academia'@'127.0.0.1' IDENTIFIED BY 'academia123';
GRANT ALL PRIVILEGES ON academia.* TO 'academia'@'127.0.0.1';
FLUSH PRIVILEGES;
