<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoAsignacionActual extends Model
{
    use HasFactory;

    protected $table = 'producto_asignaciones_actuales';

    protected $fillable = [
        'producto_id',
        'responsable_id',
        'area_id',
        'asignacion_id',
        'fecha_asignacion',
    ];

    protected $casts = [
        'fecha_asignacion' => 'date',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function responsable()
    {
        return $this->belongsTo(Responsable::class, 'responsable_id');
    }

    public function area()
    {
        return $this->belongsTo(Departamento::class, 'area_id');
    }

    public function asignacion()
    {
        return $this->belongsTo(Asignacion::class, 'asignacion_id');
    }
}
