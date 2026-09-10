import { Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

interface AppointmentSummary {
  appointmentNumber: string;
  status: string;
  fullName: string;
  email: string;
  serviceName: string;
  date: string;
  time: string;
  qrCodeUrl: string | null;
}

interface Props { appointment: AppointmentSummary; }

function formatTime(t: string) {
  if (!t) return '';
  const [h, m] = t.split(':').map(Number);
  return `${h % 12 || 12}:${String(m).padStart(2, '0')} ${h >= 12 ? 'PM' : 'AM'}`;
}

export default function BookConfirmation({ appointment }: Props) {
  const formattedDate = new Date(appointment.date + 'T00:00:00').toLocaleDateString('en-US', {
    weekday: 'long', month: 'long', day: 'numeric',
  });
  const isApproved = appointment.status === 'approved';

  return (
    <PublicLayout>
      <section className="max-w-5xl mx-auto px-4 sm:px-6 pt-12 pb-24">
        <div className="max-w-md animate-fade-up">
          <span className={`inline-block text-xs font-medium px-2.5 py-1 rounded-full mb-4 ${isApproved ? 'badge-approved' : 'badge-pending'}`}>
            {isApproved ? '✓ Confirmed' : 'Pending verification'}
          </span>
          <h1 className="font-display text-2xl mb-1.5">
            {isApproved ? 'Your appointment is confirmed!' : 'Request submitted'}
          </h1>
          <p className="text-sm text-muted mb-7 leading-relaxed">
            {isApproved
              ? 'Show the QR code below when you arrive at check-in.'
              : "We received your appointment and payment proof. You will get a QR code by email once approved."}
          </p>

          <div className="border border-border rounded-xl p-6 bg-white shadow-card">
            <div className="flex items-start justify-between mb-5">
              <div>
                <p className="text-xs text-muted mb-0.5">Appointment number</p>
                <p className="font-mono text-lg text-violet-600 font-semibold">{appointment.appointmentNumber}</p>
              </div>
              <div className="w-20 h-20 rounded-lg border border-border flex items-center justify-center shrink-0 bg-white overflow-hidden">
                {isApproved && appointment.qrCodeUrl
                  ? <img src={appointment.qrCodeUrl} alt="Check-in QR Code" className="w-full h-full" />
                  : <p className="text-[9px] text-muted text-center px-1">QR pending approval</p>
                }
              </div>
            </div>
            <div className="space-y-2 pt-4 border-t border-border text-sm">
              <div className="flex justify-between"><span className="text-muted">Service</span><span className="font-medium">{appointment.serviceName}</span></div>
              <div className="flex justify-between"><span className="text-muted">Date</span><span className="font-medium">{formattedDate}</span></div>
              <div className="flex justify-between"><span className="text-muted">Time</span><span className="font-medium font-mono">{formatTime(appointment.time)}</span></div>
              <div className="flex justify-between"><span className="text-muted">Name</span><span className="font-medium">{appointment.fullName}</span></div>
            </div>
          </div>

          <p className="text-xs text-muted mt-5 leading-relaxed">
            {isApproved
              ? `Screenshot this QR code. A copy has also been sent to `
              : `A confirmation email is on its way to `}
            <span className="font-medium text-ink/70">{appointment.email}</span>.
          </p>
          <Link href="/" className="mt-7 inline-flex items-center gap-2 text-sm font-medium text-violet-600 hover:text-violet-700">
            ← Back to home
          </Link>
        </div>
      </section>
    </PublicLayout>
  );
}
