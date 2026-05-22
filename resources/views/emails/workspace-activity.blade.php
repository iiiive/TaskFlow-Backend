<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Workspace Activity Notification</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6fb; font-family: Arial, sans-serif; color: #1f2937;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6fb; padding: 32px 0;">
        <tr>
            <td align="center">
                <table width="620" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 16px; overflow: hidden; border: 1px solid #e5e7eb;">
                    
                    <tr>
                        <td style="background-color: #111827; padding: 24px 32px;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 22px; font-weight: 700;">
                                Planora
                            </h1>
                            <p style="margin: 6px 0 0; color: #d1d5db; font-size: 14px;">
                                Workspace Activity Notification
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 32px;">
                            <p style="margin: 0 0 16px; font-size: 16px;">
                                Hello,
                            </p>

                            <p style="margin: 0 0 20px; font-size: 15px; line-height: 1.6;">
                                A new activity was recorded in one of your Planora workspaces.
                            </p>

                            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; margin: 24px 0;">
                                <tr>
                                    <td style="padding: 14px 18px; background-color: #f9fafb; font-size: 13px; color: #6b7280; width: 160px;">
                                        Workspace
                                    </td>
                                    <td style="padding: 14px 18px; font-size: 14px; color: #111827;">
                                        {{ $workspaceName ?? 'Workspace' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 14px 18px; background-color: #f9fafb; font-size: 13px; color: #6b7280;">
                                        Action
                                    </td>
                                    <td style="padding: 14px 18px; font-size: 14px; color: #111827;">
                                        {{ $action ?? 'New activity' }}
                                    </td>
                                </tr>

                                <tr>
                                    <td style="padding: 14px 18px; background-color: #f9fafb; font-size: 13px; color: #6b7280;">
                                        Description
                                    </td>
                                    <td style="padding: 14px 18px; font-size: 14px; color: #111827; line-height: 1.5;">
                                        {{ $description ?? 'A workspace activity was created.' }}
                                    </td>
                                </tr>

                                @if (!empty($actorName))
                                    <tr>
                                        <td style="padding: 14px 18px; background-color: #f9fafb; font-size: 13px; color: #6b7280;">
                                            Done by
                                        </td>
                                        <td style="padding: 14px 18px; font-size: 14px; color: #111827;">
                                            {{ $actorName }}
                                        </td>
                                    </tr>
                                @endif

                                @if (!empty($ticketTitle))
                                    <tr>
                                        <td style="padding: 14px 18px; background-color: #f9fafb; font-size: 13px; color: #6b7280;">
                                            Ticket
                                        </td>
                                        <td style="padding: 14px 18px; font-size: 14px; color: #111827;">
                                            {{ $ticketTitle }}
                                        </td>
                                    </tr>
                                @endif
                            </table>

                            @if (!empty($frontendUrl))
                                <div style="margin-top: 28px;">
                                    <a href="{{ $frontendUrl }}"
                                       style="display: inline-block; background-color: #111827; color: #ffffff; text-decoration: none; padding: 12px 20px; border-radius: 10px; font-size: 14px; font-weight: 600;">
                                        Open Planora
                                    </a>
                                </div>
                            @endif

                            <p style="margin: 28px 0 0; font-size: 13px; color: #6b7280; line-height: 1.5;">
                                You received this email because you are a member of this workspace.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding: 18px 32px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0; font-size: 12px; color: #9ca3af;">
                                © {{ date('Y') }} Planora. All rights reserved.
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>