<!DOCTYPE html>
<html>
<head>
    <title>Subscription Renewal Notification</title>
</head>
<body>
    <h1>Subscription Renewal Notification</h1>
    <p>Dear {{ $resellerName }},</p>

    <p>We are excited to inform you that a user has renewed their subscription through your referral. Here are the details:</p>

    <h2>User Information</h2>
    <p><strong>Name:</strong> {{ $userName }}</p>
    <p><strong>Email:</strong> {{ $userEmail }}</p>
    <p><strong>Registration Date:</strong> {{ \Carbon\Carbon::parse($registrationDate)->format('d/m/Y') }}</p>

    <h2>Business Details</h2>
    <p><strong>Business Name:</strong> {{ $businessName }}</p>
    <p><strong>Package Details:</strong> {{ $subscriptionName }}</p>

    <h2>Payment Information</h2>
    <p><strong>Renewal Amount:</strong> £{{ $renewalAmount }}</p>

    <p>Thank you for your collaboration and ongoing support!</p>

    <p>Best regards,</p>
    <p>HRM Team</p>
</body>
</html>
