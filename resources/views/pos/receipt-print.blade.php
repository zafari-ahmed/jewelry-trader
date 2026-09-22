<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->order_number }} · Receipt</title>
    @vite(['resources/css/app.css'])
    <style>
        /* 80mm thermal stock: no margins, nothing but the receipt. */
        @page { size: 80mm auto; margin: 0; }
        @media print { body { background: #fff; } .no-print { display: none !important; } }
    </style>
</head>
<body class="bg-ivory">
    <div class="mx-auto max-w-thermal py-20">
        @include('pos.partials.thermal', ['order' => $order])
        <div class="no-print mt-16 text-center">
            <button onclick="window.print()" class="cursor-pointer rounded-surface border border-navy bg-navy px-18 py-10 text-body font-semibold text-ivory">Print</button>
        </div>
    </div>
</body>
</html>
