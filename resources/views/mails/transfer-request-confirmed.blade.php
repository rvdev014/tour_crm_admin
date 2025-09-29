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
            margin-bottom: 30px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 20px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin-bottom: 10px;
        }

        .contact {
            font-size: 12px;
            color: #666;
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
        <div class="logo">EAST ASIA POINT TRAVEL & TOURS</div>
        <div class="contact">
            38, 91/2 Green Park, Makhtumkuli str., 100047 Tashkent, Uzbekistan<br>
            Phone: +998977207752 | Email: info@asia-point.uz | Website: www.letsgouzbekistan.com
        </div>
    </div>

    <div class="confirmation-badge">
        ✓ TRANSFER REQUEST CONFIRMED
    </div>

    <p>Dear {{ $transferRequest->fio }},</p>

    <p>Great news! Your transfer request has been confirmed and we have created a new transfer booking for you.</p>

    <div class="section">
        <h3>📋 Transfer Details</h3>
        <div class="detail-row">
            <span class="label">Transfer Number:</span>
            <span class="value">#{{ $transfer->number }}</span>
        </div>
        <div class="detail-row">
            <span class="label">From:</span>
            <span class="value">{{ $transferRequest->fromCity->name ?? 'Location ID: ' . $transferRequest->from }}</span>
        </div>
        <div class="detail-row">
            <span class="label">To:</span>
            <span class="value">{{ $transferRequest->toCity->name ?? 'Location ID: ' . $transferRequest->to }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Date & Time:</span>
            <span class="value">{{ $transferRequest->date_time->format('F j, Y \a\t H:i') }}</span>
        </div>
        <div class="detail-row">
            <span class="label">Passengers:</span>
            <span class="value">{{ $transferRequest->passengers_count }} person(s)</span>
        </div>
        @if($transferRequest->transportClass)
        <div class="detail-row">
            <span class="label">Transport Class:</span>
            <span class="value">{{ $transferRequest->transportClass->name }}</span>
        </div>
        @endif
        @if($transferRequest->distance)
        <div class="detail-row">
            <span class="label">Distance:</span>
            <span class="value">{{ $transferRequest->distance }} km</span>
        </div>
        @endif
    </div>

    @if($transferRequest->comment || $transferRequest->terminal_name || $transferRequest->text_on_sign)
    <div class="section">
        <h3>📝 Additional Information</h3>
        @if($transferRequest->comment)
        <div class="detail-row">
            <span class="label">Comment:</span>
            <span class="value">{{ $transferRequest->comment }}</span>
        </div>
        @endif
        @if($transferRequest->terminal_name)
        <div class="detail-row">
            <span class="label">Terminal:</span>
            <span class="value">{{ $transferRequest->terminal_name }}</span>
        </div>
        @endif
        @if($transferRequest->text_on_sign)
        <div class="detail-row">
            <span class="label">Sign Text:</span>
            <span class="value">{{ $transferRequest->text_on_sign }}</span>
        </div>
        @endif
        @if($transferRequest->baggage_count)
        <div class="detail-row">
            <span class="label">Baggage Count:</span>
            <span class="value">{{ $transferRequest->baggage_count }}</span>
        </div>
        @endif
        @if($transferRequest->is_sample_baggage)
        <div class="detail-row">
            <span class="label">Sample Baggage:</span>
            <span class="value">Yes</span>
        </div>
        @endif
        @if($transferRequest->activate_flight_tracking)
        <div class="detail-row">
            <span class="label">Flight Tracking:</span>
            <span class="value">Activated</span>
        </div>
        @endif
    </div>
    @endif

    <div class="section">
        <h3>📞 Contact Information</h3>
        <p>If you have any questions or need to make changes to your transfer, please contact us:</p>
        <div class="detail-row">
            <span class="label">Phone:</span>
            <span class="value">+998977207752</span>
        </div>
        <div class="detail-row">
            <span class="label">Email:</span>
            <span class="value">info@asia-point.uz</span>
        </div>
    </div>

    <p>We look forward to providing you with excellent service!</p>

    <div class="footer">
        <p><strong>Best regards,<br>East Asia Point Travel & Tours Team</strong></p>
        <p>This is an automated message. Please do not reply directly to this email.</p>
    </div>

</body>
</html>