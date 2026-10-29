<?php
function enviarSMSPaciente($telefono, $mensaje) {
    
    $sid    = "AC00ae9741281b14b4fee86ef442f14397";
    $token  = "c379aef956c982cb4b56881a53804c20"; 
    $url    = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
    
    $data = [
        'To' => $telefono,
        'MessagingServiceSid' => 'MG424caaf4a7db8458db4b79b9619c580a',
        'Body' => $mensaje
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error) {
        return json_encode(['status' => 'danger', 'msg' => 'Error de conexión: ' . $error]);
    }
    
    return json_encode(['status' => 'success', 'msg' => 'Notificación enviada correctamente.']);
}
?>