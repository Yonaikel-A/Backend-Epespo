<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acta extends Model
{
    protected $fillable = [
        'codigo',
        'asignacion_id',
        'responsable_id',
        'fecha_creacion',
        'estado',
        'archivo_path',
        'archivo_pdf_path',
    ];
        public function asignacion()
{
    return $this->belongsTo(Asignacion::class, 'asignacion_id');
}

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'acta_id');
    }

    public function responsable()
    {
        return $this->belongsTo(Responsable::class);
    }

    public function recepciones()
    {
        return $this->hasMany(Recepcion::class, 'acta_id');
    }


}
