<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Models\Nutriologo;
use App\Services\ServicioValidacionIdentidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
}

