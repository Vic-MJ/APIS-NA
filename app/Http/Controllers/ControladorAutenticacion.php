<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
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
            $reglas['ine_frente'] = 'required|file|mimes:jpeg,png,jpg,pdf|max:5120';
            $reglas['ine_reverso'] = 'required|file|mimes:jpeg,png,jpg,pdf|max:5120';
        }

        $validador = Validator::make($request->all(), $reglas);

        if ($validador->fails()) {
            return response()->json(['errores' => $validador->errors()], 422);
        }

        $estadoValidacion = 'pendiente';
        $rutaFrente = null;
        $rutaReverso = null;

        if ($request->rol === 'nutriologo') {
            if (empty($request->cedula)) {
                return response()->json([
                    'mensaje' => 'La cédula profesional es obligatoria para registrarse como Nutriólogo.'
                ], 422);
            }

            if (!$this->servicioValidacion->validarCedula($request->cedula)) {
                return response()->json([
                    'mensaje' => 'La cédula profesional no es válida (Se requieren 7 u 8 dígitos numéricos).'
                ], 422);
            }

            $rutaFrente = $request->file('ine_frente')->store('identidades', 'local');
            $rutaReverso = $request->file('ine_reverso')->store('identidades', 'local');

            $resultadoIdentidad = $this->servicioValidacion->validarIdentidad($rutaFrente, $rutaReverso);
            $estadoValidacion = $resultadoIdentidad['estado'];

            $nombreIne = mb_strtoupper("{$resultadoIdentidad['nombres']} {$resultadoIdentidad['paterno']} {$resultadoIdentidad['materno']}");
            $nombreRequest = mb_strtoupper("{$request->nombres} {$request->apellido_p} {$request->apellido_m}");

            if ($nombreIne !== $nombreRequest) {
                return response()->json([
                    'mensaje' => "El nombre en la INE ({$nombreIne}) no coincide con los datos proporcionados ({$nombreRequest})."
                ], 422);
            }
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
            'ine_reverso' => $rutaReverso,
            'estado_validacion' => $estadoValidacion,
            'tipo_paciente' => $request->rol === 'paciente' ? 'free' : null,
        ]);

        $token = $usuario->createToken('token_acceso')->plainTextToken;

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

    public function validarIne(Request $request)
    {
        $reglas = [
            'ine_frente' => 'required|file|mimes:jpeg,png,jpg|max:5120',
            'nombres' => 'required|string',
            'apellido_p' => 'required|string',
        ];

        $validador = Validator::make($request->all(), $reglas);
        if ($validador->fails()) {
            return response()->json(['errores' => $validador->errors()], 422);
        }

        $rutaFrente = $request->file('ine_frente')->store('identidades_temp', 'local');
        $rutaReverso = $request->hasFile('ine_reverso') ? $request->file('ine_reverso')->store('identidades_temp', 'local') : '';

        $resultadoIdentidad = $this->servicioValidacion->validarIdentidad($rutaFrente, $rutaReverso);

        if ($resultadoIdentidad['estado'] === 'error_sistema') {
             return response()->json([
                'valido' => false,
                'mensaje' => $resultadoIdentidad['mensaje']
            ], 500);
        }

        $textoLeido = $resultadoIdentidad['texto_ocr'] ?? '';
        
        $nombreCompleto = mb_strtoupper("{$request->nombres} {$request->apellido_p} {$request->apellido_m}");
        $nombreCompleto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú'], ['A', 'E', 'I', 'O', 'U'], $nombreCompleto);
        
        $partesNombre = array_filter(explode(' ', $nombreCompleto), function($p) {
            return strlen($p) > 2; 
        });

        $faltantes = [];
        foreach ($partesNombre as $parte) {
            if (strpos($textoLeido, $parte) === false) {
                $faltantes[] = $parte;
            }
        }

        if (!empty($faltantes)) {
            return response()->json([
                'valido' => false,
                'mensaje' => "No se pudo validar la identidad. No se encontraron: " . implode(', ', $faltantes),
                'debug_lectura' => $textoLeido
            ], 422);
        }

        return response()->json([
            'valido' => true,
            'mensaje' => 'Validación de identidad exitosa. Todos los campos coinciden.',
            'leido' => $nombreCompleto
        ]);
    }
}

