@php use App\Models\TourDayExpense; @endphp
    <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail for Hotel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .header {
            text-align: center;
            margin-bottom: 10px;
        }

        .contact {
            font-size: 12px;
            text-align: center;
        }

        .section {
            margin: 20px 0;
        }

        .bold {
            font-weight: bold;
        }

        .reservation-box {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table, th, td {
            border: 1px solid black;
        }

        th {
            background-color: #f3f3f3;
        }

        th, td {
            padding: 5px;
            text-align: center;
        }

        .footer {
            margin-top: 20px;
        }
    </style>
</head>
@php
    /** @var $expense TourDayExpense */
    /** @var $placeholders array */

$rooming = $placeholders['roomingArr'];
@endphp
<body>

<div style="width: 100%; max-width: 900px; margin: 0 auto; padding: 10px;">

    <div class="header">
        <img src="{{ asset('img/logo.jpg') }}" alt="Logo" height="50"><br>
        <strong>EAST ASIA POINT TRAVEL & TOURS</strong><br>
    </div>

    <div class="contact">
        38, 91/2 Green Park, Makhtumkuli str., 100047 Tashkent, Uzbekistan<br>
        Phone: <a href="tel:+998977207752">+99897 720 77 52</a> &nbsp; | &nbsp; Email: <a href="mailto:info@asia-point.uz">info@asia-point.uz</a> &nbsp; | &nbsp;
        Website: <a href="https://www.letsgouzbekistan.com">www.letsgouzbekistan.com</a>
    </div>

    <div class="section">
        <p><strong>Отдел бронирования / Reservation Department</strong></p>
        <p><strong>Дата / Date:</strong> {{ $placeholders['date'] }}</p>
        <p><strong>{{ $placeholders['hotel'] }}</strong></p>
    </div>

    <div class="reservation-box">RESERVATION</div>

    <div class="section">
        <p><strong>Уважаемые коллеги,<br>Dear Sir/Madam,</strong></p>
        <p>Просим Вас внести изменения и забронировать нижеследующие номера.<br>
            We are pleased to send the below booking to your esteemed hotel:</p>

        <p><strong>Ref#:</strong> {{ $placeholders['groupNum'] }} &nbsp;&nbsp;&nbsp; <strong>Кол-во
                (Pax):</strong> {{ $placeholders['pax'] }}</p>
        {{--    <p><strong>Single:</strong> 1 &nbsp;&nbsp; <strong>Double:</strong> 10</p>--}}
        <p>{!! $rooming->map(fn($amount, $roomType) => "<strong>{$roomType}:</strong> {$amount}")->implode('&nbsp;&nbsp;') !!}</p>

        <table>
            <thead>
            <tr>
                <th>№</th>
                <th>Номер извещения</th>
                <th>ФИО туристов / Гостиница</th>
                <th>Заезд (Check-in)</th>
                <th>Время заезда (Arr.Time)</th>
                <th>Выезд (Check-out)</th>
                <th>Время выезда (Dep.Time)</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td>1</td>
                <td>{{ $placeholders['groupNum'] }} {{ $placeholders['country'] }}</td>
                <td>{{ $placeholders['hotel'] }} {{ $placeholders['city'] }}</td>
                <td>{{ $placeholders['arrivals'] }}</td>
                <td>{{ $placeholders['arrivalTimes'] }}</td>
                <td>{{ $placeholders['outs'] }}</td>
                <td>{{ $placeholders['outsTime'] }}</td>
            </tr>
            </tbody>
        </table>
    </div>

    <div class="footer">
        <p>Заранее спасибо и жду Ваших подтверждений<br>
            /Thanks in advance and looking forward to your written confirmation).</p>
        <p>Оплату гарантируем сог. договора №{{ $placeholders['contract_num'] }} от {{ $placeholders['contract_year'] }}<br>
            We guarantee the payment as per agreement #{{ $placeholders['contract_num'] }} on {{ $placeholders['contract_year'] }}</p>
        <p>
            <b>С уважением, With best regards</b>
            <br>
            <b>{{ $placeholders['operator'] }}</b>
            <br>
            <b>+998977207752</b>
            <br>
            <b>+998333377752</b>
        </p>
    </div>

</div>

</body>
