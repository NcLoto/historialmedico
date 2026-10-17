<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Pager;
use App\Core\Request;

final class AuditoriaController extends Controller
{
    /** Filtros de la bitácora, compartidos por la pantalla y la exportación CSV. */
    public static function filtros(Request $r): array
    {
        $where = ['1=1'];
        $params = [];
        $q = mb_substr($r->str('q'), 0, 100);
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(a.usuario_nombre LIKE ? OR a.descripcion LIKE ? OR p.codigo LIKE ?)';
            array_push($params, $like, $like, $like);
        }
        $accion = $r->str('accion');
        if ($accion !== '') {
            $where[] = 'a.accion = ?';
            $params[] = $accion;
        }
        $modulo = $r->str('modulo');
        if ($modulo !== '') {
            $where[] = 'a.modulo = ?';
            $params[] = $modulo;
        }
        $desde = self::date($r->str('desde'));
        $hasta = self::date($r->str('hasta'));
        if ($desde) {
            $where[] = 'a.creado_en >= ?';
            $params[] = $desde . ' 00:00:00';
        }
        if ($hasta) {
            $where[] = 'a.creado_en <= ?';
            $params[] = $hasta . ' 23:59:59';
        }
        return ['where' => implode(' AND ', $where), 'params' => $params, 'q' => $q, 'accion' => $accion, 'modulo' => $modulo, 'desde' => $desde, 'hasta' => $hasta];
    }

    public const FROM = 'FROM auditoria a LEFT JOIN pacientes p ON p.id = a.paciente_id';

    public function index(Request $r): void
    {
        $f = self::filtros($r);
        $pager = Pager::make((int) Database::value('SELECT COUNT(*) ' . self::FROM . ' WHERE ' . $f['where'], $f['params']), $r->input('pagina', 1), 25);
        $rows = Database::all(
            'SELECT a.*, p.codigo AS paciente_codigo ' . self::FROM . ' WHERE ' . $f['where'] . ' ORDER BY a.id DESC LIMIT 25 OFFSET ' . $pager->offset(),
            $f['params']
        );
        $this->view('auditoria/index', [
            'title' => 'Auditoría', 'rows' => $rows, 'pager' => $pager, 'q' => $f['q'], 'accion' => $f['accion'], 'modulo' => $f['modulo'], 'desde' => $f['desde'], 'hasta' => $f['hasta'],
            'acciones' => $this->distinct('accion'), 'modulos' => $this->distinct('modulo'),
        ]);
    }

    private function distinct(string $col): array
    {
        $vals = array_column(Database::all("SELECT DISTINCT `$col` AS v FROM auditoria ORDER BY v"), 'v');
        return array_combine($vals, array_map($col === 'modulo' ? 'modulo_label' : 'accion_label', $vals));
    }

    private static function date(string $v): string
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v ? $v : '';
    }
}
