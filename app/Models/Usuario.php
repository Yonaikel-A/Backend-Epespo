<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Tymon\JWTAuth\Contracts\JWTSubject;

use Illuminate\Notifications\Notifiable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Http\Controllers\Auth\ResetPasswordFrontendNotification;
class Usuario extends Authenticatable implements JWTSubject, CanResetPasswordContract
{
    use Notifiable, CanResetPassword;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre',
        'correo',
        'contrasena',
        'rol',
        'activo',
    ];

    protected $hidden = [
        'contrasena',
    ];

    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    public function getEmailForPasswordReset()
    {
        return $this->correo;
    }

    public function routeNotificationForMail($notification)
    {
        return $this->correo;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordFrontendNotification($token));
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'rol' => $this->rol,
            'correo' => $this->correo,
        ];
    }

    public function setContrasenaAttribute($value)
    {
        $this->attributes['contrasena'] = bcrypt($value);
    }
public function movimientos()
{
    return $this->hasMany(Movimiento::class, 'usuario_id');
}

}
