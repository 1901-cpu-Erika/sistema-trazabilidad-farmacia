<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('Acceso denegado');
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'farmacia_trazabilidad';
$backup_dir = 'C:\\xampp\\backups_farmacia\\';
$mysqldump = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
$openssl = 'C:\\xampp\\apache\\bin\\openssl.exe';
$passphrase = 'FarmaciaTrazabilidad2026Segura';
if (!is_dir($backup_dir)) {
    if (!mkdir($backup_dir, 0750, true)) {
        die("No se pudo crear la carpeta de respaldos: $backup_dir");
    }
}
$timestamp = date('Y-m-d_H-i-s');
$backup_file = $backup_dir . $db_name . '_' . $timestamp . '.sql';
$encrypted_file = $backup_file . '.enc';
$command = "\"{$mysqldump}\" --user={$db_user} --password={$db_pass} --host={$db_host} --single-transaction --routines --triggers {$db_name} > \"{$backup_file}\"";
exec($command, $output, $return_var);

if ($return_var !== 0 || !file_exists($backup_file) || filesize($backup_file) === 0) {
    $log = date('Y-m-d H:i:s') . " | ERROR | Falló el respaldo con mysqldump (código: $return_var)\n";
    file_put_contents($backup_dir . 'backup.log', $log, FILE_APPEND);
    die("ERROR al respaldar. Revisa el log.");
}

$tamaño_original = filesize($backup_file);
$encrypt_command = "\"{$openssl}\" enc -aes-256-cbc -pbkdf2 -salt -in \"{$backup_file}\" -out \"{$encrypted_file}\" -pass pass:{$passphrase}";
exec($encrypt_command, $output_enc, $return_var_enc);

if ($return_var_enc !== 0 || !file_exists($encrypted_file) || filesize($encrypted_file) === 0) {
    $log = date('Y-m-d H:i:s') . " | ERROR | Falló la encriptación (código: $return_var_enc)\n";
    file_put_contents($backup_dir . 'backup.log', $log, FILE_APPEND);
    unlink($backup_file);
    die("ERROR al encriptar. Revisa el log.");
}
unlink($backup_file);
$tamaño_enc = filesize($encrypted_file);
$log = date('Y-m-d H:i:s') . " | OK | " . basename($encrypted_file) . " | Original: " . round($tamaño_original / 1024, 2) . " KB | Encriptado: " . round($tamaño_enc / 1024, 2) . " KB\n";
file_put_contents($backup_dir . 'backup.log', $log, FILE_APPEND);

echo "OK - Respaldo encriptado exitosamente: " . basename($encrypted_file) . "\n";
echo "Tamaño: " . round($tamaño_enc / 1024, 2) . " KB\n";

$files = glob($backup_dir . '*.enc');
$now = time();
$eliminados = 0;
foreach ($files as $file) {
    if (is_file($file) && ($now - filemtime($file)) > (30 * 24 * 60 * 60)) {
        unlink($file);
        $eliminados++;
    }
}

if ($eliminados > 0) {
    echo "Respaldos antiguos eliminados: $eliminados\n";
}