<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\Color;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;

/**
 * Notificaciones por correo. Cada aviso del sistema puede generar además un correo, que se guarda en una cola
 * (correos_salientes) y lo envía la tarea programada (bin/tareas.php) con reintentos.
 *
 * Privacidad: un correo sale del sistema (puede leerse en un teléfono compartido o reenviarse), por eso nunca
 * lleva información clínica: ni diagnósticos, ni motivos de consulta, ni títulos de documentos, ni nombres de
 * pacientes en los correos a médicos. Solo dice que hay algo nuevo y enlaza al sistema, donde se ve tras iniciar sesión.
 */
final class Correo
{
    /** tipo => [asunto, frase]. {detalle} se reemplaza por un dato no clínico (fecha y hora), si lo hay. */
    public const PLANTILLAS = [
        'consulta_programada'   => ['Nueva consulta programada', 'Se programó una consulta para usted{detalle}.'],
        'consulta_registrada'   => ['Su consulta ya está disponible', 'El médico registró la atención de su consulta. Puede ver el resumen en el sistema.'],
        'consulta_cancelada'    => ['Consulta cancelada', 'Se canceló la consulta{detalle}.'],
        'recordatorio_consulta' => ['Recordatorio de consulta', 'Le recordamos la consulta{detalle}.'],
        'documento_nuevo'       => ['Nuevo documento disponible', 'Se agregó un documento a un historial médico que usted puede consultar.'],
        'alergia_grave'         => ['Alerta clínica en su agenda', 'Se registró una alerta clínica de un paciente que usted atenderá próximamente.'],
        'vacuna_pendiente'      => ['Vacuna pendiente', 'Tiene una dosis de vacuna próxima o vencida.'],
        'seguridad'             => ['Alerta de seguridad', '{titulo}.'],
        'cuentas'               => ['Cuentas de usuario', '{titulo}.'],
        'aviso'                 => ['{titulo}', '{mensaje}'],
    ];
    /** Avisos que se envían a pacientes sin cuenta en el portal (solo con su consentimiento registrado). */
    public const PARA_SIN_CUENTA = ['consulta_programada', 'consulta_cancelada', 'recordatorio_consulta'];
    private const ESPERAS = [5, 30, 120];   // minutos entre reintentos

    public static function activo(): bool
    {
        return Config::get('correo_activo', '1') === '1' && Mailer::driver() !== 'desactivado';
    }

    /** @return list<string> */
    public static function tiposPorCorreo(): array
    {
        return array_values(array_intersect(array_keys(self::PLANTILLAS), explode(',', Config::get('correo_tipos'))));
    }

    /** ¿El usuario quiere este aviso por correo? Sin preferencia guardada, sí para los tipos habilitados. */
    public static function prefiere(int $usuarioId, string $tipo): bool
    {
        if (!in_array($tipo, self::tiposPorCorreo(), true)) {
            return false;
        }
        $p = Database::one('SELECT activo, correo FROM notificacion_preferencias WHERE usuario_id = ? AND tipo = ?', [$usuarioId, $tipo]);
        return $p === null || $p['correo'] === null ? true : (bool) $p['correo'];
    }

    /** Correo que acompaña a una notificación interna recién creada. */
    public static function deNotificacion(int $usuarioId, string $tipo, string $titulo, ?string $mensaje, ?string $enlace, ?string $detalle, ?string $clave, ?int $pacienteId): void
    {
        try {
            if (!self::activo() || !self::prefiere($usuarioId, $tipo)) {
                return;
            }
            $u = Database::one('SELECT email, nombres, apellidos, activo FROM usuarios WHERE id = ?', [$usuarioId]);
            if (!$u || !$u['activo'] || !filter_var($u['email'], FILTER_VALIDATE_EMAIL)) {
                return;
            }
            $nombre = trim($u['nombres'] . ' ' . $u['apellidos']);
            [$asunto, $html, $texto] = self::componer($tipo, $titulo, $mensaje, $detalle, $enlace, $u['nombres'], self::urlAbsoluta('correo/baja/' . self::tokenBaja($usuarioId, $tipo)));
            self::encolar(['usuario_id' => $usuarioId, 'paciente_id' => $pacienteId, 'destinatario' => $u['email'], 'nombre' => $nombre, 'tipo' => $tipo,
                'asunto' => $asunto, 'cuerpo_html' => $html, 'cuerpo_texto' => $texto, 'clave' => $clave ? 'u' . $usuarioId . '-' . $clave : null]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo encolar un correo', $e);
        }
    }

    /** Aviso por correo a un paciente sin cuenta en el portal, solo si aceptó recibir correos. */
    public static function aPacienteSinCuenta(int $pacienteId, string $tipo, ?string $detalle, ?string $clave = null): void
    {
        try {
            if (!self::activo() || !in_array($tipo, self::PARA_SIN_CUENTA, true) || !in_array($tipo, self::tiposPorCorreo(), true)) {
                return;
            }
            $p = Database::one('SELECT nombres, apellidos, email, acepta_correo, activo FROM pacientes WHERE id = ? AND usuario_id IS NULL', [$pacienteId]);
            if (!$p || !$p['activo'] || !$p['acepta_correo'] || !filter_var((string) $p['email'], FILTER_VALIDATE_EMAIL)) {
                return;
            }
            [$asunto, $html, $texto] = self::componer($tipo, '', null, $detalle, null, $p['nombres'], null, true);
            self::encolar(['usuario_id' => null, 'paciente_id' => $pacienteId, 'destinatario' => $p['email'], 'nombre' => trim($p['nombres'] . ' ' . $p['apellidos']),
                'tipo' => $tipo, 'asunto' => $asunto, 'cuerpo_html' => $html, 'cuerpo_texto' => $texto, 'clave' => $clave ? 'p' . $pacienteId . '-' . $clave : null]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo encolar un correo a paciente', $e);
        }
    }

    public static function encolar(array $d): bool
    {
        return Database::run(
            'INSERT IGNORE INTO correos_salientes (usuario_id, paciente_id, destinatario, nombre, tipo, asunto, cuerpo_html, cuerpo_texto, clave) VALUES (?,?,?,?,?,?,?,?,?)',
            [$d['usuario_id'], $d['paciente_id'], $d['destinatario'], $d['nombre'], $d['tipo'], mb_substr($d['asunto'], 0, 200), $d['cuerpo_html'], $d['cuerpo_texto'], $d['clave']]
        ) > 0;
    }

    /**
     * Correo inmediato (sin cola): recuperación de contraseña y correo de prueba. Queda en la bandeja de salida.
     * @param list<string> $parrafos
     * @return array{ok:bool,error:?string}
     */
    public static function enviarAhora(string $para, string $nombre, string $tipo, string $asunto, array $parrafos, ?array $boton = null, ?int $usuarioId = null): array
    {
        [$html, $texto] = self::plantilla($asunto, $nombre, $parrafos, $boton, null, $tipo === 'recuperacion' ? 'Si usted no lo solicitó, ignore este mensaje: su contraseña no cambiará.' : null);
        $r = Mailer::enviar($para, $nombre, $asunto, $html, $texto);
        try {
            Database::insert('correos_salientes', [
                'usuario_id' => $usuarioId, 'destinatario' => $para, 'nombre' => $nombre, 'tipo' => $tipo, 'asunto' => $asunto,
                // El enlace de recuperación es una credencial: no se conserva en la bandeja
                'cuerpo_html' => $tipo === 'recuperacion' ? '(contenido no conservado por seguridad)' : $html,
                'cuerpo_texto' => $tipo === 'recuperacion' ? '(contenido no conservado por seguridad)' : $texto,
                'estado' => $r['ok'] ? 'enviado' : 'fallido', 'intentos' => 1, 'ultimo_error' => $r['error'], 'enviado_en' => $r['ok'] ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            Logger::error('No se pudo registrar el correo enviado', $e);
        }
        return ['ok' => $r['ok'], 'error' => $r['error']];
    }

    /**
     * Envía los correos pendientes, respetando el límite por hora. Los que fallan se reintentan a los 5, 30 y
     * 120 minutos; después quedan como fallidos para revisión en la bandeja de salida.
     * @return array{enviados:int,fallidos:int,pendientes:int,limite:bool}
     */
    public static function procesar(int $max = 50): array
    {
        $res = ['enviados' => 0, 'fallidos' => 0, 'pendientes' => 0, 'limite' => false];
        if (Mailer::driver() === 'desactivado') {
            $res['pendientes'] = (int) Database::value("SELECT COUNT(*) FROM correos_salientes WHERE estado = 'pendiente'");
            return $res;
        }
        $limite = max(1, Config::int('correo_limite_hora', 300));
        $enHora = (int) Database::value("SELECT COUNT(*) FROM correos_salientes WHERE estado = 'enviado' AND enviado_en >= (NOW() - INTERVAL 1 HOUR)");
        $cupo = min($max, $limite - $enHora);
        if ($cupo <= 0) {
            $res['limite'] = true;
        }
        $filas = $cupo > 0 ? Database::all("SELECT * FROM correos_salientes WHERE estado = 'pendiente' AND siguiente_intento <= NOW() ORDER BY id LIMIT " . (int) $cupo) : [];
        foreach ($filas as $c) {
            $cab = $c['usuario_id'] ? ['List-Unsubscribe' => '<' . self::urlAbsoluta('correo/baja/' . self::tokenBaja((int) $c['usuario_id'], $c['tipo'])) . '>'] : [];
            $r = Mailer::enviar($c['destinatario'], $c['nombre'], $c['asunto'], $c['cuerpo_html'], $c['cuerpo_texto'], $cab);
            $intentos = (int) $c['intentos'] + 1;
            if ($r['ok']) {
                Database::run("UPDATE correos_salientes SET estado = 'enviado', intentos = ?, enviado_en = NOW(), ultimo_error = NULL WHERE id = ?", [$intentos, $c['id']]);
                $res['enviados']++;
            } elseif ($intentos > count(self::ESPERAS)) {
                Database::run("UPDATE correos_salientes SET estado = 'fallido', intentos = ?, ultimo_error = ? WHERE id = ?", [$intentos, $r['error'], $c['id']]);
                Logger::error('Correo #' . $c['id'] . ' fallido tras ' . $intentos . ' intentos: ' . $r['error']);
                $res['fallidos']++;
            } else {
                Database::run('UPDATE correos_salientes SET intentos = ?, ultimo_error = ?, siguiente_intento = (NOW() + INTERVAL ? MINUTE) WHERE id = ?', [$intentos, $r['error'], self::ESPERAS[$intentos - 1], $c['id']]);
            }
        }
        $res['pendientes'] = (int) Database::value("SELECT COUNT(*) FROM correos_salientes WHERE estado = 'pendiente'");
        return $res;
    }

    /** Elimina de la bandeja los correos enviados o cancelados de hace más de 90 días. */
    public static function depurar(): int
    {
        return Database::run("DELETE FROM correos_salientes WHERE estado IN ('enviado','cancelado') AND creado_en < (NOW() - INTERVAL 90 DAY)");
    }

    // ------------------------------------------------------------ Contenido

    /**
     * Asunto, HTML y texto del correo de un aviso. Solo usa datos no clínicos.
     * @return array{0:string,1:string,2:string}
     */
    public static function componer(string $tipo, string $titulo, ?string $mensaje, ?string $detalle, ?string $enlace, string $nombre, ?string $bajaUrl, bool $sinCuenta = false): array
    {
        [$asunto, $frase] = self::PLANTILLAS[$tipo] ?? ['Nueva notificación', 'Tiene una notificación nueva en el sistema.'];
        $r = ['{detalle}' => $detalle ? ' ' . $detalle : '', '{titulo}' => $titulo, '{mensaje}' => (string) $mensaje];
        $asunto = strtr($asunto, $r);
        $parrafos = [strtr($frase, $r)];
        if ($tipo !== 'aviso' && !in_array($tipo, ['seguridad', 'cuentas'], true)) {
            $parrafos[] = $sinCuenta
                ? 'Si necesita reprogramar o tiene dudas, comuníquese con el hospital.'
                : 'Por su privacidad, este correo no incluye información médica. Ingrese al sistema para ver el detalle.';
        }
        $boton = $sinCuenta ? null : ['Abrir en el sistema', self::urlAbsoluta($enlace && str_starts_with($enlace, '/') ? ltrim($enlace, '/') : 'notificaciones')];
        $pie = $bajaUrl ? 'Recibe este correo porque tiene activos estos avisos. Puede dejar de recibirlos aquí: ' . $bajaUrl : null;
        [$html, $texto] = self::plantilla($asunto, $nombre, $parrafos, $boton, $bajaUrl, $pie);
        return [self::hospital() . ': ' . $asunto, $html, $texto];
    }

    /**
     * Plantilla institucional, compatible con clientes de correo (tablas y estilos en línea).
     * @return array{0:string,1:string} [html, texto]
     */
    private static function plantilla(string $titulo, string $nombre, array $parrafos, ?array $boton, ?string $bajaUrl, ?string $nota): array
    {
        $e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $hospital = self::hospital();
        $color = Color::brand(Config::get('doc_color') ?: Config::get('color_acento', 'cian'));
        $contacto = implode(' | ', array_filter([Config::get('hospital_direccion'), Config::get('hospital_telefono')]));
        $ps = implode('', array_map(fn($p) => '<p style="margin:0 0 14px;font-size:15px;line-height:1.55;color:#262626">' . nl2br($e($p)) . '</p>', $parrafos));
        $btn = $boton ? '<p style="margin:22px 0 6px"><a href="' . $e($boton[1]) . '" style="display:inline-block;background:' . $e($color) . ';color:#ffffff;text-decoration:none;font-weight:600;font-size:14px;padding:11px 20px;border-radius:6px">' . $e($boton[0]) . '</a></p>' : '';
        $notaHtml = $nota ? '<p style="margin:18px 0 0;font-size:12.5px;line-height:1.5;color:#6b6b6b">' . ($bajaUrl ? $e(str_replace($bajaUrl, '', $nota)) . '<a href="' . $e($bajaUrl) . '" style="color:#6b6b6b">dejar de recibir estos avisos</a>.' : $e($nota)) . '</p>' : '';
        $html = '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $e($titulo) . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f2f2f2;font-family:Segoe UI,Helvetica,Arial,sans-serif">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f2f2f2"><tr><td align="center" style="padding:28px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e3e3e3;border-radius:8px">'
            . '<tr><td style="padding:20px 28px;border-bottom:3px solid ' . $e($color) . '"><strong style="font-size:16px;color:#161616">' . $e($hospital) . '</strong></td></tr>'
            . '<tr><td style="padding:26px 28px 24px"><h1 style="margin:0 0 16px;font-size:19px;line-height:1.3;color:#161616">' . $e($titulo) . '</h1>'
            . '<p style="margin:0 0 14px;font-size:15px;color:#262626">Hola, ' . $e($nombre) . ':</p>' . $ps . $btn . $notaHtml . '</td></tr>'
            . '<tr><td style="padding:16px 28px;border-top:1px solid #ececec;font-size:12px;line-height:1.5;color:#7a7a7a">Mensaje automático de ' . $e($hospital) . '. No responda a este correo.' . ($contacto ? '<br>' . $e($contacto) : '') . '</td></tr>'
            . '</table></td></tr></table></body></html>';
        $texto = $hospital . "\n" . str_repeat('=', mb_strlen($hospital)) . "\n\n" . $titulo . "\n\nHola, " . $nombre . ":\n\n" . implode("\n\n", $parrafos)
            . ($boton ? "\n\n" . $boton[0] . ': ' . $boton[1] : '') . ($nota ? "\n\n" . $nota : '')
            . "\n\n--\nMensaje automático de " . $hospital . '. No responda a este correo.' . ($contacto ? "\n" . $contacto : '') . "\n";
        return [$html, $texto];
    }

    private static function hospital(): string
    {
        return Config::get('hospital_nombre', 'Hospital Sanitas') ?: 'Hospital Sanitas';
    }

    public static function urlAbsoluta(string $ruta): string
    {
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        if ($base === '') {
            $base = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(url(''), '/');
        }
        return $base . '/' . ltrim($ruta, '/');
    }

    // ------------------------------------------------------------ Bajas

    /** Enlace firmado para dejar de recibir un tipo de aviso por correo (o todos, con tipo «todos»). */
    public static function tokenBaja(int $usuarioId, string $tipo): string
    {
        $i = $tipo === 'todos' ? 99 : (int) array_search($tipo, array_keys(self::PLANTILLAS), true);
        $base = sprintf('%08x%02d', $usuarioId, $i);
        return $base . substr(hash_hmac('sha256', $base, self::clave()), 0, 32);
    }

    /** @return array{0:int,1:string}|null [usuario, tipo] */
    public static function leerBaja(string $token): ?array
    {
        if (!preg_match('/^([0-9a-f]{8})(\d{2})([0-9a-f]{32})$/', $token, $m)) {
            return null;
        }
        if (!hash_equals(substr(hash_hmac('sha256', $m[1] . $m[2], self::clave()), 0, 32), $m[3])) {
            return null;
        }
        $tipos = array_keys(self::PLANTILLAS);
        $tipo = (int) $m[2] === 99 ? 'todos' : ($tipos[(int) $m[2]] ?? null);
        return $tipo ? [hexdec($m[1]), $tipo] : null;
    }

    /** Desactiva el correo de un tipo (o de todos) para el usuario; el aviso dentro del sistema se mantiene. */
    public static function darDeBaja(int $usuarioId, string $tipo): void
    {
        foreach ($tipo === 'todos' ? array_keys(Notificaciones::TIPOS) : [$tipo] as $t) {
            Database::run('INSERT INTO notificacion_preferencias (usuario_id, tipo, activo, correo) VALUES (?, ?, 1, 0) ON DUPLICATE KEY UPDATE correo = 0', [$usuarioId, $t]);
        }
    }

    private static function clave(): string
    {
        $k = (string) Env::get('APP_KEY', '');
        if (strlen($k) < 32) {
            throw new \RuntimeException('Falta APP_KEY en .env (al menos 32 caracteres) para firmar los enlaces de correo');
        }
        return $k;
    }
}
