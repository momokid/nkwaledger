<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Dormant Farmers — NkwaLedger</title>
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
            margin-top: 16px;
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
    </style>
</head>

<body>
    <h1>Dormant Farmers</h1>
    <p class="meta">Agent: {{ $agentName }}</p>
    <p class="meta">Period checked: {{ $from }} to {{ $to }}</p>
    <p class="meta">Generated: {{ $generatedAt->format('d M Y, H:i') }}</p>

    <div class="notice">
        Internal use only — not a farmer financial record.
    </div>

    @if (count($roster) === 0)
        <p>No dormant farmers in this period.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Farmer</th>
                    <th>Community</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($roster as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['community'] ?? '—' }}</td>
                        <td>{{ $row['last_activity'] ?? 'Never logged activity' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>

</html>
