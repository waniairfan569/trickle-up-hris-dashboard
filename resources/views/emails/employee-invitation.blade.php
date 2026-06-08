<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Invitation</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f8fafc; padding: 40px 0;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 30px; text-align: center; border-bottom: 4px solid #fcd82f;">
                            @if(file_exists(public_path('images/logo.png')))
                                <img src="{{ $message->embed(public_path('images/logo.png')) }}" alt="{{ $companyName }}" style="max-height: 45px; width: auto; vertical-align: middle;">
                            @else
                                <h1 style="color: #1a1a24; margin: 0; font-size: 26px; font-weight: 800; letter-spacing: -0.5px;">{{ $companyName }}</h1>
                            @endif
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 40px 40px 20px 40px;">
                            <h2 style="margin-top: 0; color: #1a1a24; font-size: 22px; font-weight: 700;">Welcome to the team!</h2>
                            
                            <p style="color: #4b5563; font-size: 16px; line-height: 26px; margin-bottom: 24px;">Hi {{ $employee->first_name }},</p>
                            
                            <p style="color: #4b5563; font-size: 16px; line-height: 26px; margin-bottom: 32px;">
                                {{ $invitedBy->full_name }} from {{ $companyName }} has invited you to set up your employee profile. 
                                Click the button below to accept your invitation and create your password. 
                            </p>
                            
                            <div style="text-align: center; margin-bottom: 32px;">
                                <a href="{{ $invitationUrl }}" style="display: inline-block; background-color: #1a1a24; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(26, 26, 36, 0.2);">Accept Invitation</a>
                            </div>
                            
                            <p style="color: #6b7280; font-size: 13px; line-height: 20px; margin-bottom: 8px; text-align: center;">Or copy and paste this link into your browser:</p>
                            <p style="color: #3b82f6; font-size: 12px; line-height: 18px; word-break: break-all; text-align: center; margin-bottom: 32px; text-decoration: underline;">
                                <a href="{{ $invitationUrl }}" style="color: #3b82f6;">{{ $invitationUrl }}</a>
                            </p>
                            
                            <p style="color: #9ca3af; font-size: 13px; margin: 0; text-align: center; font-style: italic;">
                                ⚠️ This link will expire securely in {{ $expiresInHours }} hours.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer with Socials -->
                    <tr>
                        <td style="background-color: #ffffff; padding: 20px 40px 40px 40px; text-align: center;">
                            <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin-bottom: 24px;">
                                <tr>
                                    <td align="center">
                                        <!-- Facebook -->
                                        <a href="https://facebook.com" style="display: inline-block; margin: 0 8px; background-color: #f8fafc; border-radius: 50%; padding: 12px; width: 20px; height: 20px; text-align: center;">
                                            <img src="https://cdn-icons-png.flaticon.com/512/145/145802.png" width="20" height="20" alt="Facebook" style="vertical-align: middle; border: 0;">
                                        </a>
                                        <!-- Instagram -->
                                        <a href="https://instagram.com" style="display: inline-block; margin: 0 8px; background-color: #f8fafc; border-radius: 50%; padding: 12px; width: 20px; height: 20px; text-align: center;">
                                            <img src="https://cdn-icons-png.flaticon.com/512/1409/1409946.png" width="20" height="20" alt="Instagram" style="vertical-align: middle; border: 0;">
                                        </a>
                                        <!-- LinkedIn -->
                                        <a href="https://linkedin.com" style="display: inline-block; margin: 0 8px; background-color: #f8fafc; border-radius: 50%; padding: 12px; width: 20px; height: 20px; text-align: center;">
                                            <img src="https://cdn-icons-png.flaticon.com/512/145/145807.png" width="20" height="20" alt="LinkedIn" style="vertical-align: middle; border: 0;">
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Separator -->
                            <div style="width: 80px; height: 1px; background-color: #e2e8f0; margin: 0 auto 24px auto;"></div>
                            
                            <p style="color: #475569; font-size: 15px; margin: 0 0 12px 0; font-weight: 500;">
                                <a href="https://trickleup.co.uk" style="color: #475569; text-decoration: none;">trickleup.co.uk</a> 
                                <span style="color: #cbd5e1; margin: 0 6px;">&middot;</span> 
                                <a href="mailto:hello@trickleup.co.uk" style="color: #475569; text-decoration: none;">hello@trickleup.co.uk</a>
                            </p>
                            <p style="color: #94a3b8; font-size: 14px; margin: 0 0 16px 0;">
                                &copy; {{ date('Y') }} Trickle Up. All rights reserved.
                            </p>
                            
                            <p style="color: #cbd5e1; font-size: 11px; line-height: 16px; margin: 0;">
                                If you did not expect this invitation, you can safely ignore this email.<br>
                                Sent by {{ $invitedBy->full_name }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
