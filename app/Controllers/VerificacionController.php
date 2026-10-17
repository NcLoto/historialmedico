<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Domain\Impresos;

/**
 * Verificación pública de autenticidad. Muestra solo lo necesario para confirmar que el documento fue emitido
 * por el hospital: tipo, fecha, emisor e iniciales del paciente. Nunca expone contenido clínico.
 */
final class VerificacionController extends Controller
{
    public function form(Request $r): void
    {
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $r->str('codigo')));
        if ($codigo !== '') {
            if (strlen($codigo) === 12) {
                $this->redirect('verificar/' . $codigo);
            }
            $this->flash('error', 'El código tiene 12 caracteres (letras y números). Revise el pie del documento.');
        }
        $this->view('verificar/form', ['title' => 'Verificar documento'], 'auth');
    }

    public function show(Request $r, array $p): void
    {
        $codigo = strtoupper($p['codigo']);
        $d = Database::one(
            'SELECT e.codigo, e.tipo, e.titulo, e.emitido_por_nombre, e.creado_en, p.nombres, p.apellidos, p.codigo AS pcodigo
               FROM documentos_emitidos e LEFT JOIN pacientes p ON p.id = e.paciente_id WHERE e.codigo = ?',
            [$codigo]
        );
        $this->view('verificar/show', [
            'title' => 'Verificación de documento', 'd' => $d, 'codigo' => Impresos::codigoLegible($codigo),
            'tipo' => $d ? (Impresos::TIPOS[$d['tipo']] ?? 'Documento') : null,
        ], 'auth');
    }
}
