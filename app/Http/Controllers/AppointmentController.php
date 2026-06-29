<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Http\Requests\StoreAppointmentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    /**
     * The all-day time slots the business offers. In a later iteration this
     * should come from a settings table (configurable operating hours), but
     * a fixed list is enough to wire the real booking flow end-to-end now.
     */
    private const ALL_SLOTS = ['09:00', '10:30', '12:00', '13:30', '15:00'];

    public function show(Request $request): Response
    {
        return Inertia::render('Public/Book', [
            'categories' => ServiceCategory::orderBy('sort_order')->get(),
            'services' => Service::where('is_active', true)->get(),
            'preselectedServiceId' => $request->query('service'),
        ]);
    }

    /**
     * Returns which dates in the next N days are fully booked (5+ active
     * appointments), and which slots are taken for a specific date. The
     * front end calls this as the person moves through the date/time steps.
     */
    public function availability(Request $request)
    {
        $request->validate(['date' => ['nullable', 'date']]);

        $date = $request->query('date');

        if ($date) {
            $takenTimes = Appointment::whereDate('appointment_date', $date)
                ->whereIn('status', Appointment::ACTIVE_STATUSES)
                ->pluck('appointment_time')
                ->map(fn ($t) => substr($t, 0, 5))
                ->all();

            $slots = collect(self::ALL_SLOTS)->map(fn ($time) => [
                'time' => $time,
                'available' => ! in_array($time, $takenTimes, true),
            ]);

            return response()->json(['slots' => $slots]);
        }

        // No specific date requested: return fullness for the next 21 days
        // so the calendar grid can grey out days at the 5-appointment cap.
        $start = now()->addDay()->startOfDay();
        $end = now()->addDays(21)->endOfDay();

        $counts = Appointment::whereBetween('appointment_date', [$start->toDateString(), $end->toDateString()])
            ->whereIn('status', Appointment::ACTIVE_STATUSES)
            ->select('appointment_date', DB::raw('count(*) as total'))
            ->groupBy('appointment_date')
            ->pluck('total', 'appointment_date');

        $dates = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->dayOfWeek === 0) {
                continue; // closed Sundays
            }
            $iso = $d->toDateString();
            $dates[] = [
                'date' => $iso,
                'full' => ($counts[$iso] ?? 0) >= 5,
            ];
        }

        return response()->json(['dates' => $dates]);
    }

    public function store(StoreAppointmentRequest $request)
    {
        $validated = $request->validated();

        $client = Client::firstOrCreate(
            ['email' => $validated['email']],
            [
                'full_name' => $validated['full_name'],
                'contact_number' => $validated['contact_number'],
                'address' => $validated['address'] ?? null,
            ]
        );

        // Keep contact details current for a returning client.
        $client->fill([
            'full_name' => $validated['full_name'],
            'contact_number' => $validated['contact_number'],
            'address' => $validated['address'] ?? $client->address,
        ])->save();

        $proofPath = $request->file('payment_proof')->store('payment-proofs', 'local');

        $appointment = Appointment::create([
            'appointment_number' => Appointment::generateAppointmentNumber(),
            'client_id' => $client->id,
            'service_id' => $validated['service_id'],
            'appointment_date' => $validated['appointment_date'],
            'appointment_time' => $validated['appointment_time'],
            'notes' => $validated['notes'] ?? null,
            'payment_proof_path' => $proofPath,
            'status' => Appointment::STATUS_PENDING,
        ]);

        // Send submission confirmation email
        try {
            \Illuminate\Support\Facades\Mail::to($client->email)
                ->send(new \App\Mail\AppointmentSubmitted($appointment->fresh(['client', 'service'])));
        } catch (\Exception $e) {
            logger()->error('Submission email failed: ' . $e->getMessage());
        }

        return redirect()->route('book.confirmation', $appointment->appointment_number);
    }

    public function confirmation(string $appointmentNumber): Response
    {
        $appointment = Appointment::with(['client', 'service'])
            ->where('appointment_number', $appointmentNumber)
            ->firstOrFail();

        return Inertia::render('Public/BookConfirmation', [
            'appointment' => [
                'appointmentNumber' => $appointment->appointment_number,
                'status'            => $appointment->status,
                'fullName'          => $appointment->client->full_name,
                'email'             => $appointment->client->email,
                'serviceName'       => $appointment->service->name,
                'date'              => $appointment->appointment_date->toDateString(),
                'time'              => substr($appointment->appointment_time, 0, 5),
                'qrCodeUrl'         => $appointment->qr_code_token
                    ? route('qr.show', $appointment->appointment_number)
                    : null,
            ],
        ]);
    }
}
