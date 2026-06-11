<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome to Planora</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f4f4f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,.1); }
        .header { background: #547a95; padding: 32px; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; }
        .body { padding: 32px; }
        .body p { color: #374151; line-height: 1.6; }
        .creds-box { background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .creds-box h3 { margin: 0 0 8px; color: #0369a1; font-size: 14px; text-transform: uppercase; letter-spacing: .05em; }
        .creds-item { padding: 6px 0; color: #374151; font-size: 14px; }
        .creds-item code { background: #e0f2fe; padding: 2px 8px; border-radius: 4px; font-size: 14px; }
        .plan-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px 20px; margin: 20px 0; }
        .plan-box h3 { margin: 0 0 8px; color: #475569; font-size: 13px; text-transform: uppercase; letter-spacing: .05em; }
        .plan-item { display: flex; justify-content: space-between; padding: 4px 0; color: #374151; font-size: 14px; }
        .btn { display: inline-block; background: #547a95; color: #fff; padding: 12px 28px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 16px; }
        .note { margin-top: 24px; font-size: 13px; color: #6b7280; }
        .footer { padding: 16px 32px; background: #f9fafb; color: #9ca3af; font-size: 12px; text-align: center; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>Welcome to Planora</h1>
    </div>
    <div class="body">
        <p>Hello {{ $admin->name }},</p>
        <p>An administrator account has been created for you to manage
            <strong>{{ $organization->name }}</strong> on Planora. Use the credentials below to sign in.</p>

        <div class="creds-box">
            <h3>Your login credentials</h3>
            <div class="creds-item">Email: <code>{{ $email }}</code></div>
            <div class="creds-item">Temporary password: <code>{{ $password }}</code></div>
        </div>

        @if($plan)
        <div class="plan-box">
            <h3>Subscription: {{ $plan->name }}</h3>
            <div class="plan-item"><span>Max Projects</span><span><strong>{{ $maxProjects }}</strong></span></div>
            <div class="plan-item"><span>Max Members</span><span><strong>{{ $maxMembers }}</strong></span></div>
            <div class="plan-item"><span>Storage</span><span><strong>{{ $storageGb }} GB</strong></span></div>
            <div class="plan-item"><span>Active until</span><span><strong>{{ $expiresAt ?? 'No expiry' }}</strong></span></div>
        </div>
        @endif

        <p>As the organization admin you can create user accounts, set their roles, create projects and teams,
            and assign members.</p>
        <a href="{{ $loginUrl }}" class="btn">Log in to Planora</a>

        <p class="note">For your security, please change your password after your first login.</p>
    </div>
    <div class="footer">
        &copy; {{ date('Y') }} Planora. All rights reserved.
    </div>
</div>
</body>
</html>
