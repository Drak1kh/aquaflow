<?php

namespace App\Http\Controllers;

use App\ActiveProvider;
use App\AuditRecorder;
use App\CsvCell;
use App\DecimalAmount;
use App\Models\Bill;
use App\Models\Consumer;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\User;
use App\Models\WaterConnection;
use App\Models\WaterMeter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProviderController extends Controller
{
    public function index(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'consumers.view');
        $search = trim((string) $request->query('q', ''));
        $consumers = Consumer::where('water_provider_id', $provider->id)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->where('name', 'like', '%'.$search.'%')->orWhere('account_number', 'like', '%'.$search.'%')))
            ->withCount('connections')->orderBy('name')->paginate(20)->withQueryString();
        $consumerChoices = Consumer::where('water_provider_id', $provider->id)->orderBy('name')->get();
        $connections = WaterConnection::where('water_provider_id', $provider->id)->with(['consumer', 'meter', 'readings'])->orderByDesc('id')->get();
        $bills = Bill::where('water_provider_id', $provider->id)->where('status', '!=', 'void')->get();
        $dashboard = [
            'consumer_count' => Consumer::where('water_provider_id', $provider->id)->count(),
            'active_connections' => $connections->where('status', 'active')->count(),
            'disconnected_connections' => $connections->where('status', 'disconnected')->count(),
            'unpaid_bills' => $bills->filter(fn (Bill $bill): bool => $bill->balanceCents() > 0)->count(),
            'outstanding_cents' => $bills->sum(fn (Bill $bill): int => $bill->balanceCents()),
            'collected_cents' => DecimalAmount::units((string) Payment::where('water_provider_id', $provider->id)->where('status', 'verified')->sum('amount'), 2),
        ];

        return view('pages.provider', compact('provider', 'consumers', 'consumerChoices', 'connections', 'search', 'dashboard'));
    }

    public function storeConsumer(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'consumers.manage');
        $data = $request->validate([
            'account_number' => ['required', 'alpha_dash', 'max:80', Rule::unique('consumers')->where('water_provider_id', $provider->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'service_address' => ['required', 'string', 'max:1000'],
        ]);
        $consumer = Consumer::create(['water_provider_id' => $provider->id, ...$data]);
        $audit->record($consumer, 'consumer.created');

        return back()->with('status', 'Consumer registered.');
    }

    public function updateConsumer(Request $request, Consumer $consumer, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'consumers.manage');
        abort_unless($consumer->water_provider_id === $provider->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'service_address' => ['required', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
        if ($consumer->user_id && $data['email'] !== $consumer->email) {
            throw ValidationException::withMessages(['email' => 'The linked consumer must change their login email in account settings.']);
        }
        $before = $consumer->getAttributes();
        $consumer->update($data);
        $audit->record($consumer, 'consumer.updated', $before);

        return back()->with('status', 'Consumer updated.');
    }

    public function storeConnection(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'connections.manage');
        $data = $request->validate([
            'consumer_id' => ['required', Rule::exists('consumers', 'id')->where('water_provider_id', $provider->id)],
            'connection_number' => ['required', 'alpha_dash', 'max:80', Rule::unique('water_connections')->where('water_provider_id', $provider->id)],
            'meter_number' => ['required', 'alpha_dash', 'max:80', Rule::unique('water_meters')->where('water_provider_id', $provider->id)],
            'service_address' => ['required', 'string', 'max:1000'],
        ]);

        $connection = DB::transaction(function () use ($data, $provider): WaterConnection {
            $meter = WaterMeter::create(['water_provider_id' => $provider->id, 'meter_number' => $data['meter_number']]);

            return WaterConnection::create([
                'water_provider_id' => $provider->id,
                'consumer_id' => $data['consumer_id'],
                'water_meter_id' => $meter->id,
                'connection_number' => $data['connection_number'],
                'service_address' => $data['service_address'],
            ]);
        });
        $audit->record($connection, 'connection.created');

        return back()->with('status', 'Connection and meter created.');
    }

    public function storeReading(Request $request, WaterConnection $connection, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'readings.manage');
        abort_unless($connection->water_provider_id === $provider->id, 404);
        $data = $request->validate([
            'reading_date' => ['required', 'date', 'before_or_equal:today', Rule::unique('meter_readings')->where('water_connection_id', $connection->id)],
            'current_reading' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
        ]);

        $reading = DB::transaction(function () use ($connection, $provider, $request, $data): MeterReading {
            $lastConnectionReading = MeterReading::where('water_connection_id', $connection->id)->orderByDesc('reading_date')->lockForUpdate()->first();
            if ($lastConnectionReading && $data['reading_date'] <= $lastConnectionReading->reading_date) {
                throw ValidationException::withMessages(['reading_date' => 'Readings must be recorded in date order.']);
            }

            $last = MeterReading::where('water_connection_id', $connection->id)->where('water_meter_id', $connection->water_meter_id)->orderByDesc('reading_date')->first();
            $previous = $last ? (string) $last->current_reading : '0.000';
            if (DecimalAmount::units((string) $data['current_reading'], 3) < DecimalAmount::units($previous, 3)) {
                throw ValidationException::withMessages(['current_reading' => 'Current reading cannot decrease.']);
            }

            return MeterReading::create([
                'water_provider_id' => $provider->id,
                'water_connection_id' => $connection->id,
                'water_meter_id' => $connection->water_meter_id,
                'entered_by' => $request->user()->id,
                'reading_date' => $data['reading_date'],
                'previous_reading' => $previous,
                'current_reading' => $data['current_reading'],
            ]);
        });
        $audit->record($reading, 'reading.created');

        return back()->with('status', 'Reading recorded.');
    }

    public function replaceMeter(Request $request, WaterConnection $connection, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'connections.manage');
        abort_unless($connection->water_provider_id === $provider->id, 404);
        $data = $request->validate([
            'meter_number' => ['required', 'alpha_dash', 'max:80', Rule::unique('water_meters')->where('water_provider_id', $provider->id)],
            'type' => ['required', 'string', 'max:80'],
        ]);
        $before = $connection->getAttributes();
        DB::transaction(function () use ($connection, $provider, $data): void {
            $connection->meter->update(['status' => 'retired']);
            $meter = WaterMeter::create(['water_provider_id' => $provider->id, ...$data]);
            $connection->update(['water_meter_id' => $meter->id]);
        });
        $audit->record($connection, 'meter.replaced', $before);

        return back()->with('status', 'Meter replaced. The new meter starts at zero.');
    }

    public function inviteConsumer(Request $request, Consumer $consumer, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'consumers.manage');
        abort_unless($consumer->water_provider_id === $provider->id, 404);
        if (! $consumer->email) {
            throw ValidationException::withMessages(['email' => 'Add an email address to the consumer record first.']);
        }

        $user = User::firstOrCreate(['email' => $consumer->email], [
            'name' => $consumer->name,
            'password' => Str::random(64),
        ]);
        if (Consumer::where('user_id', $user->id)->whereKeyNot($consumer->id)->exists()) {
            throw ValidationException::withMessages(['email' => 'This login already belongs to another consumer account.']);
        }

        $consumer->update(['user_id' => $user->id]);
        $audit->record($consumer, 'consumer.invited');
        Password::sendResetLink(['email' => $user->email]);
        $user->sendEmailVerificationNotification();

        return back()->with('status', 'Consumer invitation sent.');
    }

    public function report(Request $request, ActiveProvider $active): View
    {
        $provider = $active->authorize($request, 'reports.view');
        $bills = Bill::where('water_provider_id', $provider->id)->with(['consumer', 'period'])->orderByDesc('id')->get();
        $billedCents = $bills->sum(fn (Bill $bill): int => $bill->status === 'void' ? 0 : DecimalAmount::units((string) $bill->total_amount, 2));
        $outstandingCents = $bills->sum(fn (Bill $bill): int => $bill->balanceCents());
        $consumptionByPeriod = $bills->where('status', '!=', 'void')->groupBy(fn (Bill $bill): string => $bill->period->label)
            ->map(fn ($group): string => DecimalAmount::format($group->sum(fn (Bill $bill): int => DecimalAmount::units((string) $bill->consumption, 3)), 3));

        return view('pages.report', compact('provider', 'bills', 'billedCents', 'outstandingCents', 'consumptionByPeriod'));
    }

    public function updateProfile(Request $request, ActiveProvider $active, AuditRecorder $audit): RedirectResponse
    {
        $provider = $active->authorize($request, 'provider.manage');
        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:60'],
            'office_address' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $provider->getAttributes();
        $provider->update($data);
        $audit->record($provider, 'provider.profile.updated', $before);

        return back()->with('status', 'Provider profile updated.');
    }

    public function exportReport(Request $request, ActiveProvider $active): StreamedResponse
    {
        $provider = $active->authorize($request, 'reports.view');

        return response()->streamDownload(function () use ($provider): void {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                throw new \RuntimeException('Unable to open CSV output.');
            }
            fputcsv($output, ['Bill', 'Consumer', 'Period', 'Consumption', 'Total', 'Balance', 'Status']);
            Bill::where('water_provider_id', $provider->id)->with(['consumer', 'period'])->chunkById(100, function ($bills) use ($output): void {
                foreach ($bills as $bill) {
                    fputcsv($output, [
                        CsvCell::safe($bill->bill_number), CsvCell::safe($bill->consumer->name),
                        CsvCell::safe($bill->period->label), $bill->consumption, $bill->total_amount,
                        DecimalAmount::format($bill->balanceCents()), $bill->status,
                    ]);
                }
            });
            fclose($output);
        }, 'aquaflow-provider-report.csv', ['Content-Type' => 'text/csv']);
    }
}
