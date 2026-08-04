<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EquivalenciaAlimento;

class EquivalenciaAlimentoSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la colección para evitar duplicados si se vuelve a correr
        EquivalenciaAlimento::truncate();

        $grupos = [
            [
                'grupo_alimenticio' => 'Verduras',
                'alimentos' => [
                    ['nombre' => 'Lechuga, Germen de alfalfa', 'cantidad_equivalente' => '3', 'unidad' => 'tzas', 'calorias' => 25],
                    ['nombre' => 'Acelgas, Espinaca, Cilantro, Verdolaga', 'cantidad_equivalente' => '2', 'unidad' => 'tzas', 'calorias' => 25],
                    ['nombre' => 'Pepino, Apio, Champiñón', 'cantidad_equivalente' => '1 ½', 'unidad' => 'tzas', 'calorias' => 25],
                    ['nombre' => 'Ejote, Brócoli, Coliflor, Apio, Nopal, Germen de soya, Flor de calabaza', 'cantidad_equivalente' => '1', 'unidad' => 'tza', 'calorias' => 25],
                    ['nombre' => 'Jícama, Cebolla, Zanahoria, Betabel, Calabaza, Jitomate, Hongo Portobello', 'cantidad_equivalente' => '½ tza o 1', 'unidad' => 'pza', 'calorias' => 25],
                    ['nombre' => 'Chayote, Pimiento', 'cantidad_equivalente' => '½', 'unidad' => 'pza', 'calorias' => 25],
                    ['nombre' => 'Chícharo', 'cantidad_equivalente' => '¼', 'unidad' => 'tza', 'calorias' => 25]
                ]
            ],
            [
                'grupo_alimenticio' => 'Frutas',
                'alimentos' => [
                    ['nombre' => 'Lima, Ciruela, Guayaba', 'cantidad_equivalente' => '3', 'unidad' => 'pzas', 'calorias' => 60],
                    ['nombre' => 'Durazno, Mandarinas, Naranja, Tuna', 'cantidad_equivalente' => '2', 'unidad' => 'pzas', 'calorias' => 60],
                    ['nombre' => 'Manzana, Toronja, Guanábana', 'cantidad_equivalente' => '1', 'unidad' => 'pza', 'calorias' => 60],
                    ['nombre' => 'Plátano, Pera, Mango', 'cantidad_equivalente' => '½', 'unidad' => 'pza', 'calorias' => 60],
                    ['nombre' => 'Uvas', 'cantidad_equivalente' => '18', 'unidad' => 'pzas', 'calorias' => 60],
                    ['nombre' => 'Melón, Fresa, Sandía, Papaya, Piña, Frambuesa, Zarzamora', 'cantidad_equivalente' => '1', 'unidad' => 'tza', 'calorias' => 60],
                    ['nombre' => 'Kiwi', 'cantidad_equivalente' => '1 ½', 'unidad' => 'pza', 'calorias' => 60],
                    ['nombre' => 'Pasas', 'cantidad_equivalente' => '10', 'unidad' => 'pzas', 'calorias' => 60]
                ]
            ],
            [
                'grupo_alimenticio' => 'Cereales',
                'alimentos' => [
                    ['nombre' => 'Tortilla de maíz, Pan de Caja, Pan Tostado', 'cantidad_equivalente' => '1', 'unidad' => 'pza', 'calorias' => 70],
                    ['nombre' => 'Tortilla de Harina, Papa, Bolillo sin Migajón, Medialuna, Pan para Hamburguesa', 'cantidad_equivalente' => '½', 'unidad' => 'pza', 'calorias' => 70],
                    ['nombre' => 'Arroz Cocido', 'cantidad_equivalente' => '¼', 'unidad' => 'tza', 'calorias' => 70],
                    ['nombre' => 'Pasta / Espagueti, Granos de Elote, Avena en hojuelas', 'cantidad_equivalente' => '½', 'unidad' => 'tza', 'calorias' => 70],
                    ['nombre' => 'Granola, Amaranto', 'cantidad_equivalente' => '3', 'unidad' => 'cdas', 'calorias' => 70],
                    ['nombre' => 'Palomitas de Maíz Naturales', 'cantidad_equivalente' => '2', 'unidad' => 'tzas', 'calorias' => 70],
                    ['nombre' => 'Galletas Marías / Animalitos, Galleta Habanera / Salada', 'cantidad_equivalente' => '5', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Tostadas Deshidratadas', 'cantidad_equivalente' => '2', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Camote Cocido', 'cantidad_equivalente' => '1/3', 'unidad' => 'tza', 'calorias' => 70]
                ]
            ],
            [
                'grupo_alimenticio' => 'Alimentos de Origen Animal',
                'alimentos' => [
                    ['nombre' => 'Bistec de pollo, Bistec de res, Molida de Res/Cerdo, Salmón', 'cantidad_equivalente' => '30', 'unidad' => 'g', 'calorias' => 55],
                    ['nombre' => 'Pescado', 'cantidad_equivalente' => '40', 'unidad' => 'g', 'calorias' => 55],
                    ['nombre' => 'Chuleta Ahumada', 'cantidad_equivalente' => '½', 'unidad' => 'pza', 'calorias' => 75],
                    ['nombre' => 'Lata de atún', 'cantidad_equivalente' => '1/3', 'unidad' => 'lata', 'calorias' => 55],
                    ['nombre' => 'Camarón', 'cantidad_equivalente' => '6', 'unidad' => 'pzas', 'calorias' => 55],
                    ['nombre' => 'Chicharrón, Requesón, Queso Cottage', 'cantidad_equivalente' => '12g / 3½ cdas / 40g', 'unidad' => 'porción', 'calorias' => 75],
                    ['nombre' => 'Panela, Queso fresco, Queso Oaxaca', 'cantidad_equivalente' => '30', 'unidad' => 'g', 'calorias' => 75],
                    ['nombre' => 'Huevo completo', 'cantidad_equivalente' => '1', 'unidad' => 'pza', 'calorias' => 75],
                    ['nombre' => 'Claras de Huevo', 'cantidad_equivalente' => '2 pzas / 1½ Cda', 'unidad' => 'porción', 'calorias' => 35],
                    ['nombre' => 'Jamón de pavo', 'cantidad_equivalente' => '2', 'unidad' => 'reb', 'calorias' => 40],
                    ['nombre' => 'Salchicha de Pavo', 'cantidad_equivalente' => '1', 'unidad' => 'pza', 'calorias' => 45]
                ]
            ],
            [
                'grupo_alimenticio' => 'Leche',
                'alimentos' => [
                    ['nombre' => 'Leche light / Entera / Soya, Yogurt Natural', 'cantidad_equivalente' => '1', 'unidad' => 'tza', 'calorias' => 110]
                ]
            ],
            [
                'grupo_alimenticio' => 'Leguminosas',
                'alimentos' => [
                    ['nombre' => 'Frijol, Lentejas, Habas, Garbanzo, Soya', 'cantidad_equivalente' => '½', 'unidad' => 'tza', 'calorias' => 120]
                ]
            ],
            [
                'grupo_alimenticio' => 'Grasas Con proteína',
                'alimentos' => [
                    ['nombre' => 'Almendras', 'cantidad_equivalente' => '10', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Cacahuates Salados', 'cantidad_equivalente' => '14', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Mitades de nuez', 'cantidad_equivalente' => '7', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Chía', 'cantidad_equivalente' => '5', 'unidad' => 'cditas', 'calorias' => 70],
                    ['nombre' => 'Pistaches', 'cantidad_equivalente' => '18', 'unidad' => 'pzas', 'calorias' => 70],
                    ['nombre' => 'Pepitas de calabaza', 'cantidad_equivalente' => '60 pzas / 12g', 'unidad' => 'porción', 'calorias' => 70]
                ]
            ],
            [
                'grupo_alimenticio' => 'Grasas Sin proteína',
                'alimentos' => [
                    ['nombre' => 'Aceite vegetal (Oliva, Soya, Canola), Mayonesa', 'cantidad_equivalente' => '1', 'unidad' => 'cdita', 'calorias' => 45],
                    ['nombre' => 'Crema', 'cantidad_equivalente' => '1', 'unidad' => 'cda', 'calorias' => 45],
                    ['nombre' => 'Aguacate', 'cantidad_equivalente' => '1/3', 'unidad' => 'pza', 'calorias' => 45]
                ]
            ],
            [
                'grupo_alimenticio' => 'Alimentos Libres',
                'alimentos' => [
                    ['nombre' => 'Gelatina Light, Jícama, Pepino, Lechuga, Espinaca', 'cantidad_equivalente' => 'Libre', 'unidad' => 'Consumo', 'calorias' => 0]
                ]
            ]
        ];

        foreach ($grupos as $grupo) {
            EquivalenciaAlimento::create($grupo);
        }
    }
}