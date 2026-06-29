<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use Illuminate\Http\Response;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class QrCodeController extends Controller
{
    /**
     * Generates a QR code SVG for a given appointment number.
     * Public route — the client needs this on their confirmation page.
     * We validate by appointment number so it's not guessable by sequential ID.
     */
    public function show(string $appointmentNumber): Response
    {
        $appointment = Appointment::where('appointment_number', $appointmentNumber)
            ->whereNotNull('qr_code_token')
            ->firstOrFail();

        // The QR code encodes the token URL — the admin scanner hits this URL.
        $scanUrl = route('admin.qr.verify.get', $appointment->qr_code_token);

        $svg = QrCode::format('svg')
            ->size(200)
            ->margin(1)
            ->generate($scanUrl);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /**
     * Admin-side: look up an appointment by QR token via GET (for direct URL scanning).
     */
    public function verifyGet(string $token)
    {
        $appointment = Appointment::with(['client', 'service'])
            ->where('qr_code_token', $token)
            ->firstOrFail();

        return redirect()->route('admin.qr.scanner')
            ->with('prefill_token', $token);
    }
}
