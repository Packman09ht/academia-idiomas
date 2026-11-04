<?php
session_start();

const DB_HOST = '127.0.0.1';
const DB_NAME = 'academia';
const DB_USER = 'academia';
const DB_PASS = 'academia123';

try {
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Exception $x) {
    die('Error de conexión a la base de datos.');
}

function q($s, $p = []) {
    global $db;
    $st = $db->prepare($s);
    $st->execute($p);
    return $st->fetchAll();
}

function one($s, $p = []) {
    $r = q($s, $p);
    return $r[0] ?? null;
}

function ex($s, $p = []) {
    global $db;
    $st = $db->prepare($s);
    $st->execute($p);
    return $db->lastInsertId();
}

function e($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($n) {
    return 'RD$ ' . number_format((float)$n, 2);
}

function flash($m, $ok = true) {
    $_SESSION['f'] = [$m, $ok];
}

function go($qs) {
    header('Location: ?' . $qs);
    exit;
}

function opts($rows, $v, $l, $sel = null) {
    $o = '';
    foreach ($rows as $r) {
        $val = e($r[$v]);
        $lbl = e(is_callable($l) ? $l($r) : $r[$l]);
        $selected = ($r[$v] == $sel) ? ' selected' : '';
        $o .= "<option value=\"{$val}\"{$selected}>{$lbl}</option>";
    }
    return $o;
}

function nom($r) {
    return $r['nombres'] . ' ' . $r['apellidos'];
}

function tag($t, $c = '') {
    return '<span class="tag ' . $c . '">' . e($t) . '</span>';
}

function csrf() {
    return '<input type="hidden" name="t" value="' . $_SESSION['t'] . '">';
}

function hidden($a, $back = null) {
    $b = e($back ?? ($_SERVER['QUERY_STRING'] ?: 'v=dash'));
    return csrf() . '<input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="back" value="' . $b . '">';
}

function must($c, $m) {
    if (!$c) throw new Exception($m);
}

if (empty($_SESSION['t'])) {
    $_SESSION['t'] = bin2hex(random_bytes(16));
}

$hoy = date('Y-m-d');

function inscs($w = '', $p = []) {
    return q("SELECT i.*, c.precio, c.nombre curso, g.id_docente, pe.nombres, pe.apellidos 
              FROM inscripcion i 
              JOIN grupo g ON g.id_grupo = i.id_grupo 
              JOIN curso c ON c.id_curso = g.id_curso 
              JOIN estudiante es ON es.id_estudiante = i.id_estudiante 
              JOIN persona pe ON pe.id_persona = es.id_persona 
              $w ORDER BY i.id_inscripcion", $p);
}

function pagado($id) {
    return (float)one("SELECT COALESCE(SUM(monto), 0) s FROM pago WHERE id_inscripcion = ?", [$id])['s'];
}

function saldo($i) {
    return $i['precio'] - pagado($i['id_inscripcion']);
}

function cuotas($i) {
    $t = $i['precio'] / 4;
    $p = pagado($i['id_inscripcion']);
    $o = [];
    for ($k = 0; $k < 4; $k++) {
        $v = date('Y-m-d', strtotime($i['fecha_inscripcion'] . " +" . (30 * $k) . " days"));
        $ab = min($t, $p);
        $p -= $ab;
        $est = ($ab >= $t - 0.005) ? 'Pagada' : (($v < date('Y-m-d')) ? 'Vencida' : 'Pendiente');
        $o[] = ['n' => $k + 1, 'v' => $v, 'm' => $t, 'ab' => $ab, 'e' => $est];
    }
    return $o;
}

function vencido($i) {
    $s = 0;
    foreach (cuotas($i) as $c) {
        if ($c['e'] == 'Vencida') $s += $c['m'] - $c['ab'];
    }
    return $s;
}

function hs($g) {
    return one("SELECT GROUP_CONCAT(CONCAT(LEFT(dia_semana, 3), ' ', TIME_FORMAT(hora_inicio, '%H:%i')) 
              ORDER BY FIELD(dia_semana, 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado') SEPARATOR ', ') h 
              FROM horario WHERE id_grupo = ?", [$g])['h'] ?? '';
}

function grupos($w = '', $p = []) {
    return q("SELECT g.*, c.nombre curso, a.nombre aula, CONCAT(pe.nombres, ' ', pe.apellidos) docente, 
              (SELECT COUNT(*) FROM inscripcion i WHERE i.id_grupo = g.id_grupo AND i.estado = 'activa') inscritos 
              FROM grupo g 
              JOIN curso c ON c.id_curso = g.id_curso 
              JOIN aula a ON a.id_aula = g.id_aula 
              JOIN docente d ON d.id_docente = g.id_docente 
              JOIN persona pe ON pe.id_persona = d.id_persona 
              $w ORDER BY g.id_grupo", $p);
}

function prom($iid) {
    $r = one("SELECT SUM(c.nota * e.ponderacion) / SUM(e.ponderacion) p 
              FROM calificacion c 
              JOIN evaluacion e ON e.id_evaluacion = c.id_evaluacion 
              WHERE c.id_inscripcion = ?", [$iid]);
    return $r['p'] === null ? null : (float)$r['p'];
}

function asis($iid) {
    $r = one("SELECT ROUND(100 * AVG(presente)) a FROM asistencia WHERE id_inscripcion = ?", [$iid]);
    return $r['a'] === null ? null : (int)$r['a'];
}

function mkUser($nombre, $rol) {
    $clean = strtr(explode(' ', trim($nombre))[0], ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ñ'=>'N','ü'=>'u','Ü'=>'U']);
    $un = strtolower(preg_replace('/[^a-z]/i', '', $clean)) ?: 'user';
    if (one("SELECT 1 FROM usuario WHERE username = ?", [$un])) {
        $un .= rand(10, 99);
    }
    return [
        ex("INSERT INTO usuario(username, contrasena_hash, id_rol) VALUES(?, ?, ?)", [$un, password_hash('1234', PASSWORD_DEFAULT), $rol]),
        $un
    ];
}

function mkPersona($rol) {
    $n = trim($_POST['nombres'] ?? '');
    $a = trim($_POST['apellidos'] ?? '');
    $c = preg_replace('/\D/', '', $_POST['cedula'] ?? '');
    $f = $_POST['nacimiento'] ?? '';
    $m = trim($_POST['correo'] ?? '');
    
    must($n && $a && $f, 'Nombres, apellidos y fecha de nacimiento son obligatorios.');
    must(strlen($c) == 11, 'La cédula debe tener 11 dígitos.');
    must($m === '' || filter_var($m, FILTER_VALIDATE_EMAIL), 'El correo no es válido.');
    
    $u = mkUser($n, $rol);
    $pid = ex("INSERT INTO persona(cedula, nombres, apellidos, fecha_nacimiento, telefono, correo, direccion, id_usuario) 
              VALUES(?, ?, ?, ?, ?, ?, ?, ?)", [$c, $n, $a, $f, trim($_POST['telefono'] ?? ''), $m ?: null, trim($_POST['direccion'] ?? ''), $u[0]]);
    return [$pid, $u[1]];
}

$u = $_SESSION['u'] ?? null;
$R = $u['rol'] ?? 0;

$MENU = [
    1 => ['dash', 'est', 'doc', 'gru', 'ins', 'pag', 'rep'],
    2 => ['dash', 'est', 'ins', 'pag'],
    3 => ['dash', 'asi', 'not'],
    4 => ['dash', 'bol', 'ec']
];

$LBL = [
    'dash' => 'Inicio', 'est' => 'Estudiantes', 'doc' => 'Docentes', 'gru' => 'Grupos',
    'ins' => 'Inscribir', 'pag' => 'Cobros', 'rep' => 'Reportes', 'asi' => 'Pasar lista',
    'not' => 'Notas', 'bol' => 'Boletín', 'ec' => 'Mi cuenta', 'rec' => 'Recibo'
];

if ($R == 3) $LBL['dash'] = 'Mis grupos';
if ($R == 4) $LBL['dash'] = 'Mis cursos';

$ICON = [
    'home' => '<path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
    'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="M16 5.2a3.2 3.2 0 010 6"/><path d="M18 14.5c2 .8 3.5 2.8 3.5 5.5"/>',
    'cap' => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11.5V16c0 1.5 2.7 3 6 3s6-1.5 6-3v-4.5"/>',
    'layers' => '<path d="M12 3l9 5-9 5-9-5z"/><path d="M3 13l9 5 9-5"/>',
    'plus' => '<path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8z"/><path d="M14 3v5h5"/><path d="M12 12v6M9 15h6"/>',
    'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h3"/>',
    'chart' => '<path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 16v-5M13 16V8M18 16v-3"/>',
    'check' => '<rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/>',
    'list' => '<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6h.01M4 12h.01M4 18h.01"/>',
    'file' => '<path d="M14 3H6a1 1 0 00-1 1v16a1 1 0 001 1h12a1 1 0 001-1V8z"/><path d="M14 3v5h5"/><path d="M9 13h6M9 17h6"/>'
];

$IV = [
    'dash' => 'home', 'est' => 'users', 'doc' => 'cap', 'gru' => 'layers',
    'ins' => 'plus', 'pag' => 'card', 'rep' => 'chart', 'asi' => 'check',
    'not' => 'list', 'bol' => 'file', 'ec' => 'card'
];

function ic($n) {
    global $ICON;
    return '<svg viewBox="0 0 24 24" aria-hidden="true">' . $ICON[$n] . '</svg>';
}

function ACT($a) {
    global $u, $R, $hoy;
    if ($a == 'login') {
        $us = one("SELECT u.*, p.nombres, p.apellidos, p.id_persona FROM usuario u JOIN persona p ON p.id_usuario = u.id_usuario WHERE u.username = ?", [trim($_POST['user'] ?? '')]);
        must($us && password_verify($_POST['pass'] ?? '', $us['contrasena_hash']) && $us['activo'], 'Usuario o contraseña incorrectos.');
        session_regenerate_id(true);
        $e = one("SELECT id_estudiante FROM estudiante WHERE id_persona = ?", [$us['id_persona']]);
        $d = one("SELECT id_docente FROM docente WHERE id_persona = ?", [$us['id_persona']]);
        $_SESSION['t'] = bin2hex(random_bytes(16));
        $_SESSION['u'] = [
            'rol' => (int)$us['id_rol'],
            'nombre' => nom($us),
            'est' => $e['id_estudiante'] ?? null,
            'doc' => $d['id_docente'] ?? null
        ];
        return 'v=dash';
    }
    
    must($u, 'Debes iniciar sesión.');
    if ($a == 'logout') {
        session_destroy();
        session_start();
        return 'v=dash';
    }
    
    if ($a == 'addEst') {
        must(in_array($R, [1, 2]), 'Sin permiso.');
        $p = mkPersona(4);
        ex("INSERT INTO estudiante(id_persona, fecha_registro) VALUES(?, ?)", [$p[0], $hoy]);
        flash("Estudiante creado. Usuario: {$p[1]} · clave: 1234");
    }
    
    if ($a == 'estado') {
        must(in_array($R, [1, 2]), 'Sin permiso.');
        ex("UPDATE estudiante SET estado = ? WHERE id_estudiante = ?", [$_POST['estado'], $_POST['id']]);
        flash('Estado actualizado.');
    }
    
    if ($a == 'addDoc') {
        must($R == 1, 'Sin permiso.');
        must(trim($_POST['especialidad'] ?? '') && ($_POST['contratacion'] ?? ''), 'Especialidad y fecha de contratación son obligatorias.');
        $p = mkPersona(3);
        ex("INSERT INTO docente(id_persona, especialidad, fecha_contratacion) VALUES(?, ?, ?)", [$p[0], trim($_POST['especialidad']), $_POST['contratacion']]);
        flash("Docente creado. Usuario: {$p[1]} · clave: 1234");
    }
    
    if ($a == 'addCur') {
        must($R == 1, 'Sin permiso.');
        must(trim($_POST['nombre'] ?? '') && $_POST['horas'] > 0 && $_POST['precio'] >= 0, 'Revisa nombre, horas y precio.');
        ex("INSERT INTO curso(id_idioma, id_nivel, nombre, duracion_horas, precio) VALUES(?, ?, ?, ?, ?)", [$_POST['idioma'], $_POST['nivel'], trim($_POST['nombre']), $_POST['horas'], $_POST['precio']]);
        flash('Curso agregado.');
    }
    
    if ($a == 'addGru') {
        global $db;
        must($R == 1, 'Sin permiso.');
        $ds = (array)($_POST['dias'] ?? []);
        $ini = $_POST['hi'] ?? '';
        $fin = $_POST['hf'] ?? '';
        $cu = (int)$_POST['cupo'];
        
        must($ds, 'Elige al menos un día.');
        must($ini && $fin && $ini < $fin, 'Revisa las horas.');
        must($_POST['fi'] && $_POST['ff'] && $_POST['fi'] < $_POST['ff'], 'Revisa las fechas.');
        must($cu > 0 && $cu <= one("SELECT capacidad c FROM aula WHERE id_aula = ?", [$_POST['aula']])['c'], 'El cupo supera la capacidad del aula.');
        
        foreach ($ds as $d) {
            must(!one("SELECT 1 FROM horario h JOIN grupo g ON g.id_grupo = h.id_grupo WHERE h.dia_semana = ? AND ? < h.hora_fin AND h.hora_inicio < ? AND (g.id_docente = ? OR g.id_aula = ?) LIMIT 1", [$d, $ini, $fin, $_POST['docente'], $_POST['aula']]), "Choque el {$d}: el docente o el aula están ocupados.");
        }
        
        $db->beginTransaction();
        $g = ex("INSERT INTO grupo(id_curso, id_docente, id_aula, cupo_maximo, fecha_inicio, fecha_fin) VALUES(?, ?, ?, ?, ?, ?)", [$_POST['curso'], $_POST['docente'], $_POST['aula'], $cu, $_POST['fi'], $_POST['ff']]);
        foreach ($ds as $d) {
            ex("INSERT INTO horario(id_grupo, dia_semana, hora_inicio, hora_fin) VALUES(?, ?, ?, ?)", [$g, $d, $ini, $fin]);
        }
        foreach ([['Parcial', 30], ['Oral', 30], ['Final', 40]] as $ev) {
            ex("INSERT INTO evaluacion(id_grupo, nombre, ponderacion) VALUES(?, ?, ?)", [$g, $ev[0], $ev[1]]);
        }
        $db->commit();
        flash('Grupo creado.');
    }
    
    if ($a == 'inscribir') {
        must(in_array($R, [1, 2]), 'Sin permiso.');
        $e = (int)$_POST['est'];
        $g = (int)$_POST['grupo'];
        $G = one("SELECT * FROM grupo WHERE id_grupo = ?", [$g]);
        
        must(!one("SELECT 1 FROM inscripcion WHERE id_estudiante = ? AND id_grupo = ? AND estado = 'activa'", [$e, $g]), 'Ya está inscrito en este grupo.');
        must($G['estado'] != 'cerrado', 'El grupo está cerrado.');
        must(one("SELECT COUNT(*) n FROM inscripcion WHERE id_grupo = ? AND estado = 'activa'", [$g])['n'] < $G['cupo_maximo'], 'El grupo no tiene cupos.');
        must(!one("SELECT 1 FROM horario h JOIN horario o ON o.dia_semana = h.dia_semana AND o.hora_inicio < h.hora_fin AND h.hora_inicio < o.hora_fin JOIN inscripcion i ON i.id_grupo = o.id_grupo AND i.estado = 'activa' AND i.id_estudiante = ? WHERE h.id_grupo = ? LIMIT 1", [$e, $g]), 'El estudiante ya tiene clase a esa hora.');
        
        ex("INSERT INTO inscripcion(id_estudiante, id_grupo, fecha_inscripcion) VALUES(?, ?, ?)", [$e, $g, $hoy]);
        flash('Inscripción registrada.');
    }
    
    if ($a == 'retirar') {
        must(in_array($R, [1, 2]), 'Sin permiso.');
        ex("UPDATE inscripcion SET estado = 'retirada' WHERE id_inscripcion = ?", [$_POST['id']]);
        flash('Inscripción retirada.');
    }
    
    if ($a == 'pagar') {
        must(in_array($R, [1, 2]), 'Sin permiso.');
        $i = inscs("WHERE i.id_inscripcion = ?", [$_POST['insc']])[0];
        $m = round((float)$_POST['monto'], 2);
        must($m > 0, 'Escribe un monto mayor que 0.');
        must($m <= round(saldo($i), 2), 'El monto supera el saldo pendiente.');
        
        $id = ex("INSERT INTO pago(id_inscripcion, id_metodo, fecha_pago, monto, concepto, num_recibo) VALUES(?, ?, ?, ?, ?, ?)", [$i['id_inscripcion'], $_POST['metodo'], $hoy, $m, $_POST['concepto'], 'TMP' . uniqid()]);
        ex("UPDATE pago SET num_recibo = ? WHERE id_pago = ?", ['REC-' . str_pad($id, 4, '0', STR_PAD_LEFT), $id]);
        flash('Pago registrado.');
        return "v=rec&p={$id}";
    }
    
    if ($a == 'anular') {
        must($R == 1, 'Sin permiso.');
        ex("DELETE FROM pago WHERE id_pago = ?", [$_POST['id']]);
        flash('Pago anulado.');
    }
    
    if ($a == 'lista') {
        must($R == 3, 'Sin permiso.');
        $g = (int)$_POST['g'];
        must(one("SELECT 1 FROM grupo WHERE id_grupo = ? AND id_docente = ?", [$g, $u['doc']]), 'Grupo no asignado.');
        $f = $_POST['fecha'];
        
        $s = one("SELECT id_sesion FROM sesion WHERE id_grupo = ? AND fecha = ?", [$g, $f]);
        $sid = $s ? $s['id_sesion'] : ex("INSERT INTO sesion(id_grupo, fecha, tema) VALUES(?, ?, ?)", [$g, $f, '']);
        ex("UPDATE sesion SET tema = ? WHERE id_sesion = ?", [trim($_POST['tema']), $sid]);
        
        foreach (q("SELECT id_inscripcion FROM inscripcion WHERE id_grupo = ? AND estado = 'activa'", [$g]) as $i) {
            ex("INSERT INTO asistencia(id_inscripcion, id_sesion, presente) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE presente = VALUES(presente)", [$i['id_inscripcion'], $sid, isset($_POST['p'][$i['id_inscripcion']]) ? 1 : 0]);
        }
        flash('Lista guardada.');
    }
    
    if ($a == 'notas') {
        must($R == 3, 'Sin permiso.');
        $g = (int)$_POST['g'];
        must(one("SELECT 1 FROM grupo WHERE id_grupo = ? AND id_docente = ?", [$g, $u['doc']]), 'Grupo no asignado.');
        
        foreach (($_POST['n'] ?? []) as $iid => $evs) {
            foreach ($evs as $eid => $n) {
                if ($n === '') continue;
                must(is_numeric($n) && $n >= 0 && $n <= 100, 'Las notas deben ser entre 0 y 100.');
                must(one("SELECT 1 FROM inscripcion i JOIN evaluacion e ON e.id_grupo = i.id_grupo WHERE i.id_inscripcion = ? AND e.id_evaluacion = ? AND i.id_grupo = ?", [$iid, $eid, $g]), 'Dato inválido.');
                ex("INSERT INTO calificacion(id_inscripcion, id_evaluacion, nota) VALUES(?, ?, ?) ON DUPLICATE KEY UPDATE nota = VALUES(nota)", [$iid, $eid, $n]);
            }
        }
        flash('Notas guardadas.');
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        must(hash_equals($_SESSION['t'], $_POST['t'] ?? ''), 'La sesión venció.');
        $r = ACT($_POST['a'] ?? '');
    } catch (Exception $x) {
        $msg = ($x instanceof PDOException) ? ((($x->errorInfo[1] ?? 0) == 1062) ? 'El registro ya existe.' : 'Error en la base de datos.') : $x->getMessage();
        flash($msg, false);
        $r = null;
    }
    go($r ?? preg_replace('/[^a-zA-Z0-9=&_.-]/', '', $_POST['back'] ?? 'v=dash') ?: 'v=dash');
}

$v = $_GET['v'] ?? 'dash';
$ALLOW = $u ? array_merge($MENU[$R], $R < 3 ? ['bol', 'ec', 'rec'] : []) : [];
if ($u && !in_array($v, $ALLOW)) $v = 'dash';

function head($t) {
    global $u, $R;
    $f = $_SESSION['f'] ?? null;
    unset($_SESSION['f']);
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($t) . ' · Academia de Idiomas</title><link rel="stylesheet" href="estilos.css"></head><body><div class="wrap">';
    if ($u) {
        $roles = ['', 'Administrador', 'Recepción', 'Docente', 'Estudiante'];
        echo '<div class="who"><span>' . $roles[$R] . ' · ' . e($u['nombre']) . '</span><form method="post" style="margin:0">' . hidden('logout', 'v=dash') . '<button class="btn s o">Salir</button></form></div>';
    }
    if ($f) {
        echo '<div class="msg ' . ($f[1] ? 'ok' : 'bad') . '">' . e($f[0]) . '</div>';
    }
}

function foot() {
    global $u, $R, $v, $MENU, $LBL, $IV;
    echo '</div>';
    if ($u) {
        echo '<nav class="nav">';
        foreach ($MENU[$R] as $k) {
            $active = ($k == $v) ? 'on' : '';
            echo '<a href="?v=' . $k . '" class="' . $active . '">' . ic($IV[$k]) . $LBL[$k] . '</a>';
        }
        echo '</nav>';
    }
    echo '</body></html>';
}

function H($t, $s) {
    return "<h2>{$t}</h2><p class=\"sub\">{$s}</p>";
}

function cTbl($i) {
    $o = '<div class="wide"><table><tr><th>Cuota</th><th>Vence</th><th>Monto</th><th>Estado</th></tr>';
    foreach (cuotas($i) as $c) {
        $stClass = ($c['e'] == 'Pagada') ? 'ok' : (($c['e'] == 'Vencida') ? 'b' : 'w');
        $o .= '<tr><td>' . $c['n'] . '</td><td>' . $c['v'] . '</td><td>' . money($c['m']) . '</td><td>' . tag($c['e'], $stClass) . '</td></tr>';
    }
    return $o . '</table></div>';
}

function sid() {
    global $R, $u;
    if ($R == 4) return $u['est'];
    return (int)($_GET['e'] ?? (one("SELECT id_estudiante i FROM estudiante ORDER BY 1")['i'] ?? 0));
}

function stSel() {
    global $R;
    if ($R == 4) return '';
    $opts = opts(q("SELECT es.id_estudiante, pe.nombres, pe.apellidos FROM estudiante es JOIN persona pe ON pe.id_persona = es.id_persona"), 'id_estudiante', 'nom', sid());
    return '<form method="get" class="card noprint"><input type="hidden" name="v" value="' . e($_GET['v']) . '"><label style="margin:0">Estudiante<select name="e" onchange="this.form.submit()">' . $opts . '</select></label></form>';
}

function personaForm() {
    return '<label>Nombres<input name="nombres" required></label>
            <label>Apellidos<input name="apellidos" required></label>
            <label>Cédula (11 dígitos)<input name="cedula" required inputmode="numeric"></label>
            <label>Fecha de nacimiento<input name="nacimiento" type="date" required></label>
            <label>Teléfono<input name="telefono" type="tel"></label>
            <label>Correo<input name="correo" type="email"></label>
            <label>Dirección<input name="direccion"></label>';
}

if (!$u) {
    head('Entrar');
    echo '<div class="lg"><div class="logo">LOGO</div><h2>Bienvenido</h2><p class="sub">Entra con tu usuario y contraseña.</p>
    <form method="post" class="card">' . hidden('login', 'v=dash') . '<label>Usuario<input name="user" required autofocus></label><label>Contraseña<input name="pass" type="password" required></label><button class="btn">Entrar</button></form>
    <p class="sub" style="font-size:13px">Prueba con: admin, recepcion, docente o estudiante (clave 1234)</p></div>';
    foot();
    exit;
}

head($LBL[$v] ?? 'Inicio');

if ($v == 'dash') {
    if ($R == 3) {
        echo H('Mis grupos', 'Elige un grupo para pasar lista o poner notas.');
        foreach (grupos("WHERE g.id_docente = ?", [$u['doc']]) as $g) {
            echo '<div class="card"><b>' . e($g['curso']) . '</b><div class="sub" style="margin:2px 0 10px">' . e(hs($g['id_grupo'])) . ' · ' . e($g['aula']) . ' · ' . $g['inscritos'] . '/' . $g['cupo_maximo'] . ' inscritos</div><a class="btn s" href="?v=asi&g=' . $g['id_grupo'] . '">Pasar lista</a> <a class="btn s o" href="?v=not&g=' . $g['id_grupo'] . '">Poner notas</a></div>';
        }
    } elseif ($R == 4) {
        echo H('Mis cursos', 'Estos son los cursos en los que estás inscrito.');
        $ven = 0;
        foreach (inscs("WHERE i.id_estudiante = ? AND i.estado = 'activa'", [$u['est']]) as $i) {
            $ven += vencido($i);
            $g = grupos("WHERE g.id_grupo = ?", [$i['id_grupo']])[0];
            echo '<div class="card"><b>' . e($g['curso']) . '</b><div class="sub" style="margin:2px 0 0">Docente: ' . e($g['docente']) . '<br>' . e(hs($g['id_grupo'])) . ' · ' . e($g['aula']) . '<br>Del ' . $g['fecha_inicio'] . ' al ' . $g['fecha_fin'] . '</div></div>';
        }
        if ($ven) echo '<div class="card"><b class="tag b">Tienes pagos vencidos: ' . money($ven) . '</b></div>';
    } else {
        $mor = 0;
        foreach (inscs("WHERE i.estado = 'activa'") as $i) {
            if (vencido($i) > 0) $mor++;
        }
        echo H('Hola, ' . e(explode(' ', $u['nombre'])[0]), $R == 1 ? 'Como administrador puedes ver y gestionar todo.' : 'Elige qué quieres hacer.') .
             '<div class="stats">
                <div><b>' . one("SELECT COUNT(*) n FROM estudiante")['n'] . '</b><span>Estudiantes</span></div>
                <div><b>' . one("SELECT COUNT(*) n FROM grupo")['n'] . '</b><span>Grupos</span></div>
                <div><b>' . one("SELECT COUNT(*) n FROM inscripcion WHERE estado = 'activa'")['n'] . '</b><span>Inscripciones</span></div>
                <div><b>' . $mor . '</b><span>Morosos</span></div>' .
                ($R == 1 ? '<div><b>' . money(one("SELECT COALESCE(SUM(monto), 0) s FROM pago")['s']) . '</b><span>Ingresos</span></div>' : '') .
             '</div>';
        foreach (array_slice($MENU[$R], 1) as $k) {
            echo '<a class="big" href="?v=' . $k . '"><i>' . ic($IV[$k]) . '</i>' . $LBL[$k] . '</a>';
        }
    }
}

if ($v == 'est') {
    $b = trim($_GET['b'] ?? '');
    echo H('Estudiantes', 'Registra, busca y gestiona estudiantes.') .
         '<form method="post" class="card"><b>Nuevo estudiante</b><div style="height:10px"></div>' . hidden('addEst') . personaForm() . '<button class="btn">Agregar estudiante</button></form>
          <form class="noprint"><input type="hidden" name="v" value="est"><input name="b" value="' . e($b) . '" placeholder="Buscar por nombre o cédula" style="margin:0 0 12px"></form>
          <div class="card">';
    foreach (q("SELECT es.*, pe.nombres, pe.apellidos, pe.cedula, pe.telefono, u.username FROM estudiante es JOIN persona pe ON pe.id_persona = es.id_persona JOIN usuario u ON u.id_usuario = pe.id_usuario WHERE CONCAT(pe.nombres, ' ', pe.apellidos, pe.cedula) LIKE ?", ["%$b%"]) as $s) {
        $d = 0;
        foreach (inscs("WHERE i.id_estudiante = ? AND i.estado = 'activa'", [$s['id_estudiante']]) as $i) $d += vencido($i);
        $stOpts = '';
        foreach (['activo', 'inactivo', 'retirado'] as $x) {
            $stOpts .= '<option' . ($x == $s['estado'] ? ' selected' : '') . '>' . $x . '</option>';
        }
        echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e(nom($s)) . '<small>' . e($s['cedula']) . ' · ' . e($s['telefono']) . ' · usuario: ' . e($s['username']) . '</small></div>' . tag($d ? 'vencido ' . money($d) : 'al día', $d ? 'b' : 'ok') . '</div>
              <div class="bt"><form method="post">' . hidden('estado') . '<input type="hidden" name="id" value="' . $s['id_estudiante'] . '"><select name="estado" onchange="this.form.submit()">' . $stOpts . '</select></form>
              <a class="btn s o" href="?v=ec&e=' . $s['id_estudiante'] . '">Cuenta</a><a class="btn s o" href="?v=bol&e=' . $s['id_estudiante'] . '">Boletín</a></div></div>';
    }
    echo '</div>';
}

if ($v == 'doc') {
    echo H('Docentes', 'Registra y consulta docentes.') .
         '<form method="post" class="card"><b>Nuevo docente</b><div style="height:10px"></div>' . hidden('addDoc') . personaForm() . '<label>Especialidad<input name="especialidad" required></label><label>Fecha de contratación<input name="contratacion" type="date" value="' . $hoy . '" required></label><button class="btn">Agregar docente</button></form>
          <div class="card">';
    foreach (q("SELECT d.*, pe.nombres, pe.apellidos, pe.telefono, u.username FROM docente d JOIN persona pe ON pe.id_persona = d.id_persona JOIN usuario u ON u.id_usuario = pe.id_usuario") as $d) {
        echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e(nom($d)) . '<small>' . e($d['telefono']) . ' · usuario: ' . e($d['username']) . ' · desde ' . $d['fecha_contratacion'] . '</small></div>' . tag($d['especialidad']) . '</div></div>';
    }
    echo '</div>';
}

if ($v == 'gru') {
    echo H('Grupos y cursos', 'Puedes elegir varios días. Se revisa que no choque el docente ni el aula.') .
         '<form method="post" class="card"><b>Nuevo curso</b><div style="height:10px"></div>' . hidden('addCur') . '
          <label>Idioma<select name="idioma">' . opts(q("SELECT * FROM idioma"), 'id_idioma', 'nombre') . '</select></label>
          <label>Nivel<select name="nivel">' . opts(q("SELECT * FROM nivel"), 'id_nivel', fn($r) => $r['codigo'] . ' · ' . $r['descripcion']) . '</select></label>
          <label>Nombre del curso<input name="nombre" required></label>
          <label>Horas<input name="horas" type="number" value="60" min="1"></label>
          <label>Precio (RD$)<input name="precio" type="number" value="4000" min="0" step="0.01"></label>
          <button class="btn">Agregar curso</button></form>
          
          <form method="post" class="card"><b>Nuevo grupo</b><div style="height:10px"></div>' . hidden('addGru') . '
          <label>Curso<select name="curso">' . opts(q("SELECT * FROM curso"), 'id_curso', 'nombre') . '</select></label>
          <label>Docente<select name="docente">' . opts(q("SELECT d.id_docente, pe.nombres, pe.apellidos FROM docente d JOIN persona pe ON pe.id_persona = d.id_persona"), 'id_docente', 'nom') . '</select></label>
          <label>Aula<select name="aula">' . opts(q("SELECT * FROM aula"), 'id_aula', fn($r) => $r['nombre'] . ' (' . $r['capacidad'] . ')') . '</select></label>
          <label>Cupo<input name="cupo" type="number" value="10" min="1"></label><div>';
    foreach (['Lunes' => 'Lun', 'Martes' => 'Mar', 'Miércoles' => 'Mié', 'Jueves' => 'Jue', 'Viernes' => 'Vie', 'Sábado' => 'Sáb'] as $d => $c3) {
        echo '<label class="dy"><input type="checkbox" name="dias[]" value="' . $d . '">' . $c3 . '</label>';
    }
    echo '</div>
          <label>Hora de inicio<input name="hi" type="time" value="18:00"></label>
          <label>Hora de fin<input name="hf" type="time" value="20:00"></label>
          <label>Fecha de inicio<input name="fi" type="date" value="' . $hoy . '"></label>
          <label>Fecha de fin<input name="ff" type="date" value="' . date('Y-m-d', strtotime('+90 days')) . '"></label>
          <button class="btn">Crear grupo</button></form>
          <div class="card">';
    foreach (grupos() as $g) {
        echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e($g['curso']) . '<small>' . e($g['docente']) . ' · ' . e($g['aula']) . ' · ' . e(hs($g['id_grupo'])) . '<br>' . $g['fecha_inicio'] . ' a ' . $g['fecha_fin'] . '</small></div>' . tag($g['inscritos'] . '/' . $g['cupo_maximo']) . '</div></div>';
    }
    echo '</div>';
}

if ($v == 'ins') {
    echo H('Inscribir estudiante', 'Se revisan cupos, duplicados y choques de horario.') .
         '<form method="post" class="card">' . hidden('inscribir') . '
          <label>1. Estudiante<select name="est">' . opts(q("SELECT es.id_estudiante, pe.nombres, pe.apellidos FROM estudiante es JOIN persona pe ON pe.id_persona = es.id_persona WHERE es.estado = 'activo'"), 'id_estudiante', 'nom') . '</select></label>
          <label>2. Grupo<select name="grupo">' . opts(grupos(), 'id_grupo', fn($g) => $g['curso'] . ' · ' . hs($g['id_grupo']) . ' · ' . ($g['cupo_maximo'] - $g['inscritos']) . ' cupos') . '</select></label>
          <button class="btn">Inscribir</button></form>
          <div class="card"><b>Inscripciones</b>';
    foreach (inscs() as $i) {
        echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e(nom($i)) . '<small>' . e($i['curso']) . ' · ' . $i['fecha_inscripcion'] . '</small></div>' . tag($i['estado'], $i['estado'] == 'activa' ? 'ok' : 'b') . '</div>' .
             ($i['estado'] == 'activa' ? '<div class="bt"><form method="post">' . hidden('retirar') . '<input type="hidden" name="id" value="' . $i['id_inscripcion'] . '"><button class="btn s o">Retirar</button></form></div>' : '') . '</div>';
    }
    echo '</div>';
}

if ($v == 'pag') {
    $a = inscs("WHERE i.estado = 'activa'");
    if (!$a) {
        echo H('Cobros', 'No hay inscripciones todavía.');
    } else {
        $sel = (int)($_GET['i'] ?? 0);
        $i = null;
        foreach ($a as $x) if ($x['id_inscripcion'] == $sel) $i = $x;
        if (!$i) {
            foreach ($a as $x) if (saldo($x) > 0) { $i = $x; break; }
            $i = $i ?? $a[0];
        }
        echo H('Cobrar un pago', 'Elige la inscripción y registra lo que paga.') .
             '<form method="get" class="card"><input type="hidden" name="v" value="pag"><label style="margin:0 0 12px">Inscripción<select name="i" onchange="this.form.submit()">' . opts($a, 'id_inscripcion', fn($x) => nom($x) . ' · ' . $x['curso'], $i['id_inscripcion']) . '</select></label>
              <div class="row"><span>Precio</span><b>' . money($i['precio']) . '</b></div>
              <div class="row"><span>Pagado</span>' . tag(money(pagado($i['id_inscripcion'])), 'ok') . '</div>
              <div class="row"><span>Debe</span>' . tag(money(saldo($i)), saldo($i) > 0 ? 'b' : 'ok') . '</div>' . cTbl($i) . '</form>
              <form method="post" class="card">' . hidden('pagar') . '<input type="hidden" name="insc" value="' . $i['id_inscripcion'] . '">
              <label>Monto (RD$)<input name="monto" type="number" step="0.01" min="0.01" value="' . max(0, saldo($i)) . '"></label>
              <label>Concepto<select name="concepto"><option>Mensualidad</option><option>Matrícula</option></select></label>
              <label>Método de pago<select name="metodo">' . opts(q("SELECT * FROM metodo_pago"), 'id_metodo', 'nombre') . '</select></label>
              <button class="btn">Cobrar y emitir recibo</button></form>
              <div class="card"><b>Morosos</b>';
        foreach ($a as $x) {
            if (vencido($x) > 0) {
                echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e(nom($x)) . '<small>' . e($x['curso']) . '</small></div>' . tag(money(vencido($x)), 'b') . '</div><div class="bt"><a class="btn s o" href="?v=ec&e=' . $x['id_estudiante'] . '">Estado de cuenta</a></div></div>';
            }
        }
        echo '</div><div class="card"><b>Últimos pagos</b>';
        foreach (q("SELECT p.*, pe.nombres, pe.apellidos FROM pago p JOIN inscripcion i ON i.id_inscripcion = p.id_inscripcion JOIN estudiante es ON es.id_estudiante = i.id_estudiante JOIN persona pe ON pe.id_persona = es.id_persona ORDER BY p.id_pago DESC LIMIT 5") as $p) {
            echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e($p['num_recibo']) . ' · ' . $p['fecha_pago'] . '<small>' . e(nom($p)) . ' · ' . e($p['concepto']) . '</small></div><b>' . money($p['monto']) . '</b></div>
                  <div class="bt"><a class="btn s o" href="?v=rec&p=' . $p['id_pago'] . '">Recibo</a>' .
                  ($R == 1 ? '<form method="post" onsubmit="return confirm(\'¿Anular este pago?\')">' . hidden('anular') . '<input type="hidden" name="id" value="' . $p['id_pago'] . '"><button class="btn s o d">Anular</button></form>' : '') . '</div></div>';
        }
        echo '</div>';
    }
}

if ($v == 'rec') {
    $p = one("SELECT p.*, m.nombre metodo FROM pago p JOIN metodo_pago m ON m.id_metodo = p.id_metodo WHERE p.id_pago = ?", [(int)($_GET['p'] ?? 0)]);
    if (!$p) {
        echo H('Recibo', 'No se encontró el pago.');
    } else {
        $i = inscs("WHERE i.id_inscripcion = ?", [$p['id_inscripcion']])[0];
        $r = fn($a, $b) => '<div class="row"><span>' . $a . '</span><b>' . $b . '</b></div>';
        echo '<div class="card"><div style="text-align:center"><div class="logo" style="width:54px;height:54px">LOGO</div><b>Academia de Idiomas</b><div class="sub">Recibo de pago · ' . e($p['num_recibo']) . '</div></div>' .
             $r('Fecha', $p['fecha_pago']) . $r('Estudiante', e(nom($i))) . $r('Curso', e($i['curso'])) . $r('Concepto', e($p['concepto'])) . $r('Método', e($p['metodo'])) . $r('Monto', money($p['monto'])) . $r('Saldo pendiente', money(saldo($i))) .
             '</div><div class="bt noprint"><button class="btn s" onclick="print()">Imprimir</button><a class="btn s o" href="?v=pag">Volver a cobros</a></div>';
    }
}

if ($v == 'ec' || $v == 'bol') {
    $id = sid();
    $P = one("SELECT pe.* FROM estudiante es JOIN persona pe ON pe.id_persona = es.id_persona WHERE es.id_estudiante = ?", [$id]);
    echo H($v == 'ec' ? 'Estado de cuenta' : 'Boletín de calificaciones', $v == 'ec' ? 'Cuotas, pagos y lo que se debe.' : 'Notas, asistencia y resultado por curso.') . stSel();
    if (!$P) {
        echo '<div class="card">No hay estudiantes.</div>';
    } else {
        echo '<div class="card"><b>' . e(nom($P)) . '</b><div class="sub" style="margin:0">Cédula ' . e($P['cedula']) . ' · Emitido el ' . $hoy . '</div></div>';
        foreach (inscs("WHERE i.id_estudiante = ? AND i.estado <> 'retirada'", [$id]) as $i) {
            echo '<div class="card"><b>' . e($i['curso']) . '</b>';
            if ($v == 'ec') {
                echo '<div class="row"><span>Precio</span><b>' . money($i['precio']) . '</b></div>
                      <div class="row"><span>Pagado</span>' . tag(money(pagado($i['id_inscripcion'])), 'ok') . '</div>
                      <div class="row"><span>Debe</span>' . tag(money(saldo($i)), saldo($i) > 0 ? 'b' : 'ok') . '</div>
                      <div class="row"><span>Vencido</span>' . tag(money(vencido($i)), vencido($i) > 0 ? 'b' : 'ok') . '</div>' . cTbl($i);
                foreach (q("SELECT * FROM pago WHERE id_inscripcion = ?", [$i['id_inscripcion']]) as $p) {
                    echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e($p['num_recibo']) . '<small>' . e($p['concepto']) . ' · ' . $p['fecha_pago'] . '</small></div><b>' . money($p['monto']) . '</b></div></div>';
                }
            } else {
                $evs = q("SELECT * FROM evaluacion WHERE id_grupo = ?", [$i['id_grupo']]);
                $n = 0;
                foreach ($evs as $e) {
                    $c = one("SELECT nota FROM calificacion WHERE id_inscripcion = ? AND id_evaluacion = ?", [$i['id_inscripcion'], $e['id_evaluacion']]);
                    if ($c) $n++;
                    echo '<div class="row"><span>' . e($e['nombre']) . ' (' . (float)$e['ponderacion'] . '%)</span><b>' . ($c ? (float)$c['nota'] : '—') . '</b></div>';
                }
                $pr = prom($i['id_inscripcion']);
                $as = asis($i['id_inscripcion']);
                $res = ($evs && $n == count($evs)) ? ($pr >= 70 ? 'Aprobado' : 'Reprobado') : 'En curso';
                echo '<div class="row"><span>Promedio</span>' . tag($pr === null ? '—' : number_format($pr, 1)) . '</div>
                      <div class="row"><span>Asistencia</span>' . tag($as === null ? '—' : $as . '%', ($as !== null && $as < 80) ? 'w' : 'ok') . '</div>
                      <div class="row"><span>Resultado</span>' . tag($res, $res == 'Aprobado' ? 'ok' : ($res == 'Reprobado' ? 'b' : 'w')) . '</div>';
            }
            echo '</div>';
        }
        echo '<button class="btn o noprint" onclick="print()">Imprimir</button>';
    }
}

if ($v == 'rep') {
    $d = $_GET['desde'] ?? '';
    $h = $_GET['hasta'] ?? '';
    $w = 'WHERE (? = "" OR fecha_pago >= ?) AND (? = "" OR fecha_pago <= ?)';
    $pp = [$d, $d, $h, $h];
    $act = inscs("WHERE i.estado = 'activa'");
    $por = 0;
    $ven = 0;
    foreach ($act as $i) {
        $por += max(0, saldo($i));
        $ven += vencido($i);
    }
    echo H('Reportes', 'Filtra por fechas e imprime.') .
         '<form class="card noprint"><input type="hidden" name="v" value="rep"><label>Desde<input type="date" name="desde" value="' . e($d) . '"></label><label>Hasta<input type="date" name="hasta" value="' . e($h) . '"></label><div class="bt"><button class="btn s">Filtrar</button><button type="button" class="btn s o" onclick="print()">Imprimir</button></div></form>
          <div class="stats">
            <div><b>' . money(one("SELECT COALESCE(SUM(monto), 0) s FROM pago $w", $pp)['s']) . '</b><span>Ingresos del período</span></div>
            <div><b>' . one("SELECT COUNT(*) n FROM pago $w", $pp)['n'] . '</b><span>Pagos registrados</span></div>
            <div><b>' . money($por) . '</b><span>Por cobrar</span></div>
            <div><b>' . money($ven) . '</b><span>Vencido</span></div>
          </div>
          <div class="card"><b>Ingresos por método</b>';
    foreach (q("SELECT m.nombre, COALESCE(SUM(p.monto), 0) s FROM metodo_pago m LEFT JOIN pago p ON p.id_metodo = m.id_metodo AND (? = '' OR p.fecha_pago >= ?) AND (? = '' OR p.fecha_pago <= ?) GROUP BY m.id_metodo", $pp) as $m) {
        echo '<div class="row"><span>' . e($m['nombre']) . '</span><b>' . money($m['s']) . '</b></div>';
    }
    echo '</div><div class="card"><b>Por grupo</b>';
    foreach (grupos() as $g) {
        $ps = [];
        $as = [];
        foreach (inscs("WHERE i.id_grupo = ? AND i.estado = 'activa'", [$g['id_grupo']]) as $i) {
            if (($x = prom($i['id_inscripcion'])) !== null) $ps[] = $x;
            if (($y = asis($i['id_inscripcion'])) !== null) $as[] = $y;
        }
        $avgP = $ps ? number_format(array_sum($ps) / count($ps), 1) : '—';
        $avgA = $as ? round(array_sum($as) / count($as)) . '%' : '—';
        echo '<div class="rb"><div class="row" style="border:0;padding:0"><div>' . e($g['curso']) . '<small>Promedio ' . $avgP . ' · Asistencia ' . $avgA . '</small></div>' . tag($g['inscritos'] . '/' . $g['cupo_maximo']) . '</div></div>';
    }
    echo '</div>';
}

if ($v == 'asi' || $v == 'not') {
    $gs = grupos("WHERE g.id_docente = ?", [$u['doc']]);
    if (!$gs) {
        echo H('Sin grupos', 'No tienes grupos asignados.');
    } else {
        $g = (int)($_GET['g'] ?? $gs[0]['id_grupo']);
        if (!in_array($g, array_column($gs, 'id_grupo'))) $g = $gs[0]['id_grupo'];
        
        $selOpts = opts($gs, 'id_grupo', fn($x) => $x['curso'] . ' · ' . hs($x['id_grupo']), $g);
        $sel = '<form method="get"><input type="hidden" name="v" value="' . $v . '"><label>Grupo<select name="g" onchange="this.form.submit()">' . $selOpts . '</select></label>';
        $ins = inscs("WHERE i.id_grupo = ? AND i.estado = 'activa'", [$g]);
        
        if ($v == 'asi') {
            $f = $_GET['fecha'] ?? $hoy;
            $se = one("SELECT * FROM sesion WHERE id_grupo = ? AND fecha = ?", [$g, $f]);
            echo H('Pasar lista', 'Marca a los que vinieron y guarda.') . '<div class="card">' . $sel . '<label>Fecha de la clase<input type="date" name="fecha" value="' . e($f) . '" onchange="this.form.submit()"></label></form><form method="post">' . hidden('lista') . '<input type="hidden" name="g" value="' . $g . '"><input type="hidden" name="fecha" value="' . e($f) . '"><label>Tema de la clase<input name="tema" value="' . e($se['tema'] ?? '') . '" placeholder="Opcional"></label>';
            foreach ($ins as $i) {
                $a = $se ? one("SELECT presente FROM asistencia WHERE id_inscripcion = ? AND id_sesion = ?", [$i['id_inscripcion'], $se['id_sesion']]) : null;
                $p = asis($i['id_inscripcion']);
                $checked = (!$a || $a['presente']) ? 'checked' : '';
                $pTag = tag($p === null ? '—' : $p . '%', ($p !== null && $p < 80) ? 'w' : 'ok');
                echo '<label class="chk"><input type="checkbox" name="p[' . $i['id_inscripcion'] . ']" ' . $checked . '><span style="flex:1">' . e(nom($i)) . '</span>' . $pTag . '</label>';
            }
            echo '<button class="btn" style="margin-top:12px">Guardar lista</button></form></div>';
        } else {
            $evs = q("SELECT * FROM evaluacion WHERE id_grupo = ?", [$g]);
            echo H('Poner notas', 'Escribe cada nota del 0 al 100.') . '<div class="card">' . $sel . '</form><form method="post">' . hidden('notas') . '<input type="hidden" name="g" value="' . $g . '"><div class="wide"><table><tr><th>Estudiante</th>';
            foreach ($evs as $e) echo '<th>' . e($e['nombre']) . ' ' . (float)$e['ponderacion'] . '%</th>';
            echo '<th>Prom.</th></tr>';
            foreach ($ins as $i) {
                echo '<tr><td>' . e(nom($i)) . '</td>';
                foreach ($evs as $e) {
                    $c = one("SELECT nota FROM calificacion WHERE id_inscripcion = ? AND id_evaluacion = ?", [$i['id_inscripcion'], $e['id_evaluacion']]);
                    $val = $c ? (float)$c['nota'] : '';
                    echo '<td><input type="number" min="0" max="100" step="0.01" name="n[' . $i['id_inscripcion'] . '][' . $e['id_evaluacion'] . ']" value="' . $val . '"></td>';
                }
                $p = prom($i['id_inscripcion']);
                echo '<td><b>' . ($p === null ? '—' : number_format($p, 1)) . '</b></td></tr>';
            }
            echo '</table></div><button class="btn" style="margin-top:12px">Guardar notas</button></form></div>';
        }
    }
}

foot();
