<?php
if (session_status() === PHP_SESSION_NONE) {
   session_save_path('/tmp'); // Esto es clave para Vercel
    session_start();
}

date_default_timezone_set('America/Managua');
require_once '../../config/database.php';

$titulo_pagina = "Reportes del Personal";

$lista_doctores = [];
$sql_todos = "SELECT nombre, estado FROM usuarios WHERE tipo = 'doctor' ORDER BY nombre ASC";
$res_todos = $conn->query($sql_todos);

if ($res_todos) {
    while ($fila = $res_todos->fetch_assoc()) {
        $lista_doctores[] = $fila;
    }
}

$doctores_activos = 0;
$doctores_inactivos = 0;

$sql_estados = "
    SELECT estado, COUNT(*) AS total
    FROM usuarios
    WHERE tipo = 'doctor'
    GROUP BY estado
";

$resultado_estados = $conn->query($sql_estados);

if ($resultado_estados) {
    while ($fila = $resultado_estados->fetch_assoc()) {
        if ($fila['estado'] === 'activo') {
            $doctores_activos = (int) $fila['total'];
        } elseif ($fila['estado'] === 'inactivo') {
            $doctores_inactivos = (int) $fila['total'];
        }
    }
}

$total_doctores = $doctores_activos + $doctores_inactivos;

$ranking_doctores = [];

$sql_ranking = "
    SELECT
        u.id,
        u.nombre,
        COUNT(r.doctor_id) AS total_recetas
    FROM usuarios u
    LEFT JOIN recetas r ON r.doctor_id = u.id
    WHERE u.tipo = 'doctor'
    GROUP BY u.id, u.nombre
    ORDER BY total_recetas DESC, u.nombre ASC
";

$resultado_ranking = $conn->query($sql_ranking);

if ($resultado_ranking) {
    while ($fila = $resultado_ranking->fetch_assoc()) {
        $ranking_doctores[] = [
            'id' => (int) $fila['id'],
            'nombre' => $fila['nombre'],
            'recetas' => (int) $fila['total_recetas']
        ];
    }
}

$doctor_destacado = $ranking_doctores[0] ?? [
    'nombre' => 'Sin doctores registrados',
    'recetas' => 0
];

$nombres_doctores = [];
$cantidades_recetas = [];

foreach ($ranking_doctores as $doctor) {
    $nombres_doctores[] = $doctor['nombre'];
    $cantidades_recetas[] = $doctor['recetas'];
}

require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
.reportes-personal {
    padding: 24px 32px;
    width: 100%;
    max-width: 100%;
    min-height: 100vh;
    background: #f5f6fa;
    color: #1e293b;
    box-sizing: border-box;
    overflow-x: hidden;
}

.reportes-personal * {
    box-sizing: border-box;
}

.rp-banner {
    background: linear-gradient(135deg, #065499, #991b1b);
    border-radius: 20px;
    padding: 30px 35px;
    margin-bottom: 30px;
    color: #ffffff;
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 25px rgba(220, 38, 38, 0.2);
}

.rp-banner::before {
    content: '';
    position: absolute;
    right: -50px;
    top: -50px;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    pointer-events: none;
}

.rp-banner::after {
    content: '';
    position: absolute;
    right: 50px;
    bottom: -80px;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    pointer-events: none;
}

.rp-banner-contenido {
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    z-index: 2;
    width: 100%;
}

.rp-banner-icono {
    width: 75px;
    height: 75px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}

.rp-banner-texto h1 {
    margin: 0 0 8px 0;
    font-size: 28px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
}

.rp-banner-texto p {
    margin: 0 0 12px 0;
    font-size: 15px;
    color: rgba(255, 255, 255, 0.9);
    display: flex;
    align-items: center;
    gap: 8px;
}

.rp-banner-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255, 255, 255, 0.2);
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.rp-resumen {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.rp-card {
    position: relative;
    min-width: 0;
    padding: 26px;
    background: #fff;
    border: 1px solid #e8eaf0;
    border-radius: 18px;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .04);
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}

.rp-card-clickable {
    cursor: pointer;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.rp-card-clickable:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 25px rgba(15, 23, 42, .08);
    border-color: #dc2626; /* Borde rojo al pasar el mouse */
}

.rp-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
}

.rp-card-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.rp-card-icon {
    width: 52px;
    height: 52px;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 14px;
    font-size: 22px;
}

.rp-verde { background: #dcfce7; color: #15803d; }
.rp-rojo { background: #fee2e2; color: #b91c1c; }
.rp-azul { background: #dbeafe; color: #1d4ed8; }

.rp-card-numero {
    margin: 20px 0 8px;
    color: #172554;
    font-size: 40px;
    line-height: 1.1;
    font-weight: 800;
}

.rp-card-descripcion {
    margin: 0;
    font-size: 14px;
    color: #64748b;
}

.rp-card-doctor {
    margin-top: 16px;
    color: #b91c1c;
    font-size: 18px;
    font-weight: 800;
    overflow-wrap: anywhere;
}

.rp-card-recetas {
    margin-top: 8px;
    color: #64748b;
    font-size: 14px;
}

/* --- PANELES DE GRÁFICOS --- */
.rp-graficos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 24px;
    margin-bottom: 30px;
}

.rp-panel {
    min-width: 0;
    background: #fff;
    border: 1px solid #e8eaf0;
    border-radius: 18px;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(15, 23, 42, .04);
}

.rp-panel-header {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 24px 26px;
    border-bottom: 1px solid #edf0f5;
}

.rp-panel-icon {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
    align-items: center;
    border-radius: 14px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 20px;
}

.rp-panel-header h2 {
    margin: 0 0 6px;
    color: #172554;
    font-size: 18px;
    font-weight: 800;
}

.rp-panel-header p {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.rp-grafico-contenido {
    padding: 26px;
}

.rp-grafico {
    position: relative;
    width: 100%;
    height: 310px;
}

.rp-panel-ranking {
    grid-column: 1 / -1;
}

.rp-panel-ranking .rp-grafico {
    height: 380px;
}

/* --- TABLA --- */
.rp-tabla-contenedor {
    overflow-x: auto;
}

.rp-tabla {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

.rp-tabla thead {
    background: #f8fafc;
}

.rp-tabla th {
    padding: 16px 26px;
    color: #64748b;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
}

.rp-tabla td {
    padding: 16px 26px;
    border-top: 1px solid #edf0f5;
    color: #334155;
    font-size: 14px;
}

.rp-tabla tbody tr:hover {
    background: #fff8f8;
}

.rp-doctor-nombre {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 700;
}

.rp-doctor-avatar {
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #fee2e2;
    color: #b91c1c;
    font-size: 18px;
}

.rp-badge-recetas {
    display: inline-block;
    min-width: 45px;
    text-align: center;
    padding: 8px 14px;
    border-radius: 10px;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 800;
}

.rp-badge-primero {
    background: #fef3c7;
    color: #92400e;
}

.rp-pie {
    padding: 16px 26px;
    background: #fafbfe;
    border-top: 1px solid #edf0f5;
    color: #64748b;
    font-size: 13px;
}

.rp-vacio {
    padding: 30px;
    text-align: center;
    color: #64748b;
}

.rp-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.rp-modal-overlay.active {
    display: flex;
}

.rp-modal {
    background: #ffffff;
    width: 100%;
    max-width: 550px;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    animation: modalIn 0.3s ease-out;
    display: flex;
    flex-direction: column;
    max-height: 85vh;
}

@keyframes modalIn {
    from { opacity: 0; transform: translateY(30px) scale(0.95); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.rp-modal-header {
    padding: 20px 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #edf0f5;
}

.rp-modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: #172554;
    display: flex;
    align-items: center;
    gap: 10px;
}

.rp-modal-close {
    background: #f1f5f9;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.rp-modal-close:hover {
    background: #fee2e2;
    color: #b91c1c;
}

.rp-modal-body {
    padding: 12px 24px 24px;
    overflow-y: auto;
}

.rp-modal-lista {
    list-style: none;
    padding: 0;
    margin: 0;
}

.rp-modal-lista li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 15px;
    color: #334155;
    font-weight: 600;
}

.rp-modal-lista li:last-child {
    border-bottom: none;
}

.rp-status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
}

.status-activo { background: #dcfce7; color: #15803d; }
.status-inactivo { background: #fee2e2; color: #b91c1c; }

@media (max-width: 1100px) {
    .rp-resumen { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .rp-graficos { grid-template-columns: 1fr; }
    .rp-panel-ranking { grid-column: auto; }
}

@media (max-width: 768px) {
    .reportes-personal { padding: 16px; }
    .rp-banner { padding: 24px; flex-direction: column; text-align: center; }
    .rp-banner-contenido { flex-direction: column; gap: 16px; }
    .rp-banner-texto p { justify-content: center; }
    .rp-resumen { grid-template-columns: 1fr; gap: 16px; }
    .rp-grafico { height: 260px; }
    .rp-panel-ranking .rp-grafico { height: 300px; }
}
</style>

<main class="reportes-personal">

    <div class="rp-banner">
        <div class="rp-banner-contenido">
            <div class="rp-banner-icono">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="rp-banner-texto">
                <h1>Reportes del Personal</h1>
                <p><i class="fas fa-users-cog"></i> Estado y productividad de los doctores del sistema</p>
                <span class="rp-banner-badge">
                    <i class="fas fa-shield-alt"></i> ADMINISTRADOR DE PERSONAL
                </span>
            </div>
        </div>
    </div>

    <section class="rp-resumen">

        <article class="rp-card rp-card-clickable" onclick="abrirModal('activo')">
            <div class="rp-card-top">
                <span class="rp-card-label">Doctores activos</span>
                <div class="rp-card-icon rp-verde">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            <div class="rp-card-numero">
                <?= number_format($doctores_activos) ?>
            </div>
            <p class="rp-card-descripcion">
                Doctores habilitados para utilizar el sistema
            </p>
        </article>

        <article class="rp-card rp-card-clickable" onclick="abrirModal('inactivo')">
            <div class="rp-card-top">
                <span class="rp-card-label">Doctores inactivos</span>
                <div class="rp-card-icon rp-rojo">
                    <i class="fas fa-user-times"></i>
                </div>
            </div>
            <div class="rp-card-numero">
                <?= number_format($doctores_inactivos) ?>
            </div>
            <p class="rp-card-descripcion">
                Doctores con cuentas deshabilitadas
            </p>
        </article>

        <article class="rp-card">
            <div class="rp-card-top">
                <span class="rp-card-label">Doctor con más recetas</span>
                <div class="rp-card-icon rp-azul">
                    <i class="fas fa-prescription"></i>
                </div>
            </div>

            <div class="rp-card-doctor">
                <?= htmlspecialchars($doctor_destacado['nombre'], ENT_QUOTES, 'UTF-8') ?>
            </div>

            <div class="rp-card-recetas">
                <strong><?= number_format($doctor_destacado['recetas']) ?></strong>
                recetas registradas
            </div>
        </article>

    </section>

    <section class="rp-graficos">
        <article class="rp-panel">
            <div class="rp-panel-header">
                <div class="rp-panel-icon">
                    <i class="fas fa-user-doctor"></i>
                </div>
                <div>
                    <h2>Estado de los doctores</h2>
                    <p>Comparación de cuentas activas e inactivas.</p>
                </div>
            </div>
            <div class="rp-grafico-contenido">
                <div class="rp-grafico">
                    <canvas id="graficoEstados"></canvas>
                </div>
            </div>
            <div class="rp-pie">
                <i class="fas fa-info-circle"></i> Verde: activos. Rojo: inactivos.
            </div>
        </article>
        <article class="rp-panel">
            <div class="rp-panel-header">
                <div class="rp-panel-icon">
                    <i class="fas fa-prescription-bottle-medical"></i>
                </div>
                <div>
                    <h2>Doctor con más recetas</h2>
                    <p>Comparación de las recetas registradas por doctor.</p>
                </div>
            </div>
            <div class="rp-grafico-contenido">
                <div class="rp-grafico">
                    <canvas id="graficoDestacado"></canvas>
                </div>
            </div>
            <div class="rp-pie">
                <i class="fas fa-info-circle"></i> Se cuentan las recetas registradas en la tabla recetas.
            </div>
        </article>

        <article class="rp-panel rp-panel-ranking">
            <div class="rp-panel-header">
                <div class="rp-panel-icon">
                    <i class="fas fa-ranking-star"></i>
                </div>
                <div>
                    <h2>Ranking de recetas por doctor</h2>
                    <p>Comparación de la cantidad total de recetas registradas.</p>
                </div>
            </div>
            <div class="rp-grafico-contenido">
                <?php if (count($ranking_doctores) > 0): ?>
                    <div class="rp-grafico">
                        <canvas id="graficoRanking"></canvas>
                    </div>
                <?php else: ?>
                    <div class="rp-vacio">
                        <i class="fas fa-user-md fa-2x"></i>
                        <p>No hay doctores registrados para mostrar.</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rp-tabla-contenedor">
                <table class="rp-tabla">
                    <thead>
                        <tr>
                            <th>Posición</th>
                            <th>Doctor</th>
                            <th style="text-align:center">Total de recetas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ranking_doctores) > 0): ?>
                            <?php foreach ($ranking_doctores as $indice => $doctor): ?>
                                <tr>
                                    <td>
                                        <?php if ($indice === 0 && $doctor['recetas'] > 0): ?>
                                            <span class="rp-badge-recetas rp-badge-primero">
                                                <i class="fas fa-trophy"></i> 1.º
                                            </span>
                                        <?php else: ?>
                                            <span class="rp-badge-recetas">
                                                <?= $indice + 1 ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="rp-doctor-nombre">
                                            <div class="rp-doctor-avatar">
                                                <i class="fas fa-user-md"></i>
                                            </div>
                                            <?= htmlspecialchars($doctor['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td style="text-align:center">
                                        <span class="rp-badge-recetas">
                                            <?= number_format($doctor['recetas']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="rp-vacio">
                                    No hay datos disponibles.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="rp-pie">
                <i class="fas fa-database"></i>
                Información calculada a partir de los registros de usuarios y recetas.
            </div>
        </article>

    </section>

</main>
<div class="rp-modal-overlay" id="modalDoctores">
    <div class="rp-modal">
        <div class="rp-modal-header">
            <h3 id="modalTitulo"><i class="fas fa-users"></i> Lista de Doctores</h3>
            <button class="rp-modal-close" onclick="cerrarModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="rp-modal-body">
            <ul class="rp-modal-lista" id="modalLista">
            </ul>
        </div>
    </div>
</div>

<script>
const doctoresActivos = <?= $doctores_activos ?>;
const doctoresInactivos = <?= $doctores_inactivos ?>;

const listaDoctores = <?= json_encode($lista_doctores, JSON_UNESCAPED_UNICODE) ?>;

const nombresDoctores = <?= json_encode($nombres_doctores, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const cantidadesRecetas = <?= json_encode($cantidades_recetas) ?>;
const nombreDoctorDestacado = <?= json_encode($doctor_destacado['nombre'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
const recetasDoctorDestacado = <?= (int) $doctor_destacado['recetas'] ?>;

function abrirModal(estado) {
    const modal = document.getElementById('modalDoctores');
    const listaContenedor = document.getElementById('modalLista');
    const titulo = document.getElementById('modalTitulo');

    const filtrados = listaDoctores.filter(doc => doc.estado.toLowerCase() === estado.toLowerCase());
    
    if (estado === 'activo') {
        titulo.innerHTML = '<i class="fas fa-user-check" style="color:#15803d"></i> Doctores Activos';
    } else {
        titulo.innerHTML = '<i class="fas fa-user-times" style="color:#b91c1c"></i> Doctores Inactivos';
    }

    if (filtrados.length === 0) {
        listaContenedor.innerHTML = '<li style="justify-content:center; color:#64748b;">No hay doctores en este estado.</li>';
    } else {
        listaContenedor.innerHTML = filtrados.map(doc => `
            <li>
                <span><i class="fas fa-user-md" style="color:#cbd5e1; margin-right:8px;"></i> ${doc.nombre}</span>
                <span class="rp-status-badge status-${doc.estado.toLowerCase()}">${doc.estado}</span>
            </li>
        `).join('');
    }

    modal.classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalDoctores').classList.remove('active');
}

document.getElementById('modalDoctores').addEventListener('click', function(e) {
    if (e.target === this) {
        cerrarModal();
    }
});

Chart.defaults.font.family = "'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
Chart.defaults.color = '#64748b';

new Chart(document.getElementById('graficoEstados'), {
    type: 'bar',
    data: {
        labels: ['Activos', 'Inactivos'],
        datasets: [{
            label: 'Cantidad de doctores',
            data: [doctoresActivos, doctoresInactivos],
            backgroundColor: ['#16a34a', '#dc2626'],
            borderRadius: 10,
            borderSkipped: false,
            maxBarThickness: 80
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: function(context) { return ' ' + context.raw + ' doctor(es)'; }
                }
            }
        },
        scales: {
            y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf0f5' } },
            x: { grid: { display: false } }
        }
    }
});

new Chart(document.getElementById('graficoDestacado'), {
    type: 'doughnut',
    data: {
        labels: [nombreDoctorDestacado, 'Resto de doctores'],
        datasets: [{
            data: [
                recetasDoctorDestacado,
                Math.max(0, cantidadesRecetas.reduce((a, b) => a + b, 0) - recetasDoctorDestacado)
            ],
            backgroundColor: ['#24b7dc', '#e2e8f0'],
            borderColor: ['#ffffff', '#ffffff'],
            borderWidth: 4,
            hoverOffset: 8
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        cutout: '70%',
        plugins: {
            legend: { position: 'bottom', labels: { padding: 20 } },
            tooltip: {
                callbacks: {
                    label: function(context) { return ' ' + context.label + ': ' + context.raw + ' receta(s)'; }
                }
            }
        }
    }
});

const canvasRanking = document.getElementById('graficoRanking');
if (canvasRanking && nombresDoctores.length > 0) {
    new Chart(canvasRanking, {
        type: 'bar',
        data: {
            labels: nombresDoctores,
            datasets: [{
                label: 'Recetas registradas',
                data: cantidadesRecetas,
                backgroundColor: nombresDoctores.map((nombre, indice) => {
                    return indice === 0 && cantidadesRecetas[0] > 0 ? '#dc2626' : '#94a3b8';
                }),
                borderRadius: 8,
                borderSkipped: false,
                maxBarThickness: 35
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) { return ' ' + context.raw + ' receta(s)'; }
                    }
                }
            },
            scales: {
                x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: '#edf0f5' } },
                y: { grid: { display: false } }
            }
        }
    });
}
</script>

