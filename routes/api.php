<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControladorAutenticacion;
use App\Http\Controllers\ControladorConsulta;
use App\Http\Controllers\ControladorDieta;
use App\Http\Controllers\ControladorComida;
use App\Http\Controllers\ControladorEquivalenciaAlimento;
use App\Http\Controllers\ControladorRutina;
use App\Http\Controllers\ControladorNutriologo;
use App\Http\Controllers\ControladorPaciente;
use App\Http\Controllers\ControladorWearable;
use App\Http\Controllers\ControladorActividad;
use App\Http\Controllers\ControladorAdministrador;
use App\Http\Controllers\ChatControlador;
use App\Http\Controllers\NotificacionControlador;
use App\Http\Controllers\PerfilControlador;

Route::middleware('throttle:5,1')->post('/login', [ControladorAutenticacion::class, 'iniciarSesion']);
// Rutas de Autenticación (Públicas)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/registro', [ControladorAutenticacion::class, 'registro']);
    Route::get('/validar-cedula/{cedula}', [ControladorAutenticacion::class, 'consultarCedula']);
});

// Rutas Protegidas por Sanctum
Route::middleware('auth:sanctum')->group(function () {

    Route::get('/usuario-actual', function (Request $request) {
        return $request->user();
    });

    // --- BLOQUE ADMINISTRATIVO ---
    Route::prefix('admin')->middleware('rol:admin')->group(function () {
        Route::get('/estadisticas', [ControladorAdministrador::class, 'estadisticas']);
        Route::get('/respaldos', [ControladorAdministrador::class, 'listarRespaldos']);
        Route::post('/respaldos/crear', [ControladorAdministrador::class, 'crearRespaldo']);
        Route::get('/respaldos/descargar/{archivo}', [ControladorAdministrador::class, 'descargarRespaldo'])->name('descargar-respaldo');
        Route::delete('/respaldos/eliminar/{archivo}', [ControladorAdministrador::class, 'eliminarRespaldo']);

        // Rutas de gestión de Base de Datos
        Route::get('/db/colecciones', [ControladorAdministrador::class, 'listarColecciones']);
        Route::get('/db/salud', [ControladorAdministrador::class, 'estadoSaludBd']);
        Route::post('/db/reparar-secuencias', [ControladorAdministrador::class, 'repararSecuencias']);

        // Rutas de Ajustes de Sistema
        Route::get('/configuraciones', [ControladorAdministrador::class, 'obtenerConfiguraciones']);
        Route::post('/configuraciones', [ControladorAdministrador::class, 'actualizarConfiguraciones']);
        Route::post('/mantenimiento/ejecutar', [ControladorAdministrador::class, 'ejecutarTareaMantenimiento']);
    });

    // --- BLOQUE CLÍNICO / DATOS ---
    // Solo admins o nutriólogos pueden gestionar pacientes y consultas
    Route::middleware('rol:admin,nutriologo')->group(function () {
        Route::apiResource('consultas', ControladorConsulta::class);
        Route::apiResource('pacientes', ControladorPaciente::class);
    });

    // Rutas accesibles por todos los autenticados (con lógica interna por ID de usuario)
    Route::apiResource('nutriologos', ControladorNutriologo::class);
    Route::post('/pacientes/vincular', [ControladorPaciente::class, 'vincularNutriologo']);
    Route::post('/pacientes/desvincular', [ControladorPaciente::class, 'desvincularNutriologo']);
    Route::apiResource('dietas', ControladorDieta::class);
    Route::apiResource('comidas', ControladorComida::class);
    Route::apiResource('actividades', ControladorActividad::class);
    Route::apiResource('equivalencias', ControladorEquivalenciaAlimento::class);
    Route::apiResource('rutinas', ControladorRutina::class);
    Route::apiResource('wearables', ControladorWearable::class);

    // Rutas Extra (Consultas Específicas)
    Route::prefix('extra')->group(function () {
        Route::get('/consultas/usuario/{usuarioId}', [ControladorConsulta::class, 'porUsuario']);
        Route::get('/dietas/activas/{usuarioId}', [ControladorDieta::class, 'activas']);
        Route::get('/comidas/dieta/{dietaId}/dia/{dia}', [ControladorComida::class, 'porDietaYDia']);
        Route::get('/equivalencias/buscar', [ControladorEquivalenciaAlimento::class, 'buscar']);
        Route::get('/equivalencias/edamam', [ControladorEquivalenciaAlimento::class, 'buscarEquivalentes']);
        Route::get('/rutinas/dificultad/{nivel}', [ControladorRutina::class, 'porNivel']);
        Route::get('/nutriologos/especialidad/{especialidad}', [ControladorNutriologo::class, 'porEspecialidad']);
        Route::get('/pacientes/usuario/{idUsuario}', [ControladorPaciente::class, 'buscar']);
        Route::get('/wearables/ultimo-estado/{usuarioId}', [ControladorWearable::class, 'ultimaSincronizacion']);
        Route::get('/alimentos/buscar', [ControladorComida::class, 'buscarAlimento']);
    });

    // --- BLOQUE CHAT Y NOTIFICACIONES ---
    Route::prefix('chat')->group(function () {
        Route::get('/conversaciones', [ChatControlador::class, 'obtenerConversaciones']);
        Route::get('/mensajes/{otroUsuarioId}', [ChatControlador::class, 'obtenerMensajes']);
        Route::post('/enviar', [ChatControlador::class, 'enviarMensaje']);
        Route::get('/disponibles/nutriologos', [ChatControlador::class, 'obtenerNutriologos']);
        Route::get('/disponibles/pacientes', [ChatControlador::class, 'obtenerPacientes']);
    });

    Route::prefix('notificaciones')->group(function () {
        Route::get('/', [NotificacionControlador::class, 'index']);
        Route::get('/conteo', [NotificacionControlador::class, 'conteoNoLeidas']);
        Route::post('/leida/{id}', [NotificacionControlador::class, 'marcarComoLeida']);
        Route::post('/leer-todas', [NotificacionControlador::class, 'marcarTodasComoLeidas']);
        Route::delete('/{id}', [NotificacionControlador::class, 'eliminar']);
    });

    Route::prefix('perfil')->group(function () {
        Route::get('/', [PerfilControlador::class, 'obtenerPerfil']);
        Route::post('/actualizar', [PerfilControlador::class, 'actualizarPerfil']);
        Route::post('/identificacion', [PerfilControlador::class, 'actualizarIdentificacion']);
    });
});
