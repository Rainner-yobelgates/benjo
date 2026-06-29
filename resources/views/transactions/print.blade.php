@php
    use App\Support\Money;
    use Illuminate\Support\Facades\Storage;

    $logoUrl = null;

    if (filled($setting?->logo)) {
        $logoUrl = Storage::disk('public')->url($setting->logo);
    }
@endphp
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $transaction->transaction_number }}</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                color: #111827;
                margin: 0;
                background: #f3f4f6;
            }

            .page {
                max-width: 820px;
                margin: 24px auto;
                background: #ffffff;
                padding: 32px;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            }

            .toolbar {
                max-width: 820px;
                margin: 24px auto 0;
                display: flex;
                justify-content: flex-end;
            }

            .print-button {
                border: 0;
                background: #111827;
                color: #ffffff;
                padding: 10px 16px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
            }

            .header {
                display: flex;
                justify-content: space-between;
                gap: 24px;
                align-items: flex-start;
                border-bottom: 2px solid #e5e7eb;
                padding-bottom: 20px;
                margin-bottom: 24px;
            }

            .brand {
                display: flex;
                gap: 16px;
                align-items: flex-start;
            }

            .brand img {
                width: 72px;
                height: 72px;
                object-fit: contain;
            }

            .brand h1 {
                margin: 0 0 8px;
                font-size: 24px;
            }

            .meta {
                text-align: right;
            }

            .meta p,
            .info p,
            .summary p {
                margin: 4px 0;
                font-size: 14px;
            }

            .grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 20px;
                margin-bottom: 24px;
            }

            .card {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 16px;
            }

            .card h2 {
                margin: 0 0 12px;
                font-size: 16px;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                margin-top: 12px;
            }

            th,
            td {
                border-bottom: 1px solid #e5e7eb;
                padding: 10px 8px;
                text-align: left;
                font-size: 14px;
            }

            th:last-child,
            td:last-child {
                text-align: right;
            }

            .summary {
                margin-top: 24px;
                margin-left: auto;
                max-width: 320px;
            }

            .summary-row {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                padding: 8px 0;
                border-bottom: 1px dashed #d1d5db;
            }

            .summary-row.total {
                font-weight: 700;
                font-size: 16px;
                border-bottom: 0;
            }

            .footer-note {
                margin-top: 28px;
                font-size: 12px;
                color: #6b7280;
            }

            @media print {
                body {
                    background: #ffffff;
                }

                .toolbar {
                    display: none;
                }

                .page {
                    box-shadow: none;
                    margin: 0;
                    max-width: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="toolbar">
            <button class="print-button" type="button" onclick="window.print()">Cetak / Simpan PDF</button>
        </div>

        <div class="page">
            <div class="header">
                <div class="brand">
                    @if ($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo Bengkel">
                    @endif

                    <div>
                        <h1>{{ $setting?->shop_name ?? 'Bengkel' }}</h1>
                        @if (filled($setting?->address))
                            <p>{{ $setting->address }}</p>
                        @endif
                        @if (filled($setting?->phone_number))
                            <p>{{ $setting->phone_number }}</p>
                        @endif
                    </div>
                </div>

                <div class="meta">
                    <p><strong>No. Transaksi:</strong> {{ $transaction->transaction_number }}</p>
                    <p><strong>Tanggal:</strong> {{ $transaction->transaction_date?->format('d M Y') }}</p>
                </div>
            </div>

            <div class="grid">
                <div class="card info">
                    <h2>Data Customer</h2>
                    <p><strong>Nama:</strong> {{ $transaction->customer_name }}</p>
                    <p><strong>No. HP:</strong> {{ $transaction->customer_phone ?: '-' }}</p>
                </div>

                <div class="card info">
                    <h2>Data Kendaraan</h2>
                    <p><strong>Kendaraan:</strong> {{ $transaction->vehicle_name ?: '-' }}</p>
                    <p><strong>Deskripsi Servis:</strong> {{ $transaction->service_description ?: '-' }}</p>
                </div>
            </div>

            <div class="card">
                <h2>Daftar Barang Digunakan</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Barang</th>
                            <th>Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($transaction->transactionItems as $item)
                            <tr>
                                <td>{{ $item->item_name }}</td>
                                <td>{{ $item->quantity }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">Tidak ada barang.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="summary">
                <div class="summary-row">
                    <span>Biaya Servis</span>
                    <span>{{ Money::rupiah($transaction->service_fee) }}</span>
                </div>
                <div class="summary-row total">
                    <span>Total Pembayaran Customer</span>
                    <span>{{ Money::rupiah($transaction->service_fee) }}</span>
                </div>
            </div>

            <p class="footer-note">
                Dokumen ini dicetak dari sistem administrasi bengkel.
            </p>
        </div>
    </body>
</html>
