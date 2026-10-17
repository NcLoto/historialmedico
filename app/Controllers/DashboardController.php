<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Repositories\DashboardRepository;
use App\Repositories\MedicoRepository;
use App\Repositories\PacienteRepository;

final class DashboardController extends Controller
{
    public function index(Request $r): void
    {
        $u = Auth::user();
        match ($u['rol_codigo']) {
            'paciente' => $this->paciente($u),
            'medico'   => $this->medico($u),
            default    => $this->admin(),
        };
    }

    private function paciente(array $u): void
    {
        $pid = Auth::patientId();
        $paciente = $pid ? PacienteRepository::find($pid) : null;
        if (!$paciente) {
            $this->view('dashboard/paciente_sin_ficha', ['title' => 'Inicio']);
            return;
        }
        $this->view('dashboard/paciente', [
            'title'    => 'Inicio',
            'paciente' => $paciente,
            'datos'    => DashboardRepository::paciente($pid),
            'resumen'  => PacienteRepository::clinicalSummary($pid),
        ]);
    }

    private function medico(array $u): void
    {
        $medico = MedicoRepository::byUser((int) $u['id']);
        if (!$medico) {
            $this->view('dashboard/medico_sin_perfil', ['title' => 'Dashboard']);
            return;
        }
        $this->view('dashboard/medico', [
            'title'  => 'Dashboard',
            'medico' => $medico,
            'datos'  => DashboardRepository::medico((int) $medico['id'], (int) $u['id']),
        ]);
    }

    private function admin(): void
    {
        $this->view('dashboard/admin', ['title' => 'Dashboard', 'datos' => DashboardRepository::admin()]);
    }
}
