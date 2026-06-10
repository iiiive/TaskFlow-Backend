<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Plan Limit Warning</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .header { background: #f59e0b; padding: 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 22px; }
        .body { padding: 32px; }
        .body p { color: #374151; line-height: 1.6; }
        .warning-box { background: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; padding: 16px 20px; margin: 20px 0; text-align: center; }
        .warning-box .percent { font-size: 36px; font-weight: 700; color: #d97706; }
        .warning-box p { margin: 4px 0 0; color: #92400e; font-size: 14px; }
        .btn { display: inline-block; background: #6366f1; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 16px; }
        .footer { padding: 16px 32px; background: #f9fafb; color: #9ca3af; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Plan Limit Warning</h1>
    </div>
    <div class="body">
        <p>Hello,</p>
        <p>Your organization <strong>{{ $organization->name }}</strong> is approaching its plan limit.</p>

        <div class="warning-box">
            <div class="percent">{{ $usagePercent }}%</div>
            <p>of your member limit is used</p>
        </div>

        @if($plan)
        <p>Your current plan <strong>{{ $plan->name }}</strong> allows up to <strong>{{ $plan->max_members }} members</strong>.</p>
        @endif

        <p>Please contact your Planora administrator to upgrade your plan before you reach the limit.</p>
        <a href="{{ $contactUrl }}" class="btn">Go to Planora</a>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Planora. All rights reserved.
    </div>
</div>
</body>
</html>
