<?php
require_once '../../config/database.php';
$id = intval($_GET['id']);
$sql = "SELECT m.nombre, rd.cantidad, rd.dosis FROM receta_detalles rd 
        JOIN medicamentos m ON rd.medicamento_id = m.id WHERE rd.receta_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
?>
<style>
    .receta-container {
        max-width: 700px;
        margin: 20px auto;
        background: #ffffff;
        border-radius: 24px;
        padding: 32px;
        box-shadow: 0 20px 50px rgba(124, 58, 237, 0.12);
        border: 1px solid #ede9fe;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        position: relative;
        overflow: hidden;
    }

    .receta-container::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 6px;
        background: linear-gradient(90deg, #4c1d95, #7c3aed, #a78bfa);
    }

    .receta-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding-bottom: 24px;
        border-bottom: 2px dashed #e5e7eb;
        margin-bottom: 28px;
    }

    .receta-header-content {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .receta-header-icon {
        width: 60px;
        height: 60px;
        border-radius: 18px;
        background: linear-gradient(135deg, #4c1d95, #7c3aed);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 1.6rem;
        box-shadow: 0 8px 20px rgba(124, 58, 237, 0.35);
        flex-shrink: 0;
    }

    .receta-header h3 {
        margin: 0 0 4px;
        color: #1e1b4b;
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.3px;
    }

    .receta-header p {
        margin: 0;
        color: #7c3aed;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .receta-badge {
        background: #f3e8ff;
        color: #6d28d9;
        padding: 8px 16px;
        border-radius: 50px;
        font-weight: 700;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: 2px solid #ddd6fe;
    }

    .med-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .med-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafbfc;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 18px 20px;
        margin-bottom: 14px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .med-item::before {
        content: '';
        position: absolute;
        left: 0; top: 0; bottom: 0;
        width: 4px;
        background: linear-gradient(135deg, #7c3aed, #a78bfa);
        transition: width 0.3s;
    }

    .med-item:hover {
        transform: translateY(-3px);
        background: #ffffff;
        border-color: #ddd6fe;
        box-shadow: 0 12px 28px rgba(124, 58, 237, 0.15);
    }

    .med-item:hover::before {
        width: 8px;
    }

    .med-info {
        display: flex;
        align-items: center;
        gap: 16px;
        flex: 1;
        min-width: 0;
    }

    .med-icon {
        width: 50px;
        height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, #7c3aed, #a78bfa);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
        flex-shrink: 0;
        box-shadow: 0 6px 16px rgba(124, 58, 237, 0.3);
        transition: transform 0.3s;
    }

    .med-item:hover .med-icon {
        transform: scale(1.08) rotate(-5deg);
    }

    .med-content {
        flex: 1;
        min-width: 0;
    }

    .med-name {
        font-weight: 700;
        color: #1e1b4b;
        font-size: 1rem;
        margin-bottom: 8px;
        letter-spacing: -0.2px;
    }

    .med-details {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .med-detail-tag {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 50px;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .med-detail-tag.tag-cantidad {
        background: #dbeafe;
        color: #1e40af;
    }

    .med-detail-tag.tag-dosis {
        background: #fef3c7;
        color: #92400e;
    }

    .med-detail-tag i {
        font-size: 0.75rem;
    }

    .med-detail-tag strong {
        font-weight: 700;
    }

    .empty-state {
        text-align: center;
        padding: 50px 20px;
    }

    .empty-state-icon {
        width: 90px;
        height: 90px;
        margin: 0 auto 20px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f3e8ff, #ede9fe);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #a78bfa;
        font-size: 2.5rem;
    }

    .empty-state h5 {
        color: #475569;
        font-weight: 700;
        margin-bottom: 6px;
        font-size: 1.05rem;
    }

    .empty-state p {
        color: #94a3b8;
        font-size: 0.88rem;
        margin: 0;
    }

    .receta-footer {
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.78rem;
        color: #94a3b8;
    }

    .receta-footer span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    @media (max-width: 500px) {
        .receta-container {
            padding: 22px;
            margin: 10px;
        }
        .receta-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 14px;
        }
        .med-item {
            flex-direction: column;
            align-items: flex-start;
        }
        .med-info {
            width: 100%;
        }
    }
</style>

<div class="receta-container">
    <div class="receta-header">
        <div class="receta-header-content">
            <div class="receta-header-icon">
                <i class="fas fa-prescription-bottle-medical"></i>
            </div>
            <div>
                <h3>Detalle de Medicamentos</h3>
                <p><i class="fas fa-hashtag me-1"></i>Receta N° <?php echo $id; ?></p>
            </div>
        </div>
        <span class="receta-badge">
            <i class="fas fa-pills"></i>
            <?php echo $res->num_rows; ?> medicamento<?php echo $res->num_rows != 1 ? 's' : ''; ?>
        </span>
    </div>

    <?php if ($res->num_rows > 0): ?>
        <ul class="med-list">
            <?php while($row = $res->fetch_assoc()): ?>
                <li class="med-item">
                    <div class="med-info">
                        <div class="med-icon">
                            <i class="fas fa-capsules"></i>
                        </div>
                        <div class="med-content">
                            <div class="med-name"><?php echo htmlspecialchars($row['nombre']); ?></div>
                            <div class="med-details">
                                <span class="med-detail-tag tag-cantidad">
                                    <i class="fas fa-box"></i>
                                    Cantidad: <strong><?php echo htmlspecialchars($row['cantidad']); ?></strong>
                                </span>
                                <span class="med-detail-tag tag-dosis">
                                    <i class="fas fa-clock"></i>
                                    Dosis: <strong><?php echo htmlspecialchars($row['dosis'] ?: 'No especificada'); ?></strong>
                                </span>
                            </div>
                        </div>
                    </div>
                </li>
            <?php endwhile; ?>
        </ul>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-box-open"></i>
            </div>
            <h5>No hay medicamentos</h5>
            <p>Esta receta no tiene medicamentos registrados</p>
        </div>
    <?php endif; ?>

    <div class="receta-footer">
        <span><i class="fas fa-shield-alt"></i> Sistema de Trazabilidad</span>
        <span><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y'); ?></span>
    </div>
</div>