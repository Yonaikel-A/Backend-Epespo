<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Restablecer contraseña - EPESPO</title>
</head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;">
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 0;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;">
          <!-- Header -->
          <tr>
            <td style="background:#0f172a;padding:22px 24px;">
              <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                <tr>
                  <td style="vertical-align:middle;">
                <img src="{{ $message->embed(public_path('imagen/epespo-logo.png')) }}"
     alt="EPESPO" style="height:44px;display:block;">

                  </td>
                  <td align="right" style="color:#e5e7eb;font-size:12px;vertical-align:middle;">
                    EPESPO – Escuela de Pesca del Pacífico Oriental
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Body -->
          <tr>
            <td style="padding:26px 24px 10px 24px;color:#111827;">
              <h2 style="margin:0 0 10px 0;font-size:20px;line-height:1.3;">Código de restablecimiento</h2>
              <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#374151;">
                Hola, recibimos una solicitud para restablecer tu contraseña.
              </p>

              <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:18px;text-align:center;margin:14px 0;">
                <div style="font-size:12px;color:#6b7280;margin-bottom:8px;">Tu código es:</div>
                <div style="font-size:34px;letter-spacing:6px;font-weight:bold;color:#111827;">
                  {{ $code }}
                </div>
                <div style="font-size:12px;color:#6b7280;margin-top:10px;">
                  Este código expira en {{ $expiresMinutes }} minutos.
                </div>
              </div>

              <p style="margin:0 0 14px 0;font-size:14px;line-height:1.6;color:#374151;">
                También puedes abrir la pantalla de restablecimiento:
              </p>

              <div style="text-align:center;margin:18px 0 6px 0;">
                <a href="{{ $actionUrl }}"
                   style="background:#2563eb;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:10px;display:inline-block;font-size:14px;font-weight:bold;">
                  Abrir pantalla de restablecer
                </a>
              </div>

              <p style="margin:16px 0 0 0;font-size:12px;line-height:1.6;color:#6b7280;">
                Si no solicitaste esto, ignora este correo. Tu cuenta seguirá segura.
              </p>

              <hr style="border:none;border-top:1px solid #e5e7eb;margin:20px 0;">

              <p style="margin:0;font-size:12px;line-height:1.6;color:#6b7280;">
                Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                <a href="{{ $actionUrl }}" style="color:#2563eb;word-break:break-all;">{{ $actionUrl }}</a>
              </p>
            </td>
          </tr>

          <!-- Footer -->
          <tr>
            <td style="padding:16px 24px;background:#ffffff;color:#9ca3af;font-size:12px;">
              © {{ date('Y') }} EPESPO – Escuela de Pesca del Pacífico Oriental
            </td>
          </tr>
        </table>

        <div style="max-width:600px;margin-top:10px;color:#9ca3af;font-size:11px;">
          Este es un correo automático, por favor no respondas.
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
