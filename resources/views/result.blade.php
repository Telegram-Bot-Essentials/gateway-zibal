<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Result</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .success {
            color: green;
        }
        .failed {
            color: red;
        }
        .details {
            margin-top: 1.5rem;
            text-align: left;
        }
        .details strong {
            display: inline-block;
            width: 120px;
        }
        .telegram-button {
            margin-top: 2rem;
            display: inline-block;
            background-color: #0088cc;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }
        .telegram-button:hover {
            background-color: #007ab8;
        }
    </style>
</head>
<body>
<div class="container">
    @if ($success)
        <h1 class="success">✅ Payment Successful</h1>
        <p>Thank you for your payment. Your transaction has been completed.</p>
    @else
        <h1 class="failed">❌ Payment Failed</h1>
        <p>Unfortunately, your payment was not successful. Please try again or contact support.</p>
    @endif

    @if (!empty($invoice))
        <div class="details">
            <p><strong>Invoice ID:</strong> {{ $invoice->id }}</p>
            <p><strong>Amount:</strong> {{ currency()->priceFormat($invoice->price) }}</p>
            <p><strong>Status:</strong> {{ $success ? 'Paid' : 'Unpaid' }}</p>
            <p><strong>Reference:</strong> {{ $invoice->reference ?? 'N/A' }}</p>
        </div>
    @endif

    <a href="{{$botLink}}" class="telegram-button">Return to Telegram</a>
</div>
</body>
</html>
