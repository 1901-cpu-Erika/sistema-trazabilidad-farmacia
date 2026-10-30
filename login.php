<?php
if (session_status() === PHP_SESSION_NONE) {
   session_save_path('/tmp');
session_start();

session_set_cookie_params(['path' => '/', 'secure' => true, 'httponly' => true]);
session_regenerate_id(true);
}
require_once 'config/database.php';

if (isset($_SESSION['usuario_id'])) {
    switch ($_SESSION['tipo_usuario']) {
        case 'admin':
            header("Location: modules/admin/dashboard.php");
            break;
        case 'jefe_farmacia':
            header("Location: modules/jefe_farmacia/dashboard.php");
            break;
        case 'doctor':
            header("Location: modules/doctor/dashboard.php");
            break;
        case 'farmacia':
            header("Location: modules/farmacia/dashboard.php");
            break;
        default:
            session_destroy();
            header("Location: login.php");
    }
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario  = $_POST['usuario'];
    $password = $_POST['password'];

    $sql = "SELECT u.*, e.nombre AS especialidad 
            FROM usuarios u
            LEFT JOIN especialidades e ON u.especialidad_id = e.id
            WHERE (u.email = ? OR u.nombre = ?) AND u.estado = 'activo'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $usuario, $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();

        if ($password == $user['password']) {
            session_regenerate_id(true);

            $_SESSION['usuario_id']       = $user['id'];
            $_SESSION['usuario_nombre']   = $user['nombre'];
            $_SESSION['tipo_usuario']     = $user['tipo'];
            $_SESSION['usuario_email']    = $user['email'];
            $_SESSION['especialidad_id']  = $user['especialidad_id'];
            $_SESSION['especialidad']     = $user['especialidad'] ?? 'Sin especialidad';

            switch ($user['tipo']) {
                case 'admin':
                    header("Location: modules/admin/dashboard.php");
                    break;
                case 'jefe_farmacia':
                    header("Location: modules/jefe_farmacia/dashboard.php");
                    break;
                case 'doctor':
                    header("Location: modules/doctor/dashboard.php");
                    break;
                case 'farmacia':
                    header("Location: modules/farmacia/dashboard.php");
                    break;
                default:
                    $error = "Rol no reconocido. Contacta al administrador.";
            }
            exit();
        } else {
            $error = "Contraseña incorrecta";
        }
    } else {
        $error = "Usuario no encontrado";
    }
}

$doctores_demo = [];
$sql_doc = "SELECT nombre, email, password FROM usuarios 
            WHERE tipo = 'doctor' AND estado = 'activo' 
            ORDER BY nombre ASC";
$res_doc = $conn->query($sql_doc);
if ($res_doc) {
    while ($row = $res_doc->fetch_assoc()) {
        $doctores_demo[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistema de Trazabilidad de Fármacos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0; padding: 0; overflow-x: hidden;
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 50%, #4fa3d1 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
        }
        .bg-decoration { position: absolute; border-radius: 50%; filter: blur(60px); z-index: 0; opacity: 0.4; }
        .circle-1 { width: 400px; height: 400px; background: #00c6ff; top: -100px; left: -100px; animation: float 8s infinite ease-in-out; }
        .circle-2 { width: 300px; height: 300px; background: #0072ff; bottom: -50px; right: -50px; animation: float 10s infinite ease-in-out reverse; }
        @keyframes float {
            0% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
            100% { transform: translateY(0px); }
        }
        .login-container { position: relative; z-index: 1; width: 100%; max-width: 1050px; }
        .glass-card {
            background: rgba(255,255,255,0.15); backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255,255,255,0.3);
            box-shadow: 0 8px 32px 0 rgba(31,38,135,0.37);
            border-radius: 20px; overflow: hidden;
        }
        .left-panel {
            background: rgba(255,255,255,0.1); padding: 40px; color: white;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            text-align: center; border-right: 1px solid rgba(255,255,255,0.2);
        }
        .left-panel i { font-size: 80px; margin-bottom: 20px; text-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        .right-panel { background: white; padding: 40px 45px; }
        .form-control { border-radius: 10px; border: 1px solid #e0e0e0; padding: 12px 15px; font-size: 0.95rem; }
        .form-control:focus { border-color: #2d6a9f; box-shadow: 0 0 0 0.25rem rgba(45,106,159,0.15); }
        .btn-primary {
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
            border: none; border-radius: 10px; padding: 12px; font-weight: 600;
            letter-spacing: 1px; transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26,58,92,0.4);
        }
        .quick-access {
            border-top: 1px dashed #e2e8f0;
            margin-top: 20px;
            padding-top: 20px;
        }
        .quick-access-title {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: #94a3b8;
            font-weight: 700;
            text-align: center;
            margin-bottom: 12px;
        }
        .quick-btn {
            border: 2px solid transparent;
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            background: #f8fafc;
            color: #475569;
            border-color: #e2e8f0;
        }
        .quick-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.1);
        }
        .quick-btn i { font-size: 0.9rem; }
        .quick-btn.role-admin { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .quick-btn.role-admin:hover { background: #fee2e2; border-color: #dc2626; }
        .quick-btn.role-jefe { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .quick-btn.role-jefe:hover { background: #d1fae5; border-color: #10b981; }
        .quick-btn.role-doctor { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }
        .quick-btn.role-doctor:hover { background: #dbeafe; border-color: #3b82f6; }
        .quick-btn.role-farmacia { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
        .quick-btn.role-farmacia:hover { background: #ede9fe; border-color: #8b5cf6; }

        .quick-btn.dropdown-toggle {
            position: relative;
            padding-right: 28px;
        }
        .quick-btn.dropdown-toggle::after {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 0;
            font-size: 0.6rem;
            opacity: 0.6;
            border-top-color: currentColor;
            border-right-color: currentColor;
        }

        @media (max-width: 768px) {
            .left-panel { display: none; }
            .right-panel { padding: 30px; }
        }
    </style>
</head>
<body>

    <div class="bg-decoration circle-1"></div>
    <div class="bg-decoration circle-2"></div>

    <div class="container login-container">
        <div class="row g-0">
            <div class="col-md-6">
                <div class="left-panel h-100">
                    <i class="fas fa-hospital-user"></i>
                    <h3 class="fw-bold">Sistema de Salud</h3>
                    <p class="mb-4">Trazabilidad y Gestión de Fármacos, Recetas Digitales y Atención al Paciente.</p>
                    <div class="d-flex gap-3 fs-4">
                        <i class="fas fa-prescription-bottle-medical"></i>
                        <i class="fas fa-user-md"></i>
                        <i class="fas fa-file-medical-alt"></i>
                        <i class="fas fa-shield-heart"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="right-panel h-100 d-flex flex-column justify-content-center">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-circle fa-4x" style="color: #2d6a9f;"></i>
                        <h2 class="fw-bold mt-3 mb-0" style="color: #1a3a5c;">Iniciar Sesión</h2>
                        <p class="text-muted">Accede a tu panel personal</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <div><?php echo $error; ?></div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" autocomplete="off" id="formLogin">
                        <div class="mb-3">
                            <label for="usuario" class="form-label fw-semibold">Correo o Nombre de Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-user text-muted"></i></span>
                                <input type="text"
                                       class="form-control border-start-0"
                                       id="usuario"
                                       name="usuario"
                                       placeholder=""
                                       autocomplete="off"
                                       autocapitalize="off"
                                       autocorrect="off"
                                       spellcheck="false"
                                       required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fas fa-lock text-muted"></i></span>
                                <input type="password"
                                       class="form-control border-start-0"
                                       id="password"
                                       name="password"
                                       placeholder=""
                                       autocomplete="new-password"
                                       required>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i> Entrar al Sistema
                        </button>
                    </form>

                    <div class="quick-access">
                        <div class="quick-access-title">
                            <i class="fas fa-bolt me-1"></i> Acceso Rápido
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <button type="button" class="quick-btn role-admin"
                                        onclick="accesoRapido('admin@admin.com', 'admin123')">
                                    <i class="fas fa-user-shield"></i> Admin
                                </button>
                            </div>
                            <div class="col-6">
                                <button type="button" class="quick-btn role-jefe"
                                        onclick="accesoRapido('jefefarmacia@farmacia.com', 'jefefarmacia123')">
                                    <i class="fas fa-user-tie"></i> Jefe Farmacia
                                </button>
                            </div>
                            <div class="col-6">
                                <div class="dropdown w-100">
                                    <button type="button"
                                            class="quick-btn role-doctor dropdown-toggle"
                                            data-bs-toggle="dropdown"
                                            aria-expanded="false">
                                        <i class="fas fa-user-md"></i> Doctor
                                    </button>
                                    <ul class="dropdown-menu w-100" style="border-radius: 12px; border: none; box-shadow: 0 8px 24px rgba(0,0,0,0.15); padding: 8px;">
                                        <?php if (count($doctores_demo) > 0): ?>
                                            <?php foreach ($doctores_demo as $doc): ?>
                                                <li>
                                                    <a class="dropdown-item rounded-3" href="#"
                                                       onclick="event.preventDefault(); accesoRapido('<?php echo htmlspecialchars($doc['email']); ?>', '<?php echo htmlspecialchars($doc['password']); ?>')">
                                                        <i class="fas fa-user-md me-2" style="color: #3b82f6;"></i>
                                                        <?php echo htmlspecialchars($doc['nombre']); ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li><span class="dropdown-item-text text-muted small">Sin doctores activos</span></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-6">
                                <button type="button" class="quick-btn role-farmacia"
                                        onclick="accesoRapido('farmacia@farmacia.com', 'farmacia123')">
                                    <i class="fas fa-user-nurse"></i> Farmacia
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i> Conexión segura y encriptada
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function accesoRapido(email, password) {
            document.getElementById('usuario').value = email;
            document.getElementById('password').value = password;
            document.getElementById('usuario').focus();
        }
    </script>
</body>
</html>