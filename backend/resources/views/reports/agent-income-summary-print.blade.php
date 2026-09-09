<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Income &amp; Expense Summary — NkwaLedger</title>
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
            font-size: 13px;
            margin-bottom: 4px;
        }

        .notice {
            background: #FEF3C7;
            border: 1px solid #FDE68A;
            color: #92400E;
            padding: 8px 12px;
            font-size: 13px;
            margin: 16px 0;
        }

        .totals {
            display: flex;
            gap: 24px;
            margin: 16px 0;
        }

        .totals div {
            border: 1px solid #E5E7EB;
            padding: 10px 14px;
        }

        .totals span {
            display: block;
            color: #6B7280;
            font-size: 12px;
        }

        .totals strong {
            font-size: 16px;
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
            font-size: 13px;
        }

        th {
            color: #6B7280;
            font-weight: 600;
        }

        .amount {
            text-align: right;
        }
    </style>
</head>

<body>
    <h1>Income &amp; Expense Summary</h1>
    <p class="meta">Agent: {{ $agentName }}</p>
    <p class="meta">Period: {{ $from }} to {{ $to }}</p>
    <p class="meta">Generated: {{ $generatedAt->format('d M Y, H:i') }}</p>

    <div class="notice">
        Internal use only — not a farmer financial record.
    </div>

    <div class="totals">
        <div><span>Total income</span><strong>{{ number_format($summary['total_income'] / 100, 2) }}</strong></div>
        <div><span>Total expense</span><strong>{{ number_format($summary['total_expense'] / 100, 2) }}</strong></div>
        <div><span>Net</span><strong>{{ number_format($summary['net'] / 100, 2) }}</strong></div>
    </div>

    <h2>Income by account</h2>
    @if (count($summary['income_by_account']) === 0)
        <p>No income recorded in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['income_by_account'] as $row)
                    <tr>
                        <td>{{ $row['account'] }}</td>
                        <td class="amount">{{ number_format($row['amount'] / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Expense by account</h2>
    @if (count($summary['expense_by_account']) === 0)
        <p>No expenses recorded in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($summary['expense_by_account'] as $row)
                    <tr>
                        <td>{{ $row['account'] }}</td>
                        <td class="amount">{{ number_format($row['amount'] / 100, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
