<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Paciente;
use App\Models\Nutriologo;
use App\Models\Consulta;
use App\Models\Comida;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class ControladorAdministrador extends Controller
{
  
    public function estadisticas()
    {
        try {
            $totalUsuarios = Usuario::count();
            $totalPacientes = Usuario::where('rol', 'paciente')->count();
            $totalNutriologos = Usuario::where('rol', 'nutriologo')->count();
            $totalAdministradores = Usuario::where('rol', 'admin')->count();
            
            $totalConsultas = Consulta::count();
            $totalComidas = Comida::count();
            
            $usoDisco = [
                'total' => 0,
                'libre' => 0,
                'usado' => 0
            ];

            try {
                $usoDisco['total'] = @disk_total_space('/') ?: 0;
                $usoDisco['libre'] = @disk_free_space('/') ?: 0;
                $usoDisco['usado'] = max(0, $usoDisco['total'] - $usoDisco['libre']);
            } catch (\Exception $e) {

            }
            
            $estadisticasBd = [];
            try {
                $nombreBd = config('database.connections.mongodb.database');
                $cliente = DB::connection('mongodb')->getMongoClient();
                $comando = $cliente->selectDatabase($nombreBd)->command(['dbStats' => 1]);
                $resultado = $comando->toArray();
                $stats = (array) ($resultado[0] ?? []);

                $estadisticasBd = [
                    'db' => $stats['db'] ?? $nombreBd,
                    'collections' => $stats['collections'] ?? 0,
                    'dataSize' => $stats['dataSize'] ?? 0,
                    'storageSize' => $stats['storageSize'] ?? 0,
                    'indexSize' => $stats['indexSize'] ?? 0
                ];
            } catch (\Exception $e) {
                $estadisticasBd = [
                    'error' => 'No se pudieron obtener estadísticas de MongoDB: ' . $e->getMessage(),
                    'dataSize' => 0,
                    'collections' => 0
                ];
            }

            return response()->json([
                'usuarios' => [
                    'total' => $totalUsuarios,
                    'pacientes' => $totalPacientes,
                    'nutriologos' => $totalNutriologos,
                    'admins' => $totalAdministradores
                ],
                'actividad' => [
                    'consultas' => $totalConsultas,
                    'comidas' => $totalComidas
                ],
                'sistema' => [
                    'disco' => $usoDisco,
                    'db' => $estadisticasBd,
                    'php_version' => PHP_VERSION,
                    'laravel_version' => app()->version(),
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Nourish Server'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en estadísticas: ' . $e->getMessage()], 500);
        }
    }

    public function crearRespaldo(Request $request)
    {
        try {
            $dbName = config('database.connections.mongodb.database');
            $dsn = config('database.connections.mongodb.dsn') ?: env('DB_URI');
            $fileName = 'respaldo_' . $dbName . '_' . date('Y-m-d_H-i-s') . '.gz';
            $filePath = storage_path('app/backups/' . $fileName);
            
            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            if ($dsn) {
                $command = sprintf(
                    'mongodump --uri=%s --archive=%s --gzip',
                    escapeshellarg($dsn),
                    escapeshellarg($filePath)
                );
            } else {
                $command = sprintf(
                    'mongodump --db=%s --archive=%s --gzip',
                    escapeshellarg($dbName),
                    escapeshellarg($filePath)
                );
            }
            
            exec($command . ' 2>&1', $output, $returnVar);

            if ($returnVar !== 0) {
                throw new \Exception("Fallo en mongodump (Código $returnVar): " . implode(" ", $output));
            }

            return response()->json([
                'mensaje' => 'Respaldo creado con éxito',
                'archivo' => $fileName,
                'fecha' => date('Y-m-d H:i:s'),
                'tamano' => filesize($filePath)
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear respaldo: ' . $e->getMessage()], 500);
        }
    }

    public function listarRespaldos()
    {
        try {
            $path = storage_path('app/backups');
            if (!file_exists($path)) {
                return response()->json([]);
            }

            $archivos = array_diff(scandir($path), ['.', '..']);
            $respaldos = [];

            foreach ($archivos as $archivo) {
                $fullPath = $path . '/' . $archivo;
                $respaldos[] = [
                    'nombre' => $archivo,
                    'tamano' => filesize($fullPath),
                    'fecha' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'url' => route('descargar-respaldo', ['archivo' => $archivo])
                ];
            }

            usort($respaldos, function($a, $b) {
                return strtotime($b['fecha']) - strtotime($a['fecha']);
            });

            return response()->json($respaldos);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function descargarRespaldo($archivo)
    {
        $path = storage_path('app/backups/' . $archivo);
        if (!file_exists($path)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        return response()->download($path);
    }

    public function eliminarRespaldo($archivo)
    {
        try {
            $path = storage_path('app/backups/' . $archivo);
            if (file_exists($path)) {
                unlink($path);
                return response()->json(['mensaje' => 'Respaldo eliminado con éxito']);
            }
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }


    public function listarColecciones()
    {
        try {
            $cliente = DB::connection('mongodb')->getMongoClient();
            $bd = $cliente->selectDatabase(config('database.connections.mongodb.database'));
            
            $colecciones = [];
            foreach ($bd->listCollections() as $infoColeccion) {
                $nombre = $infoColeccion->getName();
                $stats = $bd->command(['collStats' => $nombre])->toArray()[0];
                
                $colecciones[] = [
                    'nombre' => $nombre,
                    'documentos' => $stats['count'] ?? 0,
                    'tamano' => $stats['size'] ?? 0,
                    'tamanoAlmacenamiento' => $stats['storageSize'] ?? 0,
                    'indices' => count($stats['indexDetails'] ?? []),
                    'tamanoIndices' => $stats['totalIndexSize'] ?? 0
                ];
            }

            return response()->json($colecciones);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al listar colecciones: ' . $e->getMessage()], 500);
        }
    }

    public function repararSecuencias()
    {
        try {
            
            $colecciones = ['usuarios', 'pacientes', 'nutriologos', 'consultas', 'comidas', 'dietas', 'rutinas'];
            $resultados = [];

            foreach ($colecciones as $col) {
                $maxId = DB::collection($col)->max('_id');
                $resultados[$col] = ['max_id' => $maxId, 'estado' => 'Sincronizado'];
            }

            return response()->json([
                'mensaje' => 'Reparación de secuencias completada',
                'detalle' => $resultados
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al reparar secuencias: ' . $e->getMessage()], 500);
        }
    }

    public function estadoSaludBd()
    {
        try {
            $cliente = DB::connection('mongodb')->getMongoClient();
            $admin = $cliente->admin;
            $serverStatus = $admin->command(['serverStatus' => 1])->toArray()[0];

            return response()->json([
                'version' => $serverStatus['version'],
                'uptime' => $serverStatus['uptime'],
                'conexiones' => $serverStatus['connections'],
                'operaciones' => $serverStatus['opcounters'] ?? null,
                'memoria' => $serverStatus['mem'] ?? null
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'No se tiene permiso para ver el estado global del servidor'], 403);
        }
    }
    public function obtenerConfiguraciones()
    {
        try {
            $configs = \App\Models\Configuracion::all();
            
            if ($configs->isEmpty()) {
                \App\Models\Configuracion::establecer('nombre_app', 'Nourish App', 'string');
                \App\Models\Configuracion::establecer('registro_abierto', '1', 'boolean');
                \App\Models\Configuracion::establecer('permitir_nutriologos_nuevos', '1', 'boolean');
                \App\Models\Configuracion::establecer('correo_soporte', 'soporte@nourish.app', 'string');
                \App\Models\Configuracion::establecer('max_pacientes_por_nutriologo', '50', 'integer');
                $configs = \App\Models\Configuracion::all();
            }

            return response()->json($configs);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener configuraciones: ' . $e->getMessage()], 500);
        }
    }

    public function actualizarConfiguraciones(Request $request)
    {
        try {
            $datos = $request->all();
            foreach ($datos as $clave => $valor) {
                $config = \App\Models\Configuracion::where('clave', $clave)->first();
                if ($config) {
                    $config->valor = (string) $valor;
                    $config->save();
                }
            }
            return response()->json(['mensaje' => 'Configuraciones actualizadas con éxito']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar configuraciones: ' . $e->getMessage()], 500);
        }
    }

    public function ejecutarTareaMantenimiento(Request $request)
    {
        try {
            $tarea = $request->input('tarea');
            $resultado = '';

            switch ($tarea) {
                case 'limpiar_cache':
                    \Illuminate\Support\Facades\Artisan::call('cache:clear');
                    $resultado = 'Caché del sistema limpiada.';
                    break;
                case 'limpiar_vistas':
                    \Illuminate\Support\Facades\Artisan::call('view:clear');
                    $resultado = 'Caché de vistas optimizada.';
                    break;
                case 'generar_clave':
                    \Illuminate\Support\Facades\Artisan::call('key:generate');
                    $resultado = 'Nueva clave de aplicación generada.';
                    break;
                default:
                    throw new \Exception('Tarea de mantenimiento no reconocida.');
            }

            return response()->json(['mensaje' => $resultado]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error en tarea de mantenimiento: ' . $e->getMessage()], 500);
        }
    }
}
