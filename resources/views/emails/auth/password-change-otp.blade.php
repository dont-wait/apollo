<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>Apollo Blog — Password change verification code</title>
</head>
<body style="margin:0;padding:32px 12px;background-color:#101318;color:#e5e7eb;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:560px;margin:0 auto;border:1px solid #303640;border-radius:16px;background-color:#191e25;">
        <tr>
            <td style="padding:32px 28px 24px;text-align:center;">
                <div style="display:inline-block;width:38px;height:38px;line-height:38px;border:1px solid #59c8ef;border-radius:10px;background-color:#122a36;color:#72d5fa;font-size:20px;font-weight:bold;">A</div>
                <p style="margin:12px 0 0;color:#91a0af;font-size:11px;letter-spacing:2px;text-transform:uppercase;">Apollo Blog · Account security</p>
                <h1 style="margin:26px 0 10px;color:#f3f4f6;font-size:24px;line-height:1.3;">Your password change code</h1>
                <p style="margin:0;color:#b5bec9;font-size:15px;line-height:1.6;">Enter this one-time code to confirm your Apollo Blog password change.</p>
                <div style="margin:26px 0 18px;padding:18px 12px;border:1px solid #344653;border-radius:12px;background-color:#111820;color:#75d5fb;font-family:Arial,Helvetica,sans-serif;font-size:34px;font-weight:bold;letter-spacing:12px;">{{ $otp }}</div>
                <p style="margin:0;color:#aab4bf;font-size:13px;line-height:1.6;">This code expires in <strong style="color:#f3f4f6;">{{ $expiresInMinutes }} minutes</strong>.</p>
            </td>
        </tr>
        <tr>
            <td style="padding:0 28px 28px;">
                <div style="padding:16px;border-left:3px solid #e8ae57;border-radius:6px;background-color:#25231e;color:#c6c0b4;font-size:13px;line-height:1.6;">
                    For your security, never share this code with anyone. If you did not request a password change, secure your account immediately.
                </div>
                <p style="margin:24px 0 0;text-align:center;color:#72808d;font-size:11px;line-height:1.6;">This is an automated security message from Apollo Blog. Please do not reply.</p>
            </td>
        </tr>
    </table>
</body>
</html>
