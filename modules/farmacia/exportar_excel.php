<?php

require_once '../../vendor/autoload.php';
require_once '../../config/database.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Reporte Recetas');
$sheet->mergeCells('A1:E1');
$sheet->setCellValue('A1', 'REPORTE GENERAL DE RECETAS MÉDICAS');
$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'name' => 'Arial',
        'size' => 16,
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '1F4E78'] 
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER
    ]
]);
$sheet->getRowDimension(1)->setRowHeight(30);
$sheet->mergeCells('A2:E2');
$sheet->setCellValue('A2', 'Del ' . ($fecha_inicio ? date('d/m/Y', strtotime($fecha_inicio)) : 'Inicio') . ' al ' . ($fecha_fin ? date('d/m/Y', strtotime($fecha_fin)) : 'Actualidad'));
$sheet->getStyle('A2')->applyFromArray([
    'font' => [
        'name' => 'Arial',
        'size' => 11,
        'italic' => true,
        'color' => ['rgb' => '404040']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER
    ]
]);
$sheet->getRowDimension(2)->setRowHeight(20);
$encabezados = ['ID', 'Paciente', 'Doctor', 'Estado', 'Fecha'];
$columna = 'A';
foreach ($encabezados as $encabezado) {
    $sheet->setCellValue($columna . '4', $encabezado);
    $sheet->getStyle($columna . '4')->applyFromArray([
        'font' => [
            'name' => 'Arial',
            'size' => 12,
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '2E75B6'] 
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'FFFFFF']
            ]
        ]
    ]);
    $columna++;
}
$sheet->getRowDimension(4)->setRowHeight(22);
$sql = "
SELECT
    r.id,
    CONCAT(p.nombre,' ',p.apellido) AS paciente,
    u.nombre AS doctor,
    r.estado,
    r.created_at
FROM recetas r
INNER JOIN pacientes p
    ON r.paciente_id = p.id
INNER JOIN usuarios u
    ON r.doctor_id = u.id
";

if(!empty($fecha_inicio) && !empty($fecha_fin)){
    $sql .= " WHERE DATE(r.created_at) BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}

$sql .= " ORDER BY r.created_at DESC";

$resultado = $conn->query($sql);

if (!$resultado) {
    die("Error en la consulta SQL: " . $conn->error);
}
$fila = 5; 

while($row = $resultado->fetch_assoc()){
    $color_estado = match($row['estado']) {
        'pendiente' => 'FFC000',   
        'aprobada' => '00B050',    
        'entregada' => '0070C0',   
        'rechazada' => 'FF0000',   
        default => '808080'        
    };
    
    $sheet->setCellValue('A'.$fila, $row['id']);
    $sheet->setCellValue('B'.$fila, $row['paciente']);
    $sheet->setCellValue('C'.$fila, $row['doctor']);
    $sheet->setCellValue('D'.$fila, $row['estado']);
    $sheet->setCellValue('E'.$fila, $row['created_at']);
    
    $sheet->getStyle('D'.$fila)->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => $color_estado]
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F2F2F2'] 
        ]
    ]);
    
    $sheet->getStyle('A'.$fila.':E'.$fila)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => 'D9D9D9'] 
            ]
        ],
        'alignment' => [
            'vertical' => Alignment::VERTICAL_CENTER
        ]
    ]);
    
    $fila++;
}

foreach(range('A','E') as $col){
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

for ($i = 5; $i < $fila; $i++) {
    $sheet->getRowDimension($i)->setRowHeight(18);
}
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="Reporte_Recetas_' . date('Y-m-d') . '.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;