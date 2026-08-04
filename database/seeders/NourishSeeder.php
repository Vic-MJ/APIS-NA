<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Consulta;
use App\Models\Dieta;
use App\Models\Comida;
use App\Models\EquivalenciaAlimento;
use Illuminate\Support\Facades\File;

class NourishSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $jsonPath = base_path('../nourish.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error("No se encontró el archivo nourish.json en {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);

        User::truncate();
        Consulta::truncate();
        Dieta::truncate();
        Comida::truncate();
        EquivalenciaAlimento::truncate();

        foreach ($data['usuarios'] as $userData) {
            User::create([
                'name' => $userData['nombre'],
                'email' => $userData['correo'],
                'password' => bcrypt($userData['password']),
                'rol' => $userData['rol'],
                'paciente' => $userData['paciente'] ?? null,
                'wearables' => $userData['wearables'] ?? [],
                'rutinas' => $userData['rutinas'] ?? [],
            ]);
        }

        foreach ($data['nutriologos'] as $nutriData) {
            User::create([
                'name' => $nutriData['nombre'],
                'email' => "nutri_{$nutriData['id_usuario']}@example.com",
                'password' => bcrypt('123456'),
                'rol' => 'nutriologo',
                'cedula' => $nutriData['cedula_profesional'],
                'especialidad' => $nutriData['especialidad'],
                'universidad' => $nutriData['universidad'],
            ]);
        }

        foreach ($data['consultas'] as $consultaData) {
            Consulta::create($consultaData);
        }
        foreach ($data['dietas'] as $dietaData) {
            Dieta::create($dietaData);
        }

        foreach ($data['comidas'] as $comidaData) {
            Comida::create($comidaData);
        }

        foreach ($data['equivalencias_alimentos'] as $equivData) {
            EquivalenciaAlimento::create($equivData);
        }

        $this->command->info('Base de datos coentacda con éxito ');
    }
}
