<?php
/**
 * Recebe o lead do formulário do site e envia por e-mail via PHPMailer.
 * Requer PHPMailer instalado via Composer (ver composer.json).
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/lib/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// -- Configuração --------------------------------------------------------
$env = load_env(__DIR__ . '/.env');

$config = [
    'smtp_host' => getenv(),
    'smtp_user' => 'contato@danielguedes.com.br',
    'smtp_pass' => getenv('SMTP_PASSWORD') ?: '',
    'smtp_port' => 465,
    'smtp_secure' => PHPMailer::ENCRYPTION_SMTPS,
    'to_email' => 'contato@danielguedes.com.br',
    'to_name' => 'Daniel Guedes',
    'from_email' => 'contato@danielguedes.com.br',
    'from_name' => 'Site Daniel Guedes',
    'min_fill_ms' => 5000, // trava de tempo mínimo de preenchimento
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

$nome = field($payload, 'nome', 120);
$whatsapp = field($payload, 'whatsapp', 30);
$instagram = field($payload, 'instagram', 60);
$categoria = field($payload, 'categoria', 120);
$investe = field($payload, 'investe', 20);
$valor = field($payload, 'valor', 60);

if ($nome === '' || $whatsapp === '' || $categoria === '') {
    respond(false, 'missing_required_fields', 422);
}

// -- Envio via PHPMailer ---------------------------------------------------

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $env['SMTP_HOST'] ?? '';
    $mail->SMTPAuth = true;
    $mail->Username = $env['SMTP_USER'] ?? '';
    $mail->Password = $env['SMTP_PASS'] ?? '';
    $mail->SMTPSecure = ($env['SMTP_SECURE'] ?? 'tls') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = (int) ($env['SMTP_PORT'] ?? 587);
    $mail->CharSet = 'UTF-8';

    $mail->setFrom($env['MAIL_FROM'] ?? $env['SMTP_USER'] ?? '', $env['MAIL_FROM_NAME'] ?? 'Site');
    $mail->addAddress($env['MAIL_TO'] ?? '', $env['MAIL_TO_NAME'] ?? '');

    $mail->addEmbeddedImage(
        __DIR__ . '/favicon.png',
        'site-favicon',
        'favicon.png',
        PHPMailer::ENCODING_BASE64,
        'image/png'
    );

    $escape = static fn (string $value): string => htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $nomeHtml = $escape($nome);
    $whatsappHtml = $escape($whatsapp);
    $instagramHtml = $escape($instagram !== '' ? $instagram : 'Não informado');
    $categoriaHtml = $escape($categoria);
    $investeHtml = $escape($investe !== '' ? $investe : 'Não informado');
    $valorHtml = $escape($valor !== '' ? $valor : 'Não informado');

    $whatsappDigits = preg_replace('/\D+/', '', $whatsapp) ?? '';
    if (strlen($whatsappDigits) === 10 || strlen($whatsappDigits) === 11) {
        $whatsappDigits = '55' . $whatsappDigits;
    }
    $whatsappUrl = $whatsappDigits !== ''
        ? 'https://wa.me/' . $whatsappDigits
        : 'https://wa.me/5511994084516';

    $mail->isHTML(true);
    $mail->Subject = 'Novo lead do site: ' . $nome;
    $mail->Body = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo lead do diagnóstico gratuito</title>
</head>
<body style="margin:0; padding:0; background-color:#07101d; color:#f4f2ec; font-family:'Open Sans',Arial,sans-serif;">
    <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
        Novo pedido de diagnóstico gratuito enviado por {$nomeHtml}.
    </div>
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#07101d;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%; max-width:600px; background-color:#0a1526; border:1px solid #263750; border-radius:20px; overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px; border-bottom:1px solid #263750; background-color:#0f1f38;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="56" valign="middle" style="width:56px;">
                                        <img src="cid:daniel-guedes-logo" width="48" height="48" alt="Daniel Guedes" style="display:block; width:48px; height:48px; border:0; border-radius:12px;">
                                    </td>
                                    <td valign="middle" style="padding-left:12px;">
                                        <p style="margin:0; color:#f4f2ec; font-family:Montserrat,Arial,sans-serif; font-size:18px; line-height:24px; font-weight:700;">Daniel Guedes</p>
                                        <p style="margin:2px 0 0; color:#a6b0c3; font-size:12px; line-height:18px;">Gestão de Tráfego Pago</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:36px 32px 18px;">
                            <p style="margin:0 0 10px; color:#b8935a; font-family:Montserrat,Arial,sans-serif; font-size:12px; line-height:18px; font-weight:700; letter-spacing:1.4px; text-transform:uppercase;">Novo contato pelo site</p>
                            <h1 style="margin:0 0 12px; color:#f4f2ec; font-family:Montserrat,Arial,sans-serif; font-size:27px; line-height:34px; font-weight:700;">Pedido de diagnóstico gratuito</h1>
                            <p style="margin:0; color:#a6b0c3; font-size:15px; line-height:24px;">Um novo lead preencheu o formulário. Os dados estão organizados abaixo para facilitar o atendimento.</p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:14px 32px 8px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#152a47; border:1px solid #304663; border-radius:14px;">
                                <tr>
                                    <td style="padding:22px 24px 8px; color:#b8935a; font-family:Montserrat,Arial,sans-serif; font-size:12px; line-height:18px; font-weight:700; letter-spacing:1px; text-transform:uppercase;">Dados do contato</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 24px 14px; border-bottom:1px solid #304663;">
                                        <p style="margin:0 0 4px; color:#a6b0c3; font-size:12px; line-height:18px;">Nome</p>
                                        <p style="margin:0; color:#f4f2ec; font-size:16px; line-height:24px; font-weight:700;">{$nomeHtml}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 24px; border-bottom:1px solid #304663;">
                                        <p style="margin:0 0 4px; color:#a6b0c3; font-size:12px; line-height:18px;">WhatsApp</p>
                                        <p style="margin:0; color:#f4f2ec; font-size:16px; line-height:24px; font-weight:700;">{$whatsappHtml}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 24px;">
                                        <p style="margin:0 0 4px; color:#a6b0c3; font-size:12px; line-height:18px;">Instagram profissional</p>
                                        <p style="margin:0; color:#f4f2ec; font-size:16px; line-height:24px; font-weight:700;">{$instagramHtml}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:8px 32px 14px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%; background-color:#f6f4ef; border-radius:14px;">
                                <tr>
                                    <td style="padding:22px 24px 8px; color:#8a6a35; font-family:Montserrat,Arial,sans-serif; font-size:12px; line-height:18px; font-weight:700; letter-spacing:1px; text-transform:uppercase;">Perfil do negócio</td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 24px 14px; border-bottom:1px solid #dedbd3;">
                                        <p style="margin:0 0 4px; color:#5b6270; font-size:12px; line-height:18px;">Negócio ou área de atuação</p>
                                        <p style="margin:0; color:#1b1d22; font-size:15px; line-height:23px; font-weight:700;">{$categoriaHtml}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 24px; border-bottom:1px solid #dedbd3;">
                                        <p style="margin:0 0 4px; color:#5b6270; font-size:12px; line-height:18px;">Já investe em tráfego pago?</p>
                                        <p style="margin:0; color:#1b1d22; font-size:15px; line-height:23px; font-weight:700;">{$investeHtml}</p>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:14px 24px 20px;">
                                        <p style="margin:0 0 4px; color:#5b6270; font-size:12px; line-height:18px;">Investimento mensal</p>
                                        <p style="margin:0; color:#1b1d22; font-size:15px; line-height:23px; font-weight:700;">{$valorHtml}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:14px 32px 36px;">
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td align="center" bgcolor="#b8935a" style="border-radius:12px;">
                                        <a href="{$whatsappUrl}" target="_blank" style="display:inline-block; padding:15px 26px; color:#12151d; font-family:Montserrat,Arial,sans-serif; font-size:14px; line-height:20px; font-weight:700; text-decoration:none;">Chamar no WhatsApp</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:20px 32px; border-top:1px solid #263750; background-color:#0f1f38;">
                            <p style="margin:0; color:#7f8ca3; font-size:11px; line-height:18px;">Mensagem automática enviada pelo formulário de odanielguedes.com.br</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    $mail->AltBody = "NOVO LEAD DO DIAGNÓSTICO GRATUITO\n\n"
        . "Nome: {$nome}\n"
        . "WhatsApp: {$whatsapp}\n"
        . 'Instagram: ' . ($instagram !== '' ? $instagram : 'Não informado') . "\n"
        . "Categoria: {$categoria}\n"
        . 'Já investe em tráfego: ' . ($investe !== '' ? $investe : 'Não informado') . "\n"
        . 'Investimento mensal: ' . ($valor !== '' ? $valor : 'Não informado') . "\n\n"
        . "Chamar no WhatsApp: {$whatsappUrl}";

    $mail->send();
    respond(true, 'Solicitação enviada com sucesso. Em breve entraremos em contato.');
} catch (Exception $e) {
    $errorInfo = isset($mail) ? $mail->ErrorInfo : $e->getMessage();
    error_log('Falha ao enviar e-mail de contato: ' . $errorInfo);

    respond(false, 'Erro: ' . $errorInfo, 500);
    // respond(false, 'Não foi possível enviar sua solicitação agora. Tente novamente mais tarde.', 500);
}
