<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envío de correo sin dependencias. Arma un mensaje MIME (texto + HTML) y lo entrega según MAIL_DRIVER:
 *  - smtp:        cliente SMTP con STARTTLS (587) o SSL (465), autenticación LOGIN y verificación de certificado.
 *  - archivo:     guarda el .eml en storage/mail (desarrollo y pruebas; no sale nada a internet).
 *  - desactivado: no envía.
 * Las credenciales viven en .env; los errores nunca incluyen la contraseña.
 */
final class Mailer
{
    private const TIMEOUT = 20;

    public static function driver(): string
    {
        $d = (string) Env::get('MAIL_DRIVER', 'archivo');
        return in_array($d, ['smtp', 'archivo', 'desactivado'], true) ? $d : 'archivo';
    }

    /** @return array{0:string,1:string} [dirección, nombre] del remitente */
    public static function remitente(): array
    {
        $from = (string) (Env::get('MAIL_FROM') ?: Env::get('MAIL_USERNAME') ?: 'no-responder@localhost');
        $nombre = (string) (Env::get('MAIL_FROM_NAME') ?: 'Hospital Sanitas');
        return [$from, $nombre];
    }

    /** Resumen de la configuración para mostrar al administrador (sin secretos). */
    public static function estado(): array
    {
        [$from, $nombre] = self::remitente();
        return [
            'driver' => self::driver(), 'host' => (string) Env::get('MAIL_HOST', ''), 'puerto' => (int) Env::get('MAIL_PORT', '587'),
            'cifrado' => (string) Env::get('MAIL_ENCRYPTION', 'tls'), 'usuario' => (string) Env::get('MAIL_USERNAME', ''),
            'clave' => (string) Env::get('MAIL_PASSWORD', '') !== '', 'remitente' => $from, 'nombre' => $nombre,
            'listo' => self::driver() !== 'smtp' || ((string) Env::get('MAIL_HOST', '') !== '' && filter_var($from, FILTER_VALIDATE_EMAIL)),
        ];
    }

    /**
     * @param array<string,string> $cabeceras adicionales (p. ej. List-Unsubscribe)
     * @return array{ok:bool,error:?string,id:string}
     */
    public static function enviar(string $para, ?string $nombre, string $asunto, string $html, string $texto, array $cabeceras = []): array
    {
        $para = trim($para);
        $id = bin2hex(random_bytes(12));
        if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>,;]/', $para)) {
            return ['ok' => false, 'error' => 'Dirección de correo no válida', 'id' => $id];
        }
        $driver = self::driver();
        if ($driver === 'desactivado') {
            return ['ok' => false, 'error' => 'El envío de correo está desactivado (MAIL_DRIVER)', 'id' => $id];
        }
        [$from, $fromNombre] = self::remitente();
        $mensaje = self::mensaje($from, $fromNombre, $para, $nombre, $asunto, $html, $texto, $cabeceras, $id);
        try {
            if ($driver === 'archivo') {
                $dir = dirname(__DIR__, 2) . '/storage/mail';
                if (!is_dir($dir) && !mkdir($dir, 0775, true)) {
                    throw new \RuntimeException('No se pudo crear storage/mail');
                }
                file_put_contents($dir . '/' . date('Ymd-His') . '-' . substr($id, 0, 8) . '.eml', $mensaje);
                return ['ok' => true, 'error' => null, 'id' => $id];
            }
            self::smtp($from, $para, $mensaje);
            return ['ok' => true, 'error' => null, 'id' => $id];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => mb_substr($e->getMessage(), 0, 250), 'id' => $id];
        }
    }

    // ------------------------------------------------------------ Mensaje MIME

    private static function mensaje(string $from, string $fromNombre, string $para, ?string $nombre, string $asunto, string $html, string $texto, array $extra, string $id): string
    {
        $limpio = fn(string $s): string => trim(preg_replace('/[\r\n]+/', ' ', $s));   // sin saltos: evita inyección de cabeceras
        $dominio = substr(strrchr($from, '@') ?: '@localhost', 1);
        $limite = 'b' . bin2hex(random_bytes(10));
        $h = [
            'Date' => date('r'),
            'From' => self::direccion($from, $fromNombre),
            'To' => self::direccion($para, $nombre),
            'Subject' => self::codificar($limpio($asunto)),
            'Message-ID' => '<' . $id . '@' . $dominio . '>',
            'MIME-Version' => '1.0',
            'Content-Type' => 'multipart/alternative; boundary="' . $limite . '"',
            'Auto-Submitted' => 'auto-generated',
            'X-Auto-Response-Suppress' => 'All',
        ];
        foreach ($extra as $k => $v) {
            if (preg_match('/^[A-Za-z][A-Za-z0-9-]*$/', $k)) {
                $h[$k] = $limpio($v);
            }
        }
        $out = '';
        foreach ($h as $k => $v) {
            $out .= $k . ': ' . $v . "\r\n";
        }
        $parte = fn(string $tipo, string $cuerpo): string => '--' . $limite . "\r\nContent-Type: $tipo; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode(str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $cuerpo)) . "\r\n";
        return $out . "\r\n" . $parte('text/plain', $texto) . $parte('text/html', $html) . '--' . $limite . "--\r\n";
    }

    private static function direccion(string $email, ?string $nombre): string
    {
        $nombre = trim(preg_replace('/[\r\n"<>]+/', ' ', (string) $nombre));
        return $nombre === '' ? '<' . $email . '>' : self::codificar($nombre) . ' <' . $email . '>';
    }

    /** Cabecera en UTF-8 (RFC 2047), partida en palabras codificadas cortas. */
    private static function codificar(string $s): string
    {
        if (!preg_match('/[^\x20-\x7E]/', $s)) {
            return $s;
        }
        return implode("\r\n ", array_map(fn($p) => '=?UTF-8?B?' . base64_encode($p) . '?=', mb_str_split($s, 18)));
    }

    // ------------------------------------------------------------ SMTP

    private static function smtp(string $from, string $para, string $mensaje): void
    {
        $host = (string) Env::get('MAIL_HOST', '');
        $puerto = (int) Env::get('MAIL_PORT', '587');
        $cifrado = (string) Env::get('MAIL_ENCRYPTION', 'tls');
        if ($host === '') {
            throw new \RuntimeException('Falta MAIL_HOST en .env');
        }
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
        $s = @stream_socket_client(($cifrado === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $puerto, $errno, $errstr, self::TIMEOUT, STREAM_CLIENT_CONNECT, $ctx);
        if (!$s) {
            throw new \RuntimeException("No se pudo conectar con $host:$puerto ($errstr)");
        }
        stream_set_timeout($s, self::TIMEOUT);
        try {
            self::esperar($s, [220]);
            $ehlo = 'EHLO ' . preg_replace('/[^A-Za-z0-9.-]/', '', gethostname() ?: 'localhost');
            self::orden($s, $ehlo, [250]);
            if ($cifrado === 'tls') {
                self::orden($s, 'STARTTLS', [220]);
                if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT') ? STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT : 0))) {
                    throw new \RuntimeException('No se pudo establecer TLS con el servidor (certificado no válido o TLS no admitido)');
                }
                self::orden($s, $ehlo, [250]);
            }
            $usuario = (string) Env::get('MAIL_USERNAME', '');
            // Nunca enviar la contraseña en claro por la red: sin TLS/SSL solo se permite contra el propio equipo
            if ($usuario !== '' && !in_array($cifrado, ['tls', 'ssl'], true) && !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
                throw new \RuntimeException('Por seguridad no se envían credenciales sin cifrado: use MAIL_ENCRYPTION=tls o ssl');
            }
            if ($usuario !== '') {
                self::orden($s, 'AUTH LOGIN', [334]);
                self::orden($s, base64_encode($usuario), [334], 'usuario');
                self::orden($s, base64_encode((string) Env::get('MAIL_PASSWORD', '')), [235], 'contraseña');
            }
            self::orden($s, 'MAIL FROM:<' . $from . '>', [250]);
            self::orden($s, 'RCPT TO:<' . $para . '>', [250, 251]);
            self::orden($s, 'DATA', [354]);
            // Transparencia (RFC 5321 §4.5.2): una línea que empieza con punto se duplica
            $datos = preg_replace('/^\./m', '..', str_replace(["\r\n", "\n"], ["\n", "\r\n"], $mensaje));
            fwrite($s, rtrim($datos, "\r\n") . "\r\n.\r\n");
            self::esperar($s, [250]);
            @fwrite($s, "QUIT\r\n");
        } finally {
            fclose($s);
        }
    }

    /** @param resource $s */
    private static function orden($s, string $linea, array $codigos, ?string $oculto = null): string
    {
        fwrite($s, $linea . "\r\n");
        return self::esperar($s, $codigos, $oculto ?? strtok($linea, ' '));   // en usuario/contraseña se nombra el paso, nunca el valor
    }

    /** @param resource $s */
    private static function esperar($s, array $codigos, ?string $paso = null): string
    {
        $resp = '';
        while (($l = fgets($s, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        if ($resp === '') {
            throw new \RuntimeException('El servidor de correo no respondió' . ($paso ? " ($paso)" : ''));
        }
        $codigo = (int) substr($resp, 0, 3);
        if (!in_array($codigo, $codigos, true)) {
            throw new \RuntimeException('El servidor rechazó ' . ($paso ?? 'la conexión') . ': ' . trim(substr($resp, 0, 200)));
        }
        return $resp;
    }
}
