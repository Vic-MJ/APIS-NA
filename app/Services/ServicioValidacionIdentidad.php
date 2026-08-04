<?php

namespace App\Services;

class ServicioValidacionIdentidad
{
    public function validarCedula(string $cedula): bool
    {
        return preg_match('/^[0-9]{7,8}$/', $cedula) === 1;
    }

    public function obtenerDatosCedula(string $cedula): ?array
    {
        if (!$this->validarCedula($cedula)) {
            return null;
        }
        //para las apis de la validacion de cedula, se usan dos alternativas

        //intento 1: SEP Solr (Oficial pero inestable, a veces no encuentra cedulas pero cuando lo hace, suele ser mas rapido)
        try {
            $urlSep = "http://search.sep.gob.mx/solr/cedulasCore/select?q=id:{$cedula}&wt=json";
            $respuesta = \Illuminate\Support\Facades\Http::timeout(5)
                ->withoutVerifying()
                ->get($urlSep);
            
            if ($respuesta->successful() && isset($respuesta->json()['response']['docs'][0])) {
                $doc = $respuesta->json()['response']['docs'][0];
                return [
                    'nombres' => $doc['nombre'] ?? '',
                    'paterno' => $doc['paterno'] ?? '',
                    'materno' => $doc['materno'] ?? '',
                    'profesion' => $doc['titulo'] ?? 'Desconocida',
                    'institucion' => $doc['institucion'] ?? 'N/A',
                    'anio' => $doc['anio'] ?? '',
                    'fuente' => 'SEP Oficial'
                ];
            }
        } catch (\Exception $e) {
            // sino encuentra el intento uno, pasamos al siguiente intento
        }

        //intento 2: Scraper BuhoLegal
        try {
            $urlBuho = "https://www.buholegal.com/{$cedula}/";
            $respuesta = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language' => 'es-ES,es;q=0.8,en-US;q=0.5,en;q=0.3',
            ])
            ->timeout(10)
            ->withoutVerifying()
            ->get($urlBuho);

            if ($respuesta->successful()) {
                $html = $respuesta->body();
                
                if (preg_match('/<div[^>]*class="[^"]*card-header[^"]*"[^>]*>\s*<h3[^>]*>(.*?)<\/h3>/s', $html, $matches)) {
                    $nombreCompleto = trim(strip_tags(html_entity_decode($matches[1])));
                    
                    if (!empty($nombreCompleto) && $nombreCompleto !== 'Iniciar sesión' && $nombreCompleto !== 'Acceso Denegado') {
                        preg_match('/Carrera<\/td>\s*<td[^>]*>\s*(.*?)\s*<\/td>/si', $html, $mProf);
                        preg_match('/Universidad<\/td>\s*<td[^>]*>\s*(.*?)\s*<\/td>/si', $html, $mInst);
                        
                        // Lógica básica para dividir nombre: [Nombres] [Paterno] [Materno]
                        $partes = explode(' ', $nombreCompleto);
                        $count = count($partes);
                        
                        $materno = ($count >= 3) ? array_pop($partes) : '';
                        $paterno = ($count >= 2) ? array_pop($partes) : '';
                        $nombres = implode(' ', $partes);

                        return [
                            'nombres' => $nombres,
                            'paterno' => $paterno,
                            'materno' => $materno,
                            'profesion' => isset($mProf[1]) ? trim(strip_tags($mProf[1])) : 'Consultar en INE',
                            'institucion' => isset($mInst[1]) ? trim(strip_tags($mInst[1])) : 'N/A',
                            'fuente' => 'BuhoLegal (Directo)'
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function validarIdentidad(string $frentePath, string $reversoPath): array
    {
        $rutaAbsoluta = storage_path('app/' . $frentePath);
        
        if (!file_exists($rutaAbsoluta)) {
            return [
                'estado' => 'error_archivo',
                'mensaje' => "El archivo no existe en la ruta: {$rutaAbsoluta}",
                'nombres' => '', 'paterno' => '', 'materno' => ''
            ];
        }

        $tesseractCheck = shell_exec('tesseract --version 2>&1');
        if (strpos($tesseractCheck, 'tesseract') === false) {
            return [
                'estado' => 'error_sistema',
                'mensaje' => 'Tesseract OCR no está instalado. Ejecuta: sudo apt install tesseract-ocr tesseract-ocr-spa',
                'nombres' => '', 'paterno' => '', 'materno' => ''
            ];
        }

        // Intento 1: PSM 3 (Modo automático)
        $texto1 = shell_exec("tesseract \"{$rutaAbsoluta}\" stdout -l spa --psm 3 2>&1");
        
        // Intento 2: PSM 6 (Asume un bloque de texto uniforme)
        $texto2 = shell_exec("tesseract \"{$rutaAbsoluta}\" stdout -l spa --psm 6 2>&1");
        
        $textoFinal = strtoupper($texto1 . "\n" . $texto2);

        if (empty($textoFinal)) {
            return [
                'estado' => 'error_lectura',
                'mensaje' => 'Tesseract no pudo leer nada de la imagen.',
                'nombres' => '', 'paterno' => '', 'materno' => ''
            ];
        }

        // Normalización
        $buscar = ['Á', 'É', 'Í', 'Ó', 'Ú', '0', '1', '|'];
        $reemplazar = ['A', 'E', 'I', 'O', 'U', 'O', 'I', 'I'];
        $textoLimpio = str_replace($buscar, $reemplazar, $textoFinal);

        return [
            'estado' => 'procesado',
            'texto_ocr' => $textoLimpio,
            'mensaje' => 'Lectura completada.',
            'debug_raw' => $textoFinal
        ];
    }
}
