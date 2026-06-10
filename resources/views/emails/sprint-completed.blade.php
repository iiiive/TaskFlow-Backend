<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Sprint Completed</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: Arial, sans-serif; color: #1e293b; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .header { background: #16a34a; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
    .body { padding: 32px 40px; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #475569; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 24px; margin: 20px 0; }
    .info-box p { margin: 0 0 8px; font-size: 14px; }
    .info-box p:last-child { margin: 0; }
    .info-box strong { color: #1e293b; }
    .stat { display: inline-block; background: #dcfce7; color: #15803d; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; }
    .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #16a34a; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
    .footer { padding: 20px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>Sprint Completed ✅</h1>
    </div>
    <div class="body">
      <p>Great work! The sprint in <strong>{{ $project->name }}</strong> has been completed.</p>

      <div class="info-box">
        <p><strong>Sprint:</strong> {{ $sprint->name }}</p>
        <p><strong>Completed:</strong> {{ $sprint->completed_at?->format('M d, Y') }}</p>
        @php
          $total = $sprint->totalStoryPoints();
          $done  = $sprint->completedStoryPoints();
        @endphp
        @if($total > 0)
          <p><strong>Story Points:</strong> <span class="stat">{{ $done }} / {{ $total }} completed</span></p>
        @endif
      </div>

      <a href="{{ config('app.url') }}" class="btn">View Project</a>
    </div>
    <div class="footer">
      <p>You received this because you are a member of {{ $project->name }}.</p>
    </div>
  </div>
</body>
</html>
