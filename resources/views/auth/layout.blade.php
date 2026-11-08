<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        :root {
            --brand-primary: #173d32;
            --brand-secondary: #537454;
            --brand-accent: #dfeaa5;
            --brand-background: #f7f6ef;
            --brand-text: #173d32;
            --brand-on-primary: #ffffff;
            --muted: #64746b;
            --line: #d9ded3;
            --white: #fffef9;
            --bs-primary: var(--brand-primary);
            --bs-primary-rgb: 23, 61, 50;
            --bs-link-color: var(--brand-primary);
            --bs-link-hover-color: var(--brand-secondary);
            --bs-body-color: var(--brand-text);
            --bs-body-font-family: 'Instrument Sans', Arial, sans-serif;
        }
        body { background: var(--brand-background); color: var(--brand-text); font-size: 16px; line-height: 1.6; }
        a { text-decoration: none; }
        .btn-primary {
            --bs-btn-bg: var(--brand-primary); --bs-btn-border-color: var(--brand-primary); --bs-btn-color: var(--brand-on-primary);
            --bs-btn-hover-bg: #285945; --bs-btn-hover-border-color: #285945; --bs-btn-hover-color: var(--brand-on-primary);
            --bs-btn-active-bg: var(--brand-primary); --bs-btn-active-border-color: var(--brand-primary);
            --bs-btn-disabled-bg: var(--brand-primary); --bs-btn-disabled-border-color: var(--brand-primary);
        }
        .form-check-input:checked { background-color: var(--brand-primary); border-color: var(--brand-primary); }
        .sign-in-page { min-height: 100svh; display: grid; place-items: center; padding: 32px 20px; }
        .sign-in-shell {
            width: min(960px, 100%); display: grid; grid-template-columns: 1fr 1fr;
            border: 1px solid var(--line); border-radius: 16px; overflow: hidden;
            background: var(--white); box-shadow: 0 22px 70px #173d3210;
        }
        .sign-in-welcome {
            background: var(--brand-primary); color: var(--brand-on-primary); padding: 60px 45px;
            display: flex; flex-direction: column; justify-content: space-between; gap: 70px;
        }
        .sign-in-welcome a { color: inherit; }
        .back-home { font-size: 13px; opacity: .85; }
        .back-home:hover { opacity: 1; text-decoration: underline; }
        .brand { letter-spacing: 1px; font-size: 23px; font-weight: 750; }
        .sign-in-welcome h1 { font-size: 46px; font-weight: 500; line-height: 1.05; letter-spacing: -1.8px; overflow-wrap: anywhere; margin-bottom: 24px; }
        .sign-in-welcome p { font-size: 14px; opacity: .85; margin: 0; }
        .sign-in-form { padding: 60px 45px; }
        .sign-in-form h2 { font-size: 32px; font-weight: 500; letter-spacing: -1.2px; line-height: 1.15; margin-bottom: 14px; }
        .sign-in-form > p { color: var(--muted); font-size: 14px; }
        .eyebrow { letter-spacing: 2.1px; text-transform: uppercase; margin: 0 0 24px; font-size: 10px; font-weight: 700; color: var(--brand-text); }
        .sign-in-field { margin: 22px 0; }
        .sign-in-field label { display: block; font-size: 13px; margin-bottom: 8px; font-weight: 600; }
        .sign-in-field .form-control {
            padding: 13px; border: 1px solid var(--line); border-radius: 5px; background: var(--brand-background);
            color: var(--brand-text); box-shadow: none;
        }
        .sign-in-field .form-control:focus { outline: 2px solid var(--brand-secondary); outline-offset: 2px; }
        .password-wrap { position: relative; }
        .password-wrap .form-control { padding-right: 70px; }
        .password-toggle {
            position: absolute; top: 50%; right: 12px; transform: translateY(-50%); border: 0; background: none;
            font-size: 12px; font-weight: 600; color: var(--brand-secondary); cursor: pointer;
        }
        .sign-in-form .btn-primary { width: 100%; margin-top: 8px; border-radius: 5px; padding: 15px 23px; font-size: 13px; font-weight: 600; transition: transform .2s, background .2s; }
        .sign-in-form .btn-primary:hover { transform: translateY(-2px); }
        .sign-in-error { background: #fce8e6; color: #9a2020; padding: 12px; border-radius: 5px; font-size: 13px; }
        .sign-in-status { background: #eaf3e3; color: #2d5a2d; padding: 12px; border-radius: 5px; font-size: 13px; }
        .sign-in-links { margin-top: 25px; font-size: 13px; display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .sign-in-links a { font-weight: 600; }
        @media (max-width: 650px) {
            .sign-in-shell { grid-template-columns: 1fr; }
            .sign-in-welcome { padding: 28px; gap: 25px; }
            .sign-in-welcome h1 { font-size: 30px; margin-bottom: 10px; }
            .sign-in-form { padding: 30px 28px; }
        }
    </style>
</head>
<body class="sign-in-page">
    <main class="sign-in-shell">
        <section class="sign-in-welcome">
            <div class="d-flex flex-column gap-2 align-items-start">
                <a href="{{ route('home') }}" class="brand">{{ config('app.name') }}</a>
                <a href="{{ route('home') }}" class="back-home">&larr; Back to home</a>
            </div>
            <div>
                <h1>@yield('welcome_title')</h1>
                <p>@yield('welcome_text')</p>
            </div>
            <a href="mailto:{{ config('mail.from.address') }}">Need assistance? Contact us ↗</a>
        </section>
        <section class="sign-in-form">
            <p class="eyebrow">@yield('eyebrow', 'MEMBER PORTAL')</p>
            @yield('content')
        </section>
    </main>
    <script>
        document.querySelectorAll('[data-toggle-password]').forEach(function (button) {
            button.addEventListener('click', function () {
                var input = document.getElementById(button.dataset.togglePassword);
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                button.textContent = show ? 'Hide' : 'Show';
            });
        });
    </script>
</body>
</html>
