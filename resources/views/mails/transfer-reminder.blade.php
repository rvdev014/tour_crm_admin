@php
    use App\Models\Transfer;use Carbon\Carbon;
    /** @var Transfer $transfer */
    $transferRequest = $transfer->transferRequest
@endphp

    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Reminder</title>
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
    Transfer Reminder!
</div>

<p>Dear {{ $transferRequest->fio }},</p>
<p>This is a friendly reminder from East Asia Point Travel and Tours.</p>
<p>Your Business Class transfer from <strong>{{$transfer->from}}</strong> to <strong>{{$transfer->to}}</strong> is scheduled in 2 hours.</p>

<div class="section">
    <h3>📋 Details:</h3>
    <div class="detail-row">
        <span class="label">🕐 Pick-up time:</span>
        <span class="value">{{ Carbon::parse($transfer->date_time, $transferRequest?->user?->timezone ?? 'UTC')->format('d F Y, h:i A') }}</span>
    </div>
    <div class="detail-row">
        <span class="label">📍 Meeting point:</span>
        <span class="value">{{ $transfer->location_details ?? $transfer->from }}</span>
    </div>
    <div class="detail-row">
        <span class="label">🚘 Vehicle:</span>
        <span class="value">{{ $transferRequest?->transportClass?->vehicle_example ?? '-' }}</span>
    </div>
</div>

<div class="section">
    <p class="notes">👤 Driver will meet you with a sign displaying your name</p>
    <p class="notes">☎️ Please ensure your phone is switched on and reachable</p>
    <p class="notes">We wish you a smooth and pleasant journey! If you need to make changes, please contact us at <a href="mailto:info@letsouzbekistan.com"></a> or <a href="tel:+998977207752">+998977207752</a>.</p>
</div>

<div class="footer">
    <p><strong>Best regards,<br>East Asia Point Travel & Tours Team</strong></p>
</div>

</body>
</html>
