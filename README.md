# academia-idiomas

Sistema web monolítico ligero para la administración integral de una academia de idiomas, desarrollado en **PHP** puro y **MySQL / MariaDB**.

## Descripción breve
Sistema web en PHP y MySQL para la gestión de academias de idiomas. Controla roles (admin, recepción, docente, estudiante), inscripciones, pagos, asistencia y notas.

## Short Description
PHP and MySQL web app for language school management. Features role-based access, student tracking, course scheduling, tuition payments, attendance, and grade reporting.

---

## Características principales
- **Control de accesos por roles:** Administrador, Recepción, Docente y Estudiante.
- **Gestión académica:** Control de estudiantes, docentes, ofertas de cursos, asignación de aulas y control anti-choque de horarios.
- **Facturación y cobros:** Control de matrículas, mensualidades, seguimiento de cuotas vencidas y generación de recibos imprimibles.
- **Módulo docente:** Registro de asistencia por sesión y carga de calificaciones.
- **Consultas del estudiante:** Consulta de estado de cuenta y boletín de calificaciones.

---

## Requisitos previos
- **PHP** 7.4 o superior (con extensión `pdo_mysql` habilitada).
- **MySQL** 5.7+ o **MariaDB** 10.3+.
- Servidor web (Apache, Nginx o el servidor embebido de PHP).

---

## Instrucciones de uso e instalación

Sigue estos pasos para poner en marcha el sistema:

## LINUX

### 1. Clonar o descargar el repositorio
Descarga este repositorio en tu computadora o clónalo utilizando Git:
```bash
git clone https://github.com/Packman09ht/academia-idiomas.git
```

### 2. Configurar la Base de Datos
Importa el archivo **`academia.sql`** incluido en el repositorio a tu servidor MySQL mediante tu gestor preferido (como phpMyAdmin) o la terminal:
```bash
sudo mysql -u root -p < academia.sql
```
*Esto creará automáticamente la base de datos `academia` y el usuario necesario.*

### 3. Verificar la conexión
Abre el archivo `index.php` y verifica al inicio que las credenciales de conexión coincidan con las de tu servidor MySQL local:
```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'academia';
const DB_USER = 'academia';
const DB_PASS = 'academia123';
```

### 4. Ejecutar el proyecto
Inicia el servidor local de PHP desde la carpeta del proyecto:
```bash
php -S 127.0.0.1:8000
```
Luego, abre tu navegador e ingresa a: `http://127.0.0.1:8000`

## WINDOWS

#### 1. Descarga el repositorio
Abre el Símbolo del sistema (CMD) o PowerShell, y navega hasta la 
carpeta donde descargaste o guardaste tu proyecto usando el comando cd. Por ejemplo:
```bash
cd C:\ruta\de\tu\carpeta\academia-idiomas
```
Verification: La ruta que se muestra al inicio de la línea en tu consola 
debe coincidir exactamente con la ubicación de la carpeta de tu proyecto.

### 2. Encender el servidor local de PHP:
Escribe el siguiente comando en la consola y presiona Enter:
```bash
php -S localhost:8000
```
Verification: La consola debe mostrar un mensaje indicando que el servidor 
de desarrollo ha iniciado (por ejemplo: Development Server (http://localhost:8000) started).

### 3. Abrir el sistema en el navegador:
Abre tu navegador web favorito (Chrome, Edge, etc.), escribe la siguiente 
dirección en la barra de búsqueda y presiona Enter:http://localhost:8000
Verification: Deberá cargar inmediatamente la página principal de inicio de sesión de 
tu sistema de la academia de idiomas.

---

## Usuarios de prueba
Puedes iniciar sesión con cualquiera de los siguientes perfiles (la contraseña para todos es `1234`):

| Rol | Usuario |
| :--- | :--- |
| **Administrador** | `admin` |
| **Recepción** | `recepcion` |
| **Docente** | `docente` |
| **Estudiante** | `estudiante` |

---

## Licencia
Este proyecto está bajo la licencia **MIT**. Consulta el archivo `LICENSE` para más detalles.
