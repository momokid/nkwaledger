@php
    use App\Support\Money;
    use App\Support\Wording;
    use Illuminate\Support\Carbon;

    $day = fn (?string $date) => $date ? Carbon::parse($date)->format('d/m/Y') : '—';
    $money = fn (?int $minor) => $minor === null ? '—' : Money::format($minor);
    $loan = $s['loan'];
    $usedAnything = $s['farms'] !== [] || $s['purchases'] !== [] || $s['other_uses'] !== [];
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Loan statement — NkwaLedger</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 13px;
            color: #111827;
            margin: 32px;
        }

        h1 {
            font-size: 20px;
            margin-bottom: 4px;
        }

        h2 {
            font-size: 15px;
            margin: 20px 0 8px;
        }

        .meta {
            color: #6B7280;
            margin-bottom: 4px;
        }

        .notice {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            color: #92400E;
            padding: 8px 12px;
            margin: 16px 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }

        th,
        td {
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #E5E7EB;
        }

        th {
            color: #6B7280;
            font-weight: 600;
        }

        .amount {
            text-align: right;
        }

        .details th {
            width: 220px;
        }

        .cancelled {
            color: #9CA3AF;
            text-decoration: line-through;
        }

        .lines p {
            margin: 4px 0;
        }

        .totals p {
            margin: 4px 0;
            font-weight: 600;
        }

        .print-button {
            margin-bottom: 16px;
        }

        @media print {
            .print-button {
                display: none;
            }
        }
    </style>
</head>

<body>
    @unless ($pdf)
        <div class="print-button">
            <button onclick="window.print()">Print</button>
        </div>
    @endunless

    <h1>Loan statement</h1>
    <p class="meta">{{ $loan['lender'] }}</p>

    <h2>The loan</h2>
    <table class="details">
        <tr><th>Lender</th><td>{{ $loan['lender'] }}</td></tr>
        <tr><th>Amount received</th><td>{{ $money($loan['amount']) }}</td></tr>
        <tr><th>Date received</th><td>{{ $day($loan['granted_on']) }}</td></tr>
        <tr><th>Paid into</th><td>{{ $loan['paid_into'] ?? '—' }}</td></tr>
        <tr><th>Total interest agreed</th><td>{{ $money($loan['interest']) }}</td></tr>
        <tr><th>Due date</th><td>{{ $day($loan['due_date']) }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($loan['status']) }}</td></tr>
        <tr><th>Loan balance</th><td>{{ $money($loan['balance']) }}</td></tr>
    </table>

    @if ($s['repayments'] !== [])
        <h2>Repayments</h2>
        <table>
            <tr>
                <th>{{ Wording::get('repayment.date') }}</th>
                <th class="amount">{{ Wording::get('repayment.amount') }}</th>
                <th class="amount">{{ Wording::get('repayment.principal_part') }}</th>
                <th class="amount">{{ Wording::get('repayment.interest_part') }}</th>
                <th class="amount">{{ Wording::get('repayment.loan_balance') }}</th>
            </tr>
            @foreach ($s['repayments'] as $row)
                <tr @class(['cancelled' => $row['cancelled']])>
                    <td>{{ $day($row['date']) }}</td>
                    <td class="amount">{{ $money($row['amount']) }}</td>
                    <td class="amount">{{ $money($row['principal']) }}</td>
                    <td class="amount">{{ $money($row['interest']) }}</td>
                    <td class="amount">{{ $money($row['balance']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if ($usedAnything)
        <h2>What the money was used for</h2>

        <div class="lines">
            @foreach ($s['farms'] as $farm)
                @if ($farm['purpose'])
                    <p>{{ $farm['name'] }}: {{ $farm['purpose'] }}</p>
                @endif
                <p>{{ $farm['name'] }}: planned {{ $money($farm['planned']) }}, accounted {{ $money($farm['accounted']) }}, not yet accounted {{ $money($farm['not_yet_accounted']) }}</p>
                @if ($farm['over'] > 0)
                    <p>{{ $farm['name'] }}: {{ $money($farm['over']) }} more than planned</p>
                @endif
            @endforeach
        </div>

        @if ($s['purchases'] !== [])
            <table>
                <tr>
                    <th>Date</th>
                    <th>What it was</th>
                    <th class="amount">Amount</th>
                </tr>
                @foreach ($s['purchases'] as $row)
                    <tr>
                        <td>{{ $day($row['date']) }}</td>
                        <td>{{ $row['what'] }}@if ($row['farm']) – {{ $row['farm'] }}@endif</td>
                        <td class="amount">{{ $money($row['amount']) }}</td>
                    </tr>
                @endforeach
            </table>

            <div class="lines">
                @foreach ($s['kinds'] as $kind)
                    <p>{{ $kind['name'] }}: {{ $money($kind['amount']) }}</p>
                @endforeach
            </div>
        @endif

        @if ($s['other_uses'] !== [])
            <div class="lines">
                @foreach ($s['other_uses'] as $use)
                    <p>{{ $use['note'] }}: {{ $money($use['amount']) }}</p>
                @endforeach
            </div>
        @endif

        <div class="totals">
            <p>Used on the farms: {{ $money($s['totals']['on_farms']) }}</p>
            <p>Used for something else: {{ $money($s['totals']['other']) }}</p>
            <p>Not yet accounted for: {{ $money($s['totals']['not_yet']) }}</p>
        </div>
    @endif

    <p class="meta">Prepared on {{ $day($s['prepared_on']) }}</p>
    <div class="notice">{{ $s['notice'] }}</div>
</body>

</html>
