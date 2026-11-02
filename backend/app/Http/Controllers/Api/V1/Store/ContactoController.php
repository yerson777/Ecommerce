<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Requests\V1\Store\ContactoStoreRequest;
use App\Support\Api;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ContactoController extends Controller
{
    public function store(ContactoStoreRequest $request)
    {
        $validados = $request->validated();

        $nombre = trim($validados['nombre']);
        $correo = trim($validados['correo']);
        $asunto = trim($validados['asunto'] ?? 'Consulta desde la tienda online');
        $mensaje = trim($validados['mensaje']);

        $para = env('MAIL_TO');
        $desde = env('MAIL_FROM_ADDRESS');
        $nombreDesde = env('MAIL_FROM_NAME');

        if (!$para || !$desde) {
            Log::error('contacto: faltan MAIL_TO o MAIL_FROM_ADDRESS en .env');

            return Api::error(
                'No se pudo enviar el mensaje. Configuración de correo incompleta.',
                500
            );
        }

        $cuerpo = "Nueva consulta desde la tienda online:\n\n"
            . "Nombre: {$nombre}\n"
            . "Correo: {$correo}\n"
            . "Asunto: {$asunto}\n\n"
            . "Mensaje:\n{$mensaje}\n";

        try {
            Mail::raw($cuerpo, function ($mail) use ($para, $desde, $nombreDesde, $correo, $nombre, $asunto) {
                $mail->to($para)
                    ->from($desde, $nombreDesde)
                    ->replyTo($correo, $nombre)
                    ->subject($asunto);
            });

            return Api::success(null, 'Mensaje enviado correctamente. Te responderemos a la brevedad.', 200);
        } catch (\Throwable $e) {
            Log::error('contacto: error al enviar correo', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Api::error(
                'No se pudo enviar el mensaje. Intentalo de nuevo mas tarde.',
                500
            );
        }
    }
}
