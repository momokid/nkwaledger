<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Farmer Profile — NkwaLedger</title>
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
            width: 220px;
        }

        .amount {
            text-align: right;
        }

        .totals {
            display: flex;
            gap: 24px;
            margin: 8px 0;
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
    </style>
</head>

<body>
    <h1>Farmer Profile</h1>
    <p class="meta">Agent: {{ $agentName }}</p>
    <p class="meta">Financial period: {{ $from }} to {{ $to }}</p>
    <p class="meta">Generated: {{ $generatedAt->format('d M Y, H:i') }}</p>

    <div class="notice">
        Internal use only — not a farmer financial record.
    </div>

    <h2>Personal &amp; registration</h2>
    <table>
        <tr>
            <th>Name</th>
            <td>{{ $farmer['name'] }}</td>
        </tr>
        <tr>
            <th>Phone</th>
            <td>{{ $farmer['phone'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Gender</th>
            <td>{{ $farmer['gender'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Date of birth</th>
            <td>{{ $farmer['date_of_birth'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Home address</th>
            <td>{{ $farmer['home_address'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Community</th>
            <td>{{ $farmer['community'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Farmer group</th>
            <td>{{ $farmer['farmer_group'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Status</th>
            <td>{{ $farmer['is_active'] ? 'Active' : 'On hold' }}</td>
        </tr>
        <tr>
            <th>Onboarded</th>
            <td>{{ $farmer['onboarded_at'] ?? '—' }}</td>
        </tr>
        <tr>
            <th>Registered by</th>
            <td>{{ $farmer['registered_by'] ?? '—' }}</td>
        </tr>
    </table>

    <h2>Identity</h2>
    <table>
        <tr>
            <th>Document type</th>
            <td>{{ $farmer['identity']['type'] ?? 'None on file' }}</td>
        </tr>
        <tr>
            <th>Document on file</th>
            <td>{{ $farmer['identity']['has_document'] ? 'Yes' : 'No' }}</td>
        </tr>
        <tr>
            <th>Verified</th>
            <td>{{ $farmer['identity']['verified'] ? 'Yes, by ' . $farmer['identity']['verified_by'] : 'Not verified' }}
            </td>
        </tr>
    </table>

    <h2>Farm types</h2>
    @if (count($farmer['farm_types']) === 0)
        <p>No farm types recorded.</p>
    @else
        <p>{{ implode(', ', $farmer['farm_types']) }}</p>
    @endif

    <h2>Farm units</h2>
    @if (count($farmUnits) === 0)
        <p>No farm units recorded.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Capacity</th>
                    <th>Approved</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($farmUnits as $unit)
                    <tr>
                        <td>{{ $unit['name'] }}</td>
                        <td>{{ $unit['farm_type'] ?? '—' }}</td>
                        <td>{{ $unit['capacity'] ? $unit['capacity'] . ' ' . $unit['capacity_unit'] : '—' }}</td>
                        <td>{{ $unit['is_approved'] ? 'Yes' : 'Pending' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Financial summary</h2>
    <div class="totals">
        <div><span>Total income</span><strong>{{ number_format($summary['total_income'] / 100, 2) }}</strong></div>
        <div><span>Total expense</span><strong>{{ number_format($summary['total_expense'] / 100, 2) }}</strong></div>
        <div><span>Net</span><strong>{{ number_format($summary['net'] / 100, 2) }}</strong></div>
    </div>

    <h2>Credit score</h2>
    <p>{{ $creditScore }}</p>
</body>

</html>
