<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>You've been added to a team</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: Arial, sans-serif; color: #1e293b; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .header { background: {{ $team->color ?? '#547A95' }}; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
    .body { padding: 32px 40px; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #475569; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 24px; margin: 20px 0; }
    .info-box p { margin: 0 0 8px; }
    .info-box p:last-child { margin: 0; }
    .info-box strong { color: #1e293b; }
    .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #547A95; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
    .footer { padding: 20px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>You've joined a team on Planora</h1>
    </div>

    <div class="body">
      <p>Hi {{ $user->name ?? $user->email }},</p>

      <p>You have been added to a team on Planora. Here are the details:</p>

      <div class="info-box">
        <p><strong>Team:</strong> {{ $team->name }}</p>
        <p><strong>Your Role:</strong> {{ ucfirst(str_replace('_', ' ', $role)) }}</p>
        @if($team->description)
          <p><strong>Description:</strong> {{ $team->description }}</p>
        @endif
      </div>

      <a href="{{ config('app.url') }}" class="btn">Open Planora</a>
    </div>

    <div class="footer">
      <p>You received this email because you were added to a team on Planora.</p>
    </div>
  </div>
</body>
</html>
