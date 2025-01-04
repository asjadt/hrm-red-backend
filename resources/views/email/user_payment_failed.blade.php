<!DOCTYPE html>
<html>
<head>
    <title>New User Registration Notification</title>
</head>
<body>
    <h1>New User Registration Notification</h1>
    <p>Dear {{ $resellerName }},</p>

    <p>We regret to inform you that a new user has registered through self-registration using your referral but did not complete the payment setup. Here are the details of the new registration:</p>

    <h2>User Information</h2>
    <p><strong>Name:</strong> {{ $userName }}</p>
    <p><strong>Email:</strong> {{ $userEmail }}</p>
    <p><strong>Registration Date:</strong> {{ \Carbon\Carbon::parse($registrationDate)->format('d/m/Y') }}</p>

    <h2>Business Details</h2>
    <p><strong>Business Name:</strong> {{ $businessName }}</p>
    <p><strong>Package Details:</strong> {{ $subscriptionName }}</p>
    <p><strong>Discount Code (if any):</strong> {{ $discountCode }}</p>

    <p>Thank you for your collaboration and ongoing support!</p>

    <p>Best regards,</p>
    <p>HRM Team</p>
</body>
</html>
