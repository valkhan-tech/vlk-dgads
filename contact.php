<?php
/**
 * Recebe o lead do formulário do site e envia por e-mail via PHPMailer.
 * Requer PHPMailer instalado via Composer (ver composer.json).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// -- Configuração --------------------------------------------------------
$config = [
    'smtp_host'       => 'smtp.hostinger.com',
    'smtp_user'       => 'contato@danielguedes.com.br',
    'smtp_pass'       => getenv('SMTP_PASSWORD') ?: '',
    'smtp_port'       => 465,
    'smtp_secure'     => PHPMailer::ENCRYPTION_SMTPS,
    'to_email'        => 'contato@danielguedes.com.br',
    'to_name'         => 'Daniel Guedes',
    'from_email'      => 'contato@danielguedes.com.br',
    'from_name'       => 'Site Daniel Guedes',
    'min_fill_ms'     => 5000, // trava de tempo mínimo de preenchimento
];

function respond(bool $ok, string $message = '', int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['ok' => $ok, 'error' => $ok ? null : $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'method_not_allowed', 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    respond(false, 'invalid_payload', 400);
}

// -- Trava de tempo (revalidada no servidor) ------------------------------
// Mesmo controle aplicado no cliente: preenchimento abaixo do tempo mínimo
// é tratado como bot e recebe uma resposta de sucesso vazia (sem envio real).
$loadedAt = isset($payload['loaded_at']) ? (float) $payload['loaded_at'] : 0;
$elapsedMs = (microtime(true) * 1000) - $loadedAt;
if ($loadedAt <= 0 || $elapsedMs < $config['min_fill_ms']) {
    respond(true); // sucesso falso: não revela a proteção ao robô
}

// -- Sanitização e validação dos campos -----------------------------------
function field(array $payload, string $key, int $maxLen = 255): string
{
    $value = isset($payload[$key]) ? trim((string) $payload[$key]) : '';
    $value = strip_tags($value);
    return mb_substr($value, 0, $maxLen);
}

$nome      = field($payload, 'nome', 120);
$whatsapp  = field($payload, 'whatsapp', 30);
$instagram = field($payload, 'instagram', 60);
$categoria = field($payload, 'categoria', 120);
$investe   = field($payload, 'investe', 20);
$valor     = field($payload, 'valor', 60);

if ($nome === '' || $whatsapp === '' || $categoria === '') {
    respond(false, 'missing_required_fields', 422);
}

// -- Envio via PHPMailer ---------------------------------------------------
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $config['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $config['smtp_user'];
    $mail->Password   = $config['smtp_pass'];
    $mail->SMTPSecure = $config['smtp_secure'];
    $mail->Port       = $config['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom($config['from_email'], $config['from_name']);
    $mail->addAddress($config['to_email'], $config['to_name']);
    $mail->addReplyTo($config['from_email'], $nome);

    $mail->isHTML(true);
    $mail->Subject = 'Novo lead do site: ' . $nome;
    $mail->Body = '
        <h2>Novo lead do diagnóstico gratuito</h2>
        <p><strong>Nome:</strong> ' . htmlspecialchars($nome) . '</p>
        <p><strong>WhatsApp:</strong> ' . htmlspecialchars($whatsapp) . '</p>
        <p><strong>Instagram:</strong> ' . htmlspecialchars($instagram ?: 'não informado') . '</p>
        <p><strong>Categoria:</strong> ' . htmlspecialchars($categoria) . '</p>
        <p><strong>Já investe em tráfego:</strong> ' . htmlspecialchars($investe) . '</p>
        <p><strong>Investimento mensal:</strong> ' . htmlspecialchars($valor) . '</p>
    ';
    $mail->AltBody = "Nome: $nome\nWhatsApp: $whatsapp\nInstagram: $instagram\nCategoria: $categoria\nJá investe: $investe\nInvestimento: $valor";

    $mail->send();
    respond(true);
} catch (Exception $e) {
    respond(false, 'mail_send_failed', 500);
}
