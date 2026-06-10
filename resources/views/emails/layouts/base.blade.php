<!DOCTYPE html>
<html lang="pt-BR" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no, url=no">
    <title>@yield('title', config('app.name'))</title>
    <style>
        /* Reset */
        *, *::before, *::after { box-sizing: border-box; }
        body { margin: 0; padding: 0; width: 100%; background-color: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; -ms-interpolation-mode: bicubic; }
        a { color: inherit; text-decoration: none; }

        /* Wrapper */
        .email-wrapper { width: 100%; background-color: #f4f4f5; padding: 32px 16px; }
        .email-container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e4e4e7; }

        /* Header */
        .email-header { background-color: #18181b; padding: 24px 32px; text-align: center; }
        .email-header .app-name { color: #ffffff; font-size: 18px; font-weight: 700; letter-spacing: -0.3px; }

        /* Body */
        .email-body { padding: 32px; }
        .email-body h1 { font-size: 20px; font-weight: 700; color: #18181b; margin: 0 0 8px; }
        .email-body p { font-size: 15px; line-height: 1.6; color: #52525b; margin: 0 0 16px; }
        .email-body p:last-child { margin-bottom: 0; }

        /* Button */
        .btn-primary { display: inline-block; background-color: #18181b; color: #ffffff !important; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 6px; text-decoration: none; margin: 8px 0 16px; }
        .btn-primary:hover { background-color: #27272a; }

        /* Divider */
        .divider { border: none; border-top: 1px solid #e4e4e7; margin: 24px 0; }

        /* Footer */
        .email-footer { padding: 20px 32px; background-color: #fafafa; border-top: 1px solid #e4e4e7; text-align: center; }
        .email-footer p { font-size: 12px; color: #a1a1aa; margin: 0; line-height: 1.6; }
        .email-footer a { color: #71717a; text-decoration: underline; }

        /* Info box */
        .info-box { background-color: #f4f4f5; border-radius: 6px; padding: 16px; margin: 16px 0; }
        .info-box p { font-size: 14px; color: #52525b; margin: 0; }

        /* Responsive */
        @media only screen and (max-width: 620px) {
            .email-body { padding: 20px; }
            .email-footer { padding: 16px 20px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table class="email-container" width="100%" cellpadding="0" cellspacing="0" role="presentation">
            {{-- Header --}}
            <tr>
                <td class="email-header">
                    <span class="app-name">{{ config('app.name') }}</span>
                </td>
            </tr>

            {{-- Content --}}
            <tr>
                <td class="email-body">
                    @yield('content')
                </td>
            </tr>

            {{-- Footer --}}
            <tr>
                <td class="email-footer">
                    <p>
                        Você está recebendo este e-mail porque tem uma conta em
                        <a href="{{ config('app.url') }}">{{ config('app.name') }}</a>.<br>
                        &copy; {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
                    </p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
