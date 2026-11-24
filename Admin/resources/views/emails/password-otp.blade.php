<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kode OTP SAPA PPA</title>
</head>
<body style="margin:0;background:#f7f7fb;color:#292936;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="padding:28px 32px;background:#e0245e;color:#fff;">
                            <div style="font-size:13px;font-weight:bold;letter-spacing:2px;">SAPA PPA</div>
                            <h1 style="margin:12px 0 0;font-size:24px;">Reset password Anda</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <p style="margin:0 0 20px;line-height:1.6;">Gunakan kode berikut untuk melanjutkan proses penggantian password:</p>
                            <div style="padding:18px;background:#fff1f4;border-radius:12px;text-align:center;color:#e0245e;font-size:34px;font-weight:bold;letter-spacing:10px;">
                                {{ $otp }}
                            </div>
                            <p style="margin:22px 0 0;line-height:1.6;">Kode ini berlaku selama <strong>10 menit</strong>. Jika Anda tidak meminta reset password, abaikan email ini.</p>
                            <p style="margin:16px 0 0;color:#a52842;line-height:1.6;font-size:14px;"><strong>Peringatan keamanan:</strong> Jangan bagikan kode OTP ini kepada siapa pun, termasuk pihak yang mengaku sebagai petugas SAPA PPA.</p>
                            <p style="margin:28px 0 0;color:#777;font-size:13px;">Salam,<br><strong>Tim SAPA PPA</strong></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
