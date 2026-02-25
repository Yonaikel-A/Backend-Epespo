<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Usuario;

class UsuarioSeeder extends Seeder
{
    public function run(): void
    {
        // Crear usuario admin
        Usuario::updateOrCreate(
            ['correo' => 'alonzopico13@gmail.com'],
            [
                'nombre' => 'Miguel',
                'contrasena' => '123456', // se encripta affutomáticamente
                'rol' => 'admin',
                'activo' => true,
            ]
        );

        // Crear usuario director/lector
        Usuario::updateOrCreate(
            ['correo' => 'directora@epespo.com'],
            [
                'nombre' => 'Directora',
                'contrasena' => 'Marjorie123@', // se encripta automáticamente
                'rol' => 'lector',
                'activo' => true,
            ]
        );
    }
}
