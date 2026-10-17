<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Policy;
use App\Core\Request;
use App\Core\Validator;
use App\Domain\HistorialSections;
use App\Repositories\HistorialRepository;

/** Alta, edición y baja de registros del historial clínico de un paciente (las 8 secciones). */
final class HistorialController extends Controller
{
    public function create(Request $r, array $p): void
    {
        [$paciente, $s] = $this->context($p);
        $this->view('historial/form', [
            'title' => 'Agregar ' . $s['singular'], 'p' => $paciente, 's' => $s, 'key' => $p['sec'], 'registro' => null,
        ]);
    }

    public function store(Request $r, array $p): void
    {
        [$paciente, $s] = $this->context($p);
        $v = $this->validate($r, $s);
        if ($v->fails()) {
            $this->invalid($v, $r, "pacientes/{$paciente['id']}/historial/{$p['sec']}/nuevo");
        }
        $id = HistorialRepository::insert($s, (int) $paciente['id'], $v->clean(), (int) Auth::user()['id']);
        Audit::log('crear', 'historial', 'Registro agregado en ' . $s['titulo'], $id, (int) $paciente['id'], ['seccion' => $p['sec']]);
        $this->avisarAlergia($p['sec'], $v->clean(), $paciente, null);
        $this->flash('success', ucfirst_mb($s['singular']) . ' registrado.');
        $this->redirect("pacientes/{$paciente['id']}#{$p['sec']}");
    }

    public function edit(Request $r, array $p): void
    {
        [$paciente, $s] = $this->context($p);
        $registro = $this->record($s, $paciente, (int) $p['rid']);
        $this->view('historial/form', [
            'title' => 'Editar ' . $s['singular'], 'p' => $paciente, 's' => $s, 'key' => $p['sec'], 'registro' => $registro,
        ]);
    }

    public function update(Request $r, array $p): void
    {
        [$paciente, $s] = $this->context($p);
        $registro = $this->record($s, $paciente, (int) $p['rid']);
        $v = $this->validate($r, $s);
        if ($v->fails()) {
            $this->invalid($v, $r, "pacientes/{$paciente['id']}/historial/{$p['sec']}/{$registro['id']}/editar");
        }
        $data = $v->clean();
        HistorialRepository::update($s, (int) $paciente['id'], (int) $registro['id'], $data);
        $campos = Audit::changedFields($registro, $data);
        $this->avisarAlergia($p['sec'], $data, $paciente, $registro);
        Audit::log('editar', 'historial', 'Registro modificado en ' . $s['titulo'] . ($campos ? ' (' . implode(', ', $campos) . ')' : ''), (int) $registro['id'], (int) $paciente['id'], ['seccion' => $p['sec'], 'campos' => $campos]);
        $this->flash('success', ucfirst_mb($s['singular']) . ' actualizado.');
        $this->redirect("pacientes/{$paciente['id']}#{$p['sec']}");
    }

    public function destroy(Request $r, array $p): void
    {
        [$paciente, $s] = $this->context($p);
        $registro = $this->record($s, $paciente, (int) $p['rid']);
        HistorialRepository::delete($s, (int) $paciente['id'], (int) $registro['id']);
        Audit::log('eliminar', 'historial', 'Registro eliminado de ' . $s['titulo'], (int) $registro['id'], (int) $paciente['id'], ['seccion' => $p['sec']]);
        $this->flash('success', ucfirst_mb($s['singular']) . ' eliminado.');
        $this->redirect("pacientes/{$paciente['id']}#{$p['sec']}");
    }

    /** Una alergia grave nueva (o que pasa a grave) se avisa a los médicos que atenderán al paciente. */
    private function avisarAlergia(string $sec, array $d, array $paciente, ?array $antes): void
    {
        if ($sec !== 'alergias' || ($d['severidad'] ?? '') !== 'grave' || empty($d['activa'])) {
            return;
        }
        if ($antes && $antes['severidad'] === 'grave' && $antes['activa']) {
            return;
        }
        \App\Domain\Notificaciones::aMedicosDe((int) $paciente['id'], 'alergia_grave', 'Alergia grave: ' . $d['agente'], nombre_completo($paciente) . ($d['reaccion'] ? ', ' . $d['reaccion'] : ''), '/pacientes/' . $paciente['id'] . '#alergias', 'critico');
    }

    /** @return array{0:array,1:array} paciente accesible y definición de la sección */
    private function context(array $p): array
    {
        $s = HistorialSections::get($p['sec']);
        if (!$s) {
            throw new HttpException(404);
        }
        $paciente = Policy::patient((int) $p['id']);
        return [$paciente, $s];
    }

    private function record(array $s, array $paciente, int $rid): array
    {
        $registro = HistorialRepository::find($s, (int) $paciente['id'], $rid);
        if (!$registro) {
            throw new HttpException(404);
        }
        return $registro;
    }

    private function validate(Request $r, array $s): Validator
    {
        $v = Validator::make($r->post, HistorialSections::rules($s), HistorialSections::labels($s));
        $c = $v->clean();
        // Coherencia entre fechas (también protegida por restricciones CHECK en MySQL).
        if (isset($c['proxima_dosis'], $c['fecha_aplicacion']) && !$v->errors() && $c['proxima_dosis'] < $c['fecha_aplicacion']) {
            $v->addError('proxima_dosis', 'La próxima dosis no puede ser anterior a la fecha de aplicación.');
        }
        if (isset($c['fecha_inicio'], $c['fecha_fin']) && !$v->errors() && $c['fecha_fin'] < $c['fecha_inicio']) {
            $v->addError('fecha_fin', 'La fecha de fin no puede ser anterior al inicio.');
        }
        return $v;
    }
}
