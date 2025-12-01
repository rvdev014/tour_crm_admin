@php
    /** @var TransferRequest $transferRequest */
    use App\Models\TransferRequest;use Carbon\Carbon;
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Request Confirmed</title>
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
            background-color: #4CAF50;
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
            color: #4CAF50;
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

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
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
    ✓ Transfer Booking Confirmation
</div>

<p>Dear {{ $transferRequest->fio }},</p>
<p>Your transfer has been successfully booked! <br>Thank you for choosing East Asia Point Travel and Tours.</p>

<div class="section">
    <h3>📋 Booking Details</h3>
    <div class="detail-row">
        <span class="label">Pick-up location:</span>
        <span class="value">{{ $transferRequest->from }}</span>
    </div>
    <div class="detail-row">
        <span class="label">Destination:</span>
        <span class="value">{{ $transferRequest->to }}</span>
    </div>
    <div class="detail-row">
        <span class="label">Pick-up date & time:</span>
        <span class="value">{{ Carbon::parse($transferRequest->date_time, $transferRequest->user->timezone ?? 'UTC')->format('d.m.Y H:i') }}</span>
    </div>
    <div class="detail-row">
        <span class="label">Vehicle class:</span>
        <span class="value">{{ $transferRequest->transportClass->name }}</span>
    </div>
    <div class="detail-row">
        <span class="label">Distance:</span>
        <span class="value">{{ $transferRequest->distance }} km</span>
    </div>
    <div class="detail-row">
        <span class="label">Total fare:</span>
        <span class="value">{{ $transferRequest->total_fare }} $</span>
    </div>
    <div class="detail-row">
        <span class="label">Included:</span>
        @php
            $tClass = $transferRequest->transportClass
        @endphp
        <span class="value">
            Up to {{$tClass->passenger_capacity}} passengers,<br>
            {{$tClass->luggage_capacity}} bags,
            {{$tClass->waiting_time_included}} min waiting time,<br>
            {{$tClass->meeting_with_place ? 'meeting with a plate' : ''}}
            {{false ? ', flight tracking' : ''}}
        </span>
    </div>
</div>

<div class="section">
    <h3>📝 Important notes:</h3>
    <p class="notes">The driver will meet you at the hotel entrance with a sign displaying your name.</p>
    <p class="notes">Please ensure your phone number is active and reachable.</p>
    <p class="notes">Free cancellation is available up to 24 hours before the trip.</p>
    <p class="notes">If you have any changes or questions, please contact us at info@letsouzbekistan.com or call <a href="tel:+998977207752">+99897 720 77 52</a></p>
</div>

<div class="footer">
    <p><strong>Best regards,<br>East Asia Point Travel & Tours Team</strong></p>
</div>

</body>
</html>
