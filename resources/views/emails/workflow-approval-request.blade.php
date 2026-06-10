<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Approval Required</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: Arial, sans-serif; color: #1e293b; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .header { background: #7c3aed; padding: 32px 40px; text-align: center; }
    .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
    .body { padding: 32px 40px; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #475569; }
    .info-box { background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 8px; padding: 20px 24px; margin: 20px 0; }
    .info-box p { margin: 0 0 8px; font-size: 14px; }
    .info-box p:last-child { margin: 0; }
    .info-box strong { color: #1e293b; }
    .state-badge { display: inline-block; background: {{ $toState->color }}22; color: {{ $toState->color }}; padding: 4px 12px; border-radius: 20px; font-weight: 700; font-size: 13px; border: 1px solid {{ $toState->color }}44; }
    .btn { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #7c3aed; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
    .footer { padding: 20px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="wrapper">
    <div class="header">
      <h1>Approval Required</h1>
    </div>
    <div class="body">
      <p>Hi {{ $approver->name ?? $approver->email }},</p>

      <p>A ticket requires your approval to move to a new workflow state.</p>

      <div class="info-box">
        <p><strong>Ticket:</strong> {{ $ticket->issue_number ? "[{$ticket->issue_number}] " : '' }}{{ $ticket->title }}</p>
        <p><strong>Requested State:</strong> <span class="state-badge">{{ $toState->name }}</span></p>
        @if($ticket->assignee)
          <p><strong>Assigned To:</strong> {{ $ticket->assignee->name ?? $ticket->assignee->email }}</p>
        @endif
      </div>

      <a href="{{ config('app.url') }}" class="btn">Review in Planora</a>
    </div>
    <div class="footer">
      <p>You received this because you are a project manager on this project.</p>
    </div>
  </div>
</body>
</html>
