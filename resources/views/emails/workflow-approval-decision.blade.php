<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Workflow Decision</title>
  <style>
    body { margin: 0; padding: 0; background: #f1f5f9; font-family: Arial, sans-serif; color: #1e293b; }
    .wrapper { max-width: 560px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,0.08); }
    .header-approved { background: #16a34a; padding: 32px 40px; text-align: center; }
    .header-rejected { background: #dc2626; padding: 32px 40px; text-align: center; }
    .header-approved h1, .header-rejected h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
    .body { padding: 32px 40px; }
    .body p { margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #475569; }
    .info-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px 24px; margin: 20px 0; }
    .info-box p { margin: 0 0 8px; font-size: 14px; }
    .info-box p:last-child { margin: 0; }
    .info-box strong { color: #1e293b; }
    .btn-approved { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #16a34a; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
    .btn-rejected { display: inline-block; margin-top: 20px; padding: 12px 28px; background: #dc2626; color: #ffffff; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 15px; }
    .footer { padding: 20px 40px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="wrapper">
    @if($approved)
      <div class="header-approved">
        <h1>Workflow Approved ✅</h1>
      </div>
    @else
      <div class="header-rejected">
        <h1>Workflow Rejected ❌</h1>
      </div>
    @endif

    <div class="body">
      <p>Hi {{ $recipient->name ?? $recipient->email }},</p>

      <p>
        @if($approved)
          Your ticket has been approved to move to the next workflow state.
        @else
          Your ticket's workflow transition request was rejected.
        @endif
      </p>

      <div class="info-box">
        <p><strong>Ticket:</strong> {{ $ticket->issue_number ? "[{$ticket->issue_number}] " : '' }}{{ $ticket->title }}</p>
        <p><strong>State:</strong> {{ $state->name }}</p>
        @if($reason)
          <p><strong>Reason:</strong> {{ $reason }}</p>
        @endif
      </div>

      <a href="{{ config('app.url') }}" class="{{ $approved ? 'btn-approved' : 'btn-rejected' }}">View Ticket</a>
    </div>

    <div class="footer">
      <p>You received this because you are the reporter or assignee of this ticket.</p>
    </div>
  </div>
</body>
</html>
