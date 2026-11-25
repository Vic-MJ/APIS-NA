<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Nutriologo;
use App\Models\Configuracion;
use App\Services\ServicioValidacionIdentidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ControladorAutenticacion extends Controller
{
    protected ServicioValidacionIdentidad $servicioValidacion;

    public function __construct(ServicioValidacionIdentidad $servicioValidacion)
    {
        $this->servicioValidacion = $servicioValidacion;
    }

    public function registro(Request $request)
    {
        $reglas = [
            'nombres' => 'required|string|max:255',
            'apellido_p' => 'required|string|max:255',
            'apellido_m' => 'nullable|string|max:255',
            'correo' => 'required|string|email|max:255|unique:usuarios,correo',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*(),.?":{}|<>]/',
            ],
            'rol' => 'required|in:paciente,nutriologo',
        ];

        if ($request->rol === 'nutriologo') {
            $reglas['cedula'] = 'required|string';
            $reglas['tipo_cedula'] = 'nullable|string';
        }

        $validador = Validator::make($request->all(), $reglas);

        if ($validador->fails()) {
            return response()->json(['errores' => $validador->errors()], 422);
        }

        $usuario = Usuario::create([
            'nombre' => [
                'nombres' => $request->nombres,
                'apellido_p' => $request->apellido_p,
                'apellido_m' => $request->apellido_m,
            ],
            'correo' => $request->correo,
            'password' => Hash::make($request->password),
            'rol' => $request->rol,
            'cedula' => $request->cedula ?? null,
            'tipo_cedula' => $request->tipo_cedula ?? null,
            'estado_validacion' => $request->rol === 'nutriologo' ? 'aprobado' : 'n/a',
            'tipo_paciente' => $request->rol === 'paciente' ? 'free' : null,
            'correo_verificado' => true,
        ]);

        $token = $usuario->createToken('token_acceso')->plainTextToken;

        // Si es nutriólogo, creamos automáticamente su perfil en la colección de especialistas
        // Aseguramos que se guarden como arreglos reales de PHP para que MongoDB los reconozca como Objetos/Arrays
        if ($usuario->rol === 'nutriologo') {
            $datosNombre = is_string($usuario->nombre) ? json_decode($usuario->nombre, true) : $usuario->nombre;

            Nutriologo::updateOrCreate(
                ['usuario.id_usuario' => (string)$usuario->_id],
                [
                    'usuario' => [
                        'id_usuario' => (string)$usuario->_id,
                        'nombre' => $datosNombre
                    ],
                    'nombre' => $datosNombre,
                    'cedula_profesional' => (string)$usuario->cedula,
                    'especialidad' => (string)($usuario->tipo_cedula ?? 'Nutriólogo General'),
                    'universidad' => 'Pendiente de asignar',
                    'pacientes' => [] // Arreglo real vacío
                ]
            );
        }

        return response()->json([
            'mensaje' => 'Usuario registrado exitosamente',
            'usuario' => $usuario,
            'token' => $token,
        ], 201);
    }

    public function iniciarSesion(Request $request)
    {
        $request->validate([
            'correo' => 'required|string|email',
            'password' => 'required|string',
        ]);

        $correo = trim($request->correo);
        $password = $request->password;

        $usuario = Usuario::where('correo', $correo)->first();

        if (!$usuario || !Hash::check($password, $usuario->password)) {
            return response()->json([
                'mensaje' => 'Credenciales incorrectas.',
            ], 401);
        }

        $token = $usuario->createToken('token_acceso')->plainTextToken;

        // Sincronización proactiva del perfil de Nutriólogo al iniciar sesión
        if ($usuario->rol === 'nutriologo') {
            // Aseguramos que el nombre sea un arreglo PHP nativo
            $datosNombre = $usuario->nombre;
            while (is_string($datosNombre)) {
                $decoded = json_decode($datosNombre, true);
                if (json_last_error() !== JSON_ERROR_NONE) break;
                $datosNombre = $decoded;
            }

            Nutriologo::updateOrCreate(
                ['usuario.id_usuario' => (string)$usuario->_id],
                [
                    'usuario' => [
                        'id_usuario' => (string)$usuario->_id,
                        'nombre' => $datosNombre
                    ],
                    'nombre' => $datosNombre,
                    'cedula_profesional' => (string)$usuario->cedula,
                    'especialidad' => (string)($usuario->tipo_cedula ?? 'Nutriólogo General'),
                    'universidad' => 'Pendiente de asignar',
                    'pacientes' => [] // Forzamos arreglo real
                ]
            );
        }

        return response()->json([
            'mensaje' => 'Inicio de sesión exitoso.',
            'usuario' => $usuario,
            'token' => $token,
        ]);
    }

    public function consultarCedula(string $cedula)
    {
        $datos = $this->servicioValidacion->obtenerDatosCedula($cedula);

        if (!$datos) {
            return response()->json([
                'mensaje' => 'No se encontraron datos para la cédula proporcionada o el formato es incorrecto.'
            ], 404);
        }

        return response()->json($datos);
    }

    private function enviarCorreoResend(string $destinatario, string $asunto, string $htmlCorreo): void
    {
        $apiKey = env('RESEND_API_KEY');
        $remitente = env('RESEND_FROM', 'Nourish - Nutrición y Salud <soporte@nourishapp.com.mx>');

        $respuesta = Http::withToken($apiKey)
            ->timeout(15)
            ->acceptJson()
            ->post('https://api.resend.com/emails', [
                'from' => $remitente,
                'to' => [$destinatario],
                'subject' => $asunto,
                'html' => $htmlCorreo,
            ]);

        if (!$respuesta->successful()) {
            $detalle = $respuesta->json('message') ?? $respuesta->body();
            throw new \RuntimeException('Error de Resend (' . $respuesta->status() . '): ' . $detalle);
        }
    }

    public function solicitarRecuperacion(Request $request)
    {
        $request->validate([
            'correo' => 'required|string|email',
        ]);

        $correo = trim($request->correo);
        $usuario = Usuario::where('correo', $correo)->first();

        if (!$usuario) {
            return response()->json([
                'mensaje' => 'No encontramos ninguna cuenta registrada con ese correo electrónico.'
            ], 404);
        }

        $codigo = (string) random_int(100000, 999999);
        $usuario->codigo_recuperacion = $codigo;
        $usuario->codigo_recuperacion_expira = Carbon::now()->addMinutes(15)->toIso8601String();
        $usuario->save();

        $nombreDestinatario = is_array($usuario->nombre)
            ? trim(($usuario->nombre['nombres'] ?? '') . ' ' . ($usuario->nombre['apellido_p'] ?? ''))
            : 'Usuario Nourish';

        $htmlCorreo = "
        <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 20px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #1a237e; margin: 0; font-size: 26px; letter-spacing: 1px;'>NOURISH</h2>
                <p style='color: #64748b; font-size: 13px; margin-top: 4px;'>Tu salud, con elegancia</p>
            </div>
            <p style='color: #1e293b; font-size: 15px;'>Hola <strong>{$nombreDestinatario}</strong>,</p>
            <p style='color: #475569; font-size: 14px; line-height: 1.5;'>
                Recibimos una solicitud para restablecer la contraseña de tu cuenta en <strong>Nourish</strong>. Ingresa el siguiente código de verificación de 6 dígitos en la aplicación:
            </p>
            <div style='text-align: center; margin: 26px 0;'>
                <span style='display: inline-block; background-color: #fff1f2; color: #e11d48; border: 2px dashed #e11d48; font-size: 32px; font-weight: 800; letter-spacing: 8px; padding: 14px 28px; border-radius: 16px;'>
                    {$codigo}
                </span>
            </div>
            <p style='color: #64748b; font-size: 12px; text-align: center;'>
                Este código es válido por <strong>15 minutos</strong>. Si no solicitaste este cambio, puedes ignorar este correo de forma segura.
            </p>
        </div>";

        try {
            $this->enviarCorreoResend(
                $correo,
                'Código de verificación para recuperar tu contraseña - Nourish',
                $htmlCorreo
            );
        } catch (\Exception $e) {
            \Log::error('Error enviando correo Resend de recuperación: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'No se pudo enviar el correo de verificación: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'mensaje' => 'Hemos enviado un código de verificación de 6 dígitos a tu correo electrónico.'
        ]);
    }

    public function verificarCodigoRecuperacion(Request $request)
    {
        $request->validate([
            'correo' => 'required|string|email',
            'codigo' => 'required|string|size:6',
        ]);

        $correo = trim($request->correo);
        $codigo = trim($request->codigo);
        $usuario = Usuario::where('correo', $correo)->first();

        if (!$usuario || !$usuario->codigo_recuperacion) {
            return response()->json([
                'mensaje' => 'No hay una solicitud de recuperación activa para este correo.'
            ], 404);
        }

        if ($usuario->codigo_recuperacion !== $codigo) {
            return response()->json([
                'mensaje' => 'El código de verificación ingresado es incorrecto.'
            ], 422);
        }

        if ($usuario->codigo_recuperacion_expira && Carbon::now()->gt(Carbon::parse($usuario->codigo_recuperacion_expira))) {
            return response()->json([
                'mensaje' => 'El código de verificación ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        return response()->json([
            'mensaje' => 'Código verificado correctamente.',
            'valido' => true
        ]);
    }

    public function restablecerPassword(Request $request)
    {
        $validador = Validator::make($request->all(), [
            'correo' => 'required|string|email',
            'codigo' => 'required|string|size:6',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/[A-Z]/',
                'regex:/[0-9]/',
                'regex:/[!@#$%^&*(),.?":{}|<>]/',
            ],
        ]);

        if ($validador->fails()) {
            return response()->json(['errores' => $validador->errors()], 422);
        }

        $correo = trim($request->correo);
        $codigo = trim($request->codigo);
        $usuario = Usuario::where('correo', $correo)->first();

        if (!$usuario || $usuario->codigo_recuperacion !== $codigo) {
            return response()->json([
                'mensaje' => 'El código de verificación no es válido.'
            ], 422);
        }

        if ($usuario->codigo_recuperacion_expira && Carbon::now()->gt(Carbon::parse($usuario->codigo_recuperacion_expira))) {
            return response()->json([
                'mensaje' => 'El código de verificación ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        $usuario->password = Hash::make($request->password);
        $usuario->codigo_recuperacion = null;
        $usuario->codigo_recuperacion_expira = null;
        $usuario->save();

        return response()->json([
            'mensaje' => 'Tu contraseña ha sido actualizada exitosamente. Ya puedes iniciar sesión.'
        ]);
    }

    public function enviarCodigoVerificacionCorreo(Request $request)
    {
        $request->validate([
            'correo' => 'required|string|email|max:255',
            'nombres' => 'nullable|string|max:255',
        ]);

        $correo = strtolower(trim($request->correo));
        $usuarioExistente = Usuario::where('correo', $correo)->first();

        if ($usuarioExistente && ($usuarioExistente->correo_verificado ?? false)) {
            return response()->json([
                'mensaje' => 'Este correo electrónico ya está registrado en Nourish. Inicia sesión.'
            ], 422);
        }

        $codigo = (string) random_int(100000, 999999);
        $expiraIso = Carbon::now()->addMinutes(15)->toIso8601String();

        Configuracion::establecer(
            'verif_correo_' . $correo,
            json_encode(['codigo' => $codigo, 'expira' => $expiraIso]),
            'string'
        );

        if ($usuarioExistente) {
            $usuarioExistente->codigo_verificacion_correo = $codigo;
            $usuarioExistente->codigo_verificacion_expira = $expiraIso;
            $usuarioExistente->save();
        }

        $nombreDestinatario = trim($request->nombres ?? 'Nuevo Usuario Nourish');

        $htmlCorreo = "
        <div style='font-family: Arial, sans-serif; max-width: 520px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 20px; background-color: #ffffff;'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #1a237e; margin: 0; font-size: 26px; letter-spacing: 1px;'>NOURISH</h2>
                <p style='color: #64748b; font-size: 13px; margin-top: 4px;'>Tu salud, con elegancia</p>
            </div>
            <p style='color: #1e293b; font-size: 15px;'>¡Bienvenido(a), <strong>{$nombreDestinatario}</strong>!</p>
            <p style='color: #475569; font-size: 14px; line-height: 1.5;'>
                Estás a un paso de crear tu cuenta en <strong>Nourish</strong>. Para verificar tu correo electrónico, ingresa el siguiente código de 6 dígitos en la aplicación:
            </p>
            <div style='text-align: center; margin: 26px 0;'>
                <span style='display: inline-block; background-color: #fff1f2; color: #e11d48; border: 2px dashed #e11d48; font-size: 32px; font-weight: 800; letter-spacing: 8px; padding: 14px 28px; border-radius: 16px;'>
                    {$codigo}
                </span>
            </div>
            <p style='color: #64748b; font-size: 12px; text-align: center;'>
                Este código es válido por <strong>15 minutos</strong>.
            </p>
        </div>";

        try {
            $this->enviarCorreoResend(
                $correo,
                'Código de verificación de correo - Nourish',
                $htmlCorreo
            );
        } catch (\Exception $e) {
            \Log::error('Error enviando correo Resend de verificación de registro: ' . $e->getMessage());
            return response()->json([
                'mensaje' => 'No se pudo enviar el correo de verificación: ' . $e->getMessage()
            ], 500);
        }

        return response()->json([
            'mensaje' => 'Te hemos enviado un código de verificación de 6 dígitos a tu correo electrónico.'
        ]);
    }

    public function confirmarCodigoVerificacionCorreo(Request $request)
    {
        $request->validate([
            'correo' => 'required|string|email',
            'codigo' => 'required|string|size:6',
        ]);

        $correo = strtolower(trim($request->correo));
        $codigo = trim($request->codigo);

        $guardadoJson = Configuracion::obtener('verif_correo_' . $correo);
        $datosGuardados = is_string($guardadoJson) ? json_decode($guardadoJson, true) : null;
        $codigoEnConfig = $datosGuardados['codigo'] ?? null;
        $expiraEnConfig = $datosGuardados['expira'] ?? null;

        $usuario = Usuario::where('correo', $correo)->first();
        $codigoEnUsuario = $usuario ? $usuario->codigo_verificacion_correo : null;

        if ($codigoEnConfig !== $codigo && $codigoEnUsuario !== $codigo) {
            return response()->json([
                'mensaje' => 'El código de verificación es incorrecto o ha expirado.'
            ], 422);
        }

        if ($expiraEnConfig && Carbon::now()->gt(Carbon::parse($expiraEnConfig))) {
            return response()->json([
                'mensaje' => 'El código de verificación ha expirado. Solicita uno nuevo.'
            ], 422);
        }

        Configuracion::where('clave', 'verif_correo_' . $correo)->delete();

        if ($usuario) {
            $usuario->correo_verificado = true;
            $usuario->codigo_verificacion_correo = null;
            $usuario->codigo_verificacion_expira = null;
            $usuario->save();
        }

        return response()->json([
            'mensaje' => 'Correo electrónico verificado correctamente.',
            'verificado' => true
        ]);
    }
}

