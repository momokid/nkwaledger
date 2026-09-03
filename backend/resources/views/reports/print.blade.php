@php
    $header = $report['header'];

    $cedis = fn(int $minor) => number_format($minor / 100, 2);
    $day = fn(?string $value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('d/m/Y') : '—';
@endphp
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>{{ $header['title'] }} — {{ $header['farmer_name'] }}</title>
    <style>
        @page {
            size: A4;
            margin: 18mm 14mm 22mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: 'Inter', Arial, sans-serif;
            font-size: 11pt;
            color: #111827;
            background: #FFFFFF;
        }

        .sheet {
            max-width: 190mm;
            margin: 0 auto;
            padding: 10mm;
        }

        .brand {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 8px;
        }

        .brand h1 {
            margin: 0;
            font-size: 15pt;
            letter-spacing: 2px;
            color: #1D9E75;
        }

        .brand p {
            margin: 2px 0 0;
            font-size: 9pt;
            color: #6B7280;
        }

        .brand h2 {
            margin: 0;
            font-size: 14pt;
            text-align: right;
        }

        .facts {
            display: flex;
            flex-wrap: wrap;
            gap: 18px;
            margin: 14px 0 4px;
        }

        .fact {
            min-width: 130px;
        }

        .fact span {
            display: block;
            font-size: 8.5pt;
            color: #6B7280;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }

        .fact strong {
            display: block;
            font-size: 11pt;
            font-weight: 600;
            margin-top: 2px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        thead th {
            text-align: left;
            font-size: 9pt;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            border-bottom: 1px solid #111827;
            padding: 6px 6px;
        }

        tbody td {
            padding: 6px;
            border-bottom: 1px solid #E5E7EB;
            vertical-align: top;
        }

        tfoot td {
            padding: 8px 6px;
            border-top: 2px solid #111827;
            font-weight: 700;
        }

        .right {
            text-align: right;
        }

        .muted {
            color: #6B7280;
            font-size: 9pt;
            display: block;
            margin-top: 2px;
        }

        .section {
            margin-top: 18px;
            page-break-inside: avoid;
        }

        .section h3 {
            margin: 0 0 6px;
            font-size: 11.5pt;
        }

        .line {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #E5E7EB;
        }

        .line.total {
            border-bottom: none;
            border-top: 1px solid #111827;
            font-weight: 700;
        }

        .net {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            border: 2px solid #111827;
            margin-top: 16px;
            font-size: 13pt;
            font-weight: 700;
        }

        .note {
            margin-top: 12px;
            padding: 8px;
            border: 1px solid #B45309;
            color: #B45309;
            font-size: 9.5pt;
        }

        footer {
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #E5E7EB;
            font-size: 9pt;
            color: #6B7280;
        }

        .actions {
            text-align: right;
            margin-bottom: 8px;
        }

        .actions button {
            font: inherit;
            padding: 8px 18px;
            border: 1px solid #1D9E75;
            background: #1D9E75;
            color: #FFFFFF;
            cursor: pointer;
        }

        @media print {
            .actions {
                display: none;
            }
        }
    </style>
</head>

<body>
    <div class="sheet">
        <div class="actions">
            <button onclick="window.print()">Print</button>
        </div>

        <div class="brand">
            <div>
                <h1>NKWALEDGER</h1>
                <p>Farm finance records</p>
            </div>
            <h2>{{ $header['title'] }}</h2>
        </div>

        <div class="facts">
            <div class="fact"><span>Farmer</span><strong>{{ $header['farmer_name'] }}</strong></div>
            <div class="fact"><span>Phone</span><strong>{{ $header['farmer_phone'] ?? '—' }}</strong></div>
            <div class="fact"><span>Reference</span><strong>{{ $header['farmer_reference'] }}</strong></div>
            <div class="fact"><span>Period</span><strong>{{ $day($header['from']) }} to
                    {{ $day($header['to']) }}</strong></div>
            <div class="fact">
                <span>Records awaiting approval</span>
                <strong>{{ $header['include_provisional'] ? 'Included' : 'Left out' }}</strong>
            </div>
            <div class="fact"><span>Check code</span><strong>{{ $header['verification_code'] }}</strong></div>
        </div>

        @if ($report['provisional_held_back'] > 0)
            <p class="note">
                GHS {{ $cedis($report['provisional_held_back']) }} sits on a part of the farm that has not been
                checked,
                and is {{ $header['include_provisional'] ? 'included' : 'left out' }} above.
            </p>
        @endif

        @if ($kind === 'statement')
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>What happened</th>
                        <th>Reference</th>
                        <th class="right">In</th>
                        <th class="right">Out</th>
                        <th class="right">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5">Brought forward</td>
                        <td class="right">{{ $cedis($report['opening_balance']) }}</td>
                    </tr>

                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>{{ $day($row['date']) }}</td>
                            <td>
                                {{ $row['description'] }}
                                @if ($row['account'])
                                    <span class="muted">{{ $row['account'] }}</span>
                                @endif
                                @if ($row['value_lost'] > 0)
                                    <span class="muted">Worth GHS {{ $cedis($row['value_lost']) }}, no money
                                        moved</span>
                                @endif
                                @if ($row['cancel_state'] === 'cancelled')
                                    <span class="muted">Cancelled</span>
                                @endif
                                @if ($row['is_provisional'])
                                    <span class="muted">Not counted yet</span>
                                @endif
                            </td>
                            <td>{{ $row['reference'] }}</td>
                            <td class="right">{{ $row['money_in'] > 0 ? $cedis($row['money_in']) : '—' }}</td>
                            <td class="right">{{ $row['money_out'] > 0 ? $cedis($row['money_out']) : '—' }}</td>
                            <td class="right">{{ $cedis($row['balance']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Nothing was recorded in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Totals</td>
                        <td class="right">{{ $cedis($report['total_in']) }}</td>
                        <td class="right">{{ $cedis($report['total_out']) }}</td>
                        <td class="right">{{ $cedis($report['closing_balance']) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif

        @if ($kind === 'income')
            @foreach ([['What came in', $report['income_rows'], $report['total_income']], ['What went out', $report['expense_rows'], $report['total_expense']], ['What was lost', $report['loss_rows'], $report['total_loss']]] as [$label, $rows, $total])
                <div class="section">
                    <h3>{{ $label }}</h3>

                    @forelse ($rows as $row)
                        <div class="line">
                            <span>{{ $row['account'] }}</span>
                            <span>{{ $cedis($row['amount']) }}</span>
                        </div>
                    @empty
                        <div class="line"><span>Nothing here.</span><span>—</span></div>
                    @endforelse

                    <div class="line total">
                        <span>Total</span>
                        <span>{{ $cedis($total) }}</span>
                    </div>
                </div>
            @endforeach

            <div class="net">
                <span>{{ $report['net'] < 0 ? 'Short by' : 'What is left' }}</span>
                <span>GHS {{ $cedis(abs($report['net'])) }}</span>
            </div>
        @endif

        @if ($kind === 'trial-balance')
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Account</th>
                        <th>Side</th>
                        <th class="right">Debit</th>
                        <th class="right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($report['rows'] as $row)
                        <tr>
                            <td>{{ $row['code'] ?? '—' }}</td>
                            <td>{{ $row['account'] }}</td>
                            <td>{{ $row['class'] ?? '—' }}</td>
                            <td class="right">{{ $row['debit'] > 0 ? $cedis($row['debit']) : '—' }}</td>
                            <td class="right">{{ $row['credit'] > 0 ? $cedis($row['credit']) : '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">Nothing was recorded in this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Totals</td>
                        <td class="right">{{ $cedis($report['total_debit']) }}</td>
                        <td class="right">{{ $cedis($report['total_credit']) }}</td>
                    </tr>
                </tfoot>
            </table>

            <p style="margin-top:10px;font-weight:700;">
                {{ $report['is_balanced'] ? 'The books balance.' : 'The books do not balance.' }}
            </p>
        @endif

        <footer>
            <p style="margin:4px 0 0;">
                Prepared by {{ $header['prepared_by'] }} on {{ $day($header['generated_at']) }}.
            </p>
        </footer>
    </div>
</body>

</html>
