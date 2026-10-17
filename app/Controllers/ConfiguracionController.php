<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Color;
use App\Core\Config;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Upload;
use App\Core\Validator;
use App\Domain\Impresos;
use App\Domain\Notificaciones;

/** Configuración en cinco páginas: Institución, Apariencia, Documentos, Notificaciones y Seguridad. */
final class ConfiguracionController extends Controller
{
    private const INSTITUCION = [
        'hospital_nombre'    => 'required|max:100',
        'hospital_lema'      => 'max:80',
        'hospital_telefono'  => 'phone',
        'hospital_correo'    => 'email|max:150',
        'hospital_direccion' => 'max:200',
        'hospital_horario'   => 'max:150',
    ];
    private const SEGURIDAD = [
        'sesion_minutos'  => 'required|int|between:5,480',
        'intentos_max'    => 'required|int|between:3,10',
        'bloqueo_minutos' => 'required|int|between:1,120',
    ];
    private const LABELS = [
        'hospital_nombre' => 'Nombre del hospital', 'hospital_lema' => 'Lema', 'hospital_direccion' => 'Dirección', 'hospital_telefono' => 'Teléfono',
        'hospital_correo' => 'Correo de contacto', 'hospital_horario' => 'Horario de atención',
        'sesion_minutos' => 'Inactividad de sesión', 'archivo_max_mb' => 'Tamaño máximo de documento',
        'intentos_max' => 'Intentos fallidos permitidos', 'bloqueo_minutos' => 'Duración del bloqueo',
        'tema_defecto' => 'Tema', 'color_acento' => 'Color de acento', 'estilo_interfaz' => 'Estilo de la interfaz',
        'cristal_opacidad' => 'Opacidad de los paneles', 'cristal_desenfoque' => 'Desenfoque', 'fondo_oscurecer' => 'Oscurecer el fondo',
        'cristal_intensidad' => 'Intensidad del efecto vidrio', 'fondo_transparencia' => 'Transparencia de la imagen',
        'barra_modo' => 'Barra lateral', 'barra_color' => 'Color de la barra lateral',
    ];
    /** Rehace los documentos de demostración si cambió algo que aparece en el membrete. */
    private static function regenerarSi(array $cambios, array $claves): string
    {
        if (!array_intersect($claves, array_keys($cambios))) {
            return '';
        }
        $n = \App\Domain\DocumentosDemo::regenerar()['regenerados'];
        return $n ? ' ' . cantidad($n, 'documento de demostración se actualizó', 'documentos de demostración se actualizaron') . ' con el nuevo diseño.' : '';
    }

    /** Textos por tipo con su concordancia: título, artículo, guardado, actualizado y restablecido. */
    private const MARCA = [
        'logo'    => ['Logo', 'el logo', 'Logo guardado', 'Logo actualizado', 'Logo restablecido al predeterminado'],
        'favicon' => ['Favicon', 'el favicon', 'Favicon guardado', 'Favicon actualizado', 'Favicon restablecido al predeterminado'],
        'fondo'   => ['Imagen de fondo', 'la imagen de fondo', 'Imagen de fondo guardada', 'Imagen de fondo actualizada', 'Imagen de fondo quitada'],
    ];
    public const ACENTOS = ['cian' => 'Cian', 'azul' => 'Azul', 'verde' => 'Verde', 'grafito' => 'Grafito'];

    // ------------------------------------------------------------ Institución
    public function show(Request $r): void
    {
        $this->view('configuracion/institucion', ['title' => 'Configuración', 'cfg' => Config::all(), 'seccion' => 'institucion']);
    }

    public function update(Request $r): void
    {
        $this->save($r, self::INSTITUCION, 'configuracion', 'Datos de la institución guardados.', fn(array $c) => self::regenerarSi($c, ['hospital_nombre', 'hospital_lema', 'hospital_direccion', 'hospital_telefono', 'hospital_correo']));
    }

    // ------------------------------------------------------------ Seguridad
    public function seguridad(Request $r): void
    {
        $this->view('configuracion/seguridad', ['title' => 'Configuración: Seguridad', 'cfg' => Config::all(), 'seccion' => 'seguridad']);
    }

    public function seguridadUpdate(Request $r): void
    {
        $this->save($r, self::SEGURIDAD, 'configuracion/seguridad', 'Parámetros de seguridad guardados.');
    }


    // ------------------------------------------------------------ Documentos
    public function documentos(Request $r): void
    {
        $this->view('configuracion/documentos', [
            'title' => 'Configuración: Documentos', 'cfg' => Config::all(), 'seccion' => 'documentos',
            'categorias' => Database::all('SELECT c.*, (SELECT COUNT(*) FROM documentos_medicos d WHERE d.categoria_id = c.id) AS usos FROM categorias_documentos c ORDER BY FIELD(c.grupo, "examen", "imagen", "documento"), c.nombre'),
            'emitidos' => (int) Database::value('SELECT COUNT(*) FROM documentos_emitidos'),
        ]);
    }

    public function documentosUpdate(Request $r): void
    {
        $post = $r->post;
        foreach (['doc_logo', 'doc_contacto', 'doc_generado_por', 'doc_verificacion', 'doc_marca_agua'] as $b) {
            $post[$b] = !empty($post[$b]) ? '1' : '0';
        }
        $sec = array_values(array_intersect(array_keys(Impresos::SECCIONES), (array) ($post['doc_secciones'] ?? [])));
        $fmt = array_values(array_intersect(array_keys(Impresos::FORMATOS), (array) ($post['doc_tipos_permitidos'] ?? [])));
        if (in_array('jpg', $fmt, true) && !in_array('jpeg', $fmt, true)) {
            $fmt[] = 'jpeg';
        }
        $color = strtolower(trim((string) ($post['doc_color'] ?? '')));
        $post['doc_color'] = ($post['doc_color_modo'] ?? 'acento') === 'propio' ? $color : '';
        $post['doc_secciones'] = implode(',', $sec);
        $post['doc_tipos_permitidos'] = implode(',', $fmt);

        $rules = [
            'doc_papel' => 'required|in:a4,carta', 'doc_encabezado' => 'required|in:barra,linea,minimo', 'doc_letra' => 'required|in:normal,grande',
            'doc_pie' => 'max:220', 'doc_marca_agua_texto' => 'max:30', 'doc_firma_texto' => 'max:80', 'doc_receta_nota' => 'max:255',
            'archivo_max_mb' => 'required|int|between:1,40', 'doc_color' => 'max:7',
            'doc_logo' => 'required', 'doc_contacto' => 'required', 'doc_generado_por' => 'required', 'doc_verificacion' => 'required', 'doc_marca_agua' => 'required',
            'doc_secciones' => 'max:255', 'doc_tipos_permitidos' => 'max:100',
        ];
        $v = Validator::make($post, $rules, self::LABELS + [
            'doc_papel' => 'Tamaño de papel', 'doc_encabezado' => 'Estilo de encabezado', 'doc_letra' => 'Tamaño de letra', 'doc_pie' => 'Pie de página',
            'doc_marca_agua_texto' => 'Texto de la marca de agua', 'doc_firma_texto' => 'Texto de la firma', 'doc_receta_nota' => 'Nota de la receta', 'doc_color' => 'Color',
        ]);
        if ($post['doc_color'] !== '' && !Color::isHex($post['doc_color'])) {
            $v->addError('doc_color', 'Elija un color válido (formato #RRGGBB).');
        }
        if (!$fmt) {
            $v->addError('doc_tipos_permitidos', 'Debe aceptar al menos un formato de archivo.');
        }
        if (!$sec) {
            $v->addError('doc_secciones', 'El resumen clínico debe incluir al menos una sección.');
        }
        if ($post['doc_marca_agua'] === '1' && trim((string) ($post['doc_marca_agua_texto'] ?? '')) === '') {
            $v->addError('doc_marca_agua_texto', 'Escriba el texto de la marca de agua o desactívela.');
        }
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/documentos');
        }
        // Los documentos de demostración se rehacen con el nuevo diseño para que todos los usuarios vean el actual
        $this->persist($v->clean(), 'configuracion/documentos', 'Configuración de documentos guardada.', fn(array $c) => self::regenerarSi($c,
            ['doc_papel', 'doc_encabezado', 'doc_letra', 'doc_color', 'doc_logo', 'doc_contacto', 'doc_marca_agua', 'doc_marca_agua_texto']));
    }

    public function categoriaStore(Request $r): void
    {
        $v = $this->validarCategoria($r, 0);
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/documentos#categorias');
        }
        $id = Database::insert('categorias_documentos', $v->clean());
        Audit::log('crear', 'configuracion', 'Categoría de documentos creada: ' . $v->clean()['nombre'], $id);
        $this->flash('success', 'Categoría agregada.');
        $this->redirect('configuracion/documentos#categorias');
    }

    public function categoriaUpdate(Request $r, array $p): void
    {
        $id = (int) $p['id'];
        if (!Database::value('SELECT id FROM categorias_documentos WHERE id = ?', [$id])) {
            throw new \App\Core\HttpException(404);
        }
        $v = $this->validarCategoria($r, $id);
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/documentos#categorias');
        }
        Database::update('categorias_documentos', $v->clean(), 'id = ?', [$id]);
        Audit::log('editar', 'configuracion', 'Categoría de documentos actualizada: ' . $v->clean()['nombre'], $id);
        $this->flash('success', 'Categoría actualizada.');
        $this->redirect('configuracion/documentos#categorias');
    }

    public function categoriaDestroy(Request $r, array $p): void
    {
        $c = Database::one('SELECT c.*, (SELECT COUNT(*) FROM documentos_medicos d WHERE d.categoria_id = c.id) AS usos FROM categorias_documentos c WHERE c.id = ?', [(int) $p['id']]);
        if (!$c) {
            throw new \App\Core\HttpException(404);
        }
        if ($c['usos'] > 0) {
            $this->flash('error', 'No se puede eliminar «' . $c['nombre'] . '»: tiene ' . $c['usos'] . ' documento(s). Renómbrela o reasigne los documentos.');
            $this->redirect('configuracion/documentos#categorias');
        }
        Database::run('DELETE FROM categorias_documentos WHERE id = ?', [$c['id']]);
        Audit::log('eliminar', 'configuracion', 'Categoría de documentos eliminada: ' . $c['nombre'], (int) $c['id']);
        $this->flash('success', 'Categoría eliminada.');
        $this->redirect('configuracion/documentos#categorias');
    }

    private function validarCategoria(Request $r, int $id): Validator
    {
        $v = Validator::make($r->post, ['nombre' => 'required|max:60', 'grupo' => 'required|in:examen,imagen,documento'], ['nombre' => 'Nombre de la categoría', 'grupo' => 'Grupo']);
        if (!$v->fails() && Database::value('SELECT id FROM categorias_documentos WHERE nombre = ? AND id <> ?', [$v->clean()['nombre'], $id])) {
            $v->addError('nombre', 'Ya existe una categoría con ese nombre.');
        }
        return $v;
    }

    // ------------------------------------------------------------ Notificaciones
    public function notificaciones(Request $r): void
    {
        $this->view('configuracion/notificaciones', [
            'title' => 'Configuración: Notificaciones', 'cfg' => Config::all(), 'seccion' => 'notificaciones',
            'tipos' => Notificaciones::TIPOS, 'apagados' => Notificaciones::tiposApagados(),
            'stats' => Database::one('SELECT COUNT(*) AS total, COALESCE(SUM(leida_en IS NULL), 0) AS sin_leer, COUNT(DISTINCT usuario_id) AS usuarios FROM notificaciones WHERE creado_en >= (NOW() - INTERVAL 30 DAY)'),
            'roles' => array_column(Database::all('SELECT codigo, nombre FROM roles ORDER BY id'), 'nombre', 'codigo'),
        ]);
    }

    public function notificacionesUpdate(Request $r): void
    {
        $post = $r->post;
        $post['notif_activas'] = !empty($post['notif_activas']) ? '1' : '0';
        $activos = (array) ($post['tipos_activos'] ?? []);
        $post['notif_tipos_off'] = implode(',', array_values(array_diff(array_keys(Notificaciones::TIPOS), $activos)));
        $v = Validator::make($post, [
            'notif_activas' => 'required', 'notif_tipos_off' => 'max:255',
            'notif_recordatorio_horas' => 'required|int|between:1,168', 'notif_retencion_dias' => 'required|int|between:7,365',
        ], ['notif_recordatorio_horas' => 'Anticipación de recordatorios', 'notif_retencion_dias' => 'Conservación de notificaciones leídas']);
        if ($v->fails()) {
            $this->invalid($v, $r, 'configuracion/notificaciones');
        }
        $this->persist($v->clean(), 'configuracion/notificaciones', 'Configuración de notificaciones guardada.');
    }

    // ------------------------------------------------------------ Apariencia
    public function apariencia(Request $r): void
    {
        $this->view('configuracion/apariencia', [
            'title' => 'Configuración: Apariencia', 'cfg' => Config::all(), 'seccion' => 'apariencia', 'acentos' => self::ACENTOS,
            'maxLogoKb' => 1024, 'maxFaviconKb' => 256,
        ]);
    }

    public function aparienciaUpdate(Request $r): void
    {
        // El acento llega como hex (selector de color) o como nombre de atajo. Un hex igual a un atajo se guarda con su nombre.
        $post = $r->post;
        $color = strtolower(trim((string) ($post['color_acento'] ?? '')));
        $atajo = array_search($color, Color::PRESETS, true);
        $post['color_acento'] = $atajo !== false ? $atajo : $color;
        // Los campos del estilo que no lleguen conservan su valor actual.
        $c = \App\Domain\Marca::cristal(Config::all());
        $post += ['estilo_interfaz' => $c['activo'] ? 'cristal' : 'solido', 'cristal_opacidad' => (string) $c['opacidad'],
            'cristal_desenfoque' => (string) $c['desenfoque'], 'fondo_oscurecer' => (string) $c['oscurecer'],
            'cristal_intensidad' => (string) $c['intensidad'], 'fondo_transparencia' => (string) $c['transparencia']];
        $b = \App\Domain\Marca::barra(Config::all());
        $post += ['barra_modo' => $b['modo'], 'barra_color' => $b['color']];
        $post['barra_color'] = strtolower(trim((string) $post['barra_color']));
        $rules = ['tema_defecto' => 'required|in:claro,oscuro,auto', 'color_acento' => 'required|max:7', 'estilo_interfaz' => 'required|in:solido,cristal',
            'cristal_opacidad' => 'required|int|between:50,95', 'cristal_desenfoque' => 'required|int|between:0,30', 'fondo_oscurecer' => 'required|int|between:0,70',
            'cristal_intensidad' => 'required|int|between:0,100', 'fondo_transparencia' => 'required|int|between:0,90',
            'barra_modo' => 'required|in:automatico,claro,personalizado', 'barra_color' => 'required|regex:/^#[0-9a-f]{6}$/'];
        if (!Color::isHex($post['color_acento']) && !isset(Color::PRESETS[$post['color_acento']])) {
            $v = Validator::make($post, $rules, self::LABELS);
            $v->addError('color_acento', 'Elija un color válido (formato #RRGGBB).');
            $this->invalid($v, $r, 'configuracion/apariencia');
        }
        $this->save(new Request($r->method, $r->path, $r->query, $post, $r->files), $rules, 'configuracion/apariencia', 'Apariencia guardada.', fn(array $c) => self::regenerarSi($c, ['color_acento']));
    }

    /** Subir o quitar el logo, el favicon y la imagen de fondo. */
    public function marcaUpdate(Request $r): void
    {
        $accion = $r->str('accion');
        if (!in_array($accion, ['subir_logo', 'subir_favicon', 'subir_fondo', 'quitar_logo', 'quitar_favicon', 'quitar_fondo'], true)) {
            $this->flash('error', 'Acción no válida.');
            $this->redirect('configuracion/apariencia');
        }
        [$verbo, $tipo] = explode('_', $accion);
        $clave = $tipo . '_archivo';
        $actual = Config::get($clave);
        [, $articulo, $guardado, $actualizado, $restablecido] = self::MARCA[$tipo];

        if ($verbo === 'quitar') {
            $this->setValue($clave, '');
            $this->deleteFile($actual);
            Audit::log('editar', 'configuracion', $restablecido);
            if ($tipo === 'logo') {
                \App\Domain\DocumentosDemo::regenerar();
            }
            $_SESSION['_marca'] = ['tipo' => $tipo, 'ok' => true, 'msg' => $tipo === 'fondo' ? 'Imagen de fondo quitada. El estilo cristal usa ahora un fondo del color de acento.' : $restablecido . '.'];
            $this->redirect('configuracion/apariencia#marca-' . $tipo);
        }

        $res = \App\Domain\Marca::guardar($tipo, $_FILES['archivo'] ?? null, MarcaController::dir());
        if (!$res['ok']) {
            \App\Core\Logger::error('Carga rechazada (' . $tipo . ', «' . (string) ($_FILES['archivo']['name'] ?? '') . '», ' . (int) ($_FILES['archivo']['size'] ?? 0) . ' bytes): ' . $res['mensaje']);
            $_SESSION['_marca'] = ['tipo' => $tipo, 'ok' => false, 'msg' => 'No se cargó ' . $articulo . ': ' . $res['mensaje']];
            $this->redirect('configuracion/apariencia#marca-' . $tipo);
        }
        $nombre = $res['nombre'];
        $this->setValue($clave, $nombre);
        if (isset($res['luma'])) {
            $this->setValue('fondo_luma', (string) $res['luma']);
        }
        $this->deleteFile($actual);
        Audit::log('editar', 'configuracion', $actualizado . ' (' . $res['detalle'] . ')');
        if ($tipo === 'logo') {
            \App\Domain\DocumentosDemo::regenerar();   // el logo va en el membrete de los documentos
        }
        $aviso = $tipo === 'fondo' && Config::get('estilo_interfaz') !== 'cristal' ? ' Se verá cuando active el estilo «Cristal» arriba.' : ' Ya se muestra en todo el sistema.';
        $_SESSION['_marca'] = ['tipo' => $tipo, 'ok' => true, 'msg' => $guardado . ' (' . $res['detalle'] . ').' . $aviso];
        $this->redirect('configuracion/apariencia#marca-' . $tipo);
    }

    // ------------------------------------------------------------ Privados
    private function save(Request $r, array $rules, string $back, string $ok, ?callable $despues = null): void
    {
        $v = Validator::make($r->post, $rules, self::LABELS);
        if ($v->fails()) {
            $this->invalid($v, $r, $back);
        }
        $this->persist($v->clean(), $back, $ok, $despues);
    }

    /**
     * Guarda los valores validados y deja constancia en la auditoría de qué claves cambiaron. $despues corre con la
     * configuración ya guardada y puede añadir texto al mensaje.
     */
    private function persist(array $clean, string $back, string $ok, ?callable $despues = null): never
    {
        $antes = Config::all();
        $nuevo = array_map(fn($x) => (string) ($x ?? ''), $clean);
        foreach ($nuevo as $clave => $valor) {
            $this->setValue($clave, $valor);
        }
        $cambios = Audit::diff($antes, $nuevo);
        Audit::log('editar', 'configuracion', 'Configuración modificada' . ($cambios ? ' (' . implode(', ', array_keys($cambios)) . ')' : ' (sin cambios)'), null, null, ['cambios' => $cambios]);
        if ($despues) {
            $ok .= (string) $despues($cambios);
        }
        $this->flash('success', $ok);
        $this->redirect($back);
    }

    private function setValue(string $clave, string $valor): void
    {
        Database::run('INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$clave, $valor]);
        Config::reset();
    }

    private function deleteFile(string $nombre): void
    {
        if (preg_match(\App\Domain\Marca::NOMBRE, $nombre)) {
            @unlink(MarcaController::dir() . '/' . $nombre);
        }
    }
}
