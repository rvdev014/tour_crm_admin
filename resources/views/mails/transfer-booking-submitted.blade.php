@php
    /** @var \App\Models\TransferBooking $booking */
    use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Request Received</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .contact {
            font-size: 12px;
            text-align: center;
        }

        .confirmation-badge {
            background-color: #f5a623;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-align: center;
            font-weight: bold;
            margin: 20px 0;
        }

        .section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }

        .section h3 {
            margin-top: 0;
            color: #f5a623;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 8px 0;
            padding: 5px 0;
            border-bottom: 1px dotted #ccc;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .label {
            font-weight: bold;
            color: #555;
        }

        .value {
            color: #333;
        }

        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 12px;
            color: #666;
        }

        @media (max-width: 600px) {
            .detail-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

<div class="header">
    <img src="{{ asset('img/logo.jpg') }}" alt="Logo" height="50"><br>
    <strong>EAST ASIA POINT TRAVEL & TOURS</strong><br>
</div>

<div class="contact">
    38, 91/2 Green Park, Makhtumkuli str., 100047 Tashkent, Uzbekistan<br>
    Phone: <a href="tel:+998977207752">+99897 720 77 52</a> &nbsp; | &nbsp; Email: <a href="mailto:info@asia-point.uz">info@asia-point.uz</a> &nbsp; | &nbsp;
    Website: <a href="https://www.letsgouzbekistan.com">www.letsgouzbekistan.com</a>
</div>

<div class="confirmation-badge">
    ✓ Transfer Request Received — {{ $booking->reference }}
</div>

<p>Dear {{ $booking->first_name }},</p>
<p>
    Thank you for your transfer request. Our team will review it and confirm the
    details with you shortly. Please keep your booking reference
    <strong>{{ $booking->reference }}</strong> for your records.
</p>

@foreach ($booking->legs as $leg)
    <div class="section">
        <h3>{{ $leg->direction === 'return' ? '🔁 Return' : '🚗 Departure' }}</h3>
        <div class="detail-row">
            <span class="label">Pick-up location:</span>
            <span class="value">{{ $leg->from }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Destination:</span>
            <span class="value">{{ $leg->to }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Pick-up date & time:</span>
            <span class="value">{{ Carbon::parse($leg->date_time)->format('d.m.Y H:i') }} UTC</span>
        </div>
        <div class="detail-row">
            <span class="label">Vehicle:</span>
            <span class="value">
                {{ $leg->transportClass?->name }}
                @if ($leg->vehicle_count > 1)
                    &times; {{ $leg->vehicle_count }} vehicles
                @endif
            </span>
        </div>
        <div class="detail-row">
            <span class="label">Fare:</span>
            <span class="value">${{ number_format($leg->total_fare, 2) }}</span>
        </div>
    </div>
@endforeach

@if ($booking->extras->isNotEmpty())
    <div class="section">
        <h3>🧸 Extras</h3>
        @foreach ($booking->extras as $extra)
            <div class="detail-row">
                <span class="label">{{ $extra->name }} &times; {{ $extra->pivot->quantity }}</span>
                <span class="value">${{ number_format($extra->pivot->total_price, 2) }}</span>
            </div>
        @endforeach
    </div>
@endif

<div class="section">
    <div class="detail-row">
        <span class="label">Total:</span>
        <span class="value">${{ number_format($booking->total, 2) }}</span>
    </div>
</div>

<div class="footer">
    This is an automated confirmation that we received your request — it is not
    yet a final booking confirmation. We'll be in touch shortly.
</div>

</body>
</html>
