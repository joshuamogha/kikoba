<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} · Savings group, SACCOS and VICOBA management</title>
    <meta name="description" content="Run your savings group, VICOBA or SACCOS in one place: members, monthly contributions, shares, loans, financial cycles and statements you can share with members.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --brand-primary: #173d32;
            --brand-secondary: #537454;
            --brand-accent: #dfeaa5;
            --brand-background: #f7f6ef;
            --brand-text: #173d32;
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
        html { scroll-behavior: smooth; }
        body { background: var(--brand-background); color: var(--brand-text); font-size: 16px; line-height: 1.6; }
        a { text-decoration: none; }
        .btn { border-radius: 8px; font-weight: 600; padding: 12px 22px; }
        .btn-primary {
            --bs-btn-bg: var(--brand-primary); --bs-btn-border-color: var(--brand-primary); --bs-btn-color: #fff;
            --bs-btn-hover-bg: #285945; --bs-btn-hover-border-color: #285945; --bs-btn-hover-color: #fff;
            --bs-btn-active-bg: var(--brand-primary); --bs-btn-active-border-color: var(--brand-primary);
        }
        .btn-outline-primary {
            --bs-btn-color: var(--brand-primary); --bs-btn-border-color: var(--brand-primary);
            --bs-btn-hover-bg: var(--brand-primary); --bs-btn-hover-border-color: var(--brand-primary); --bs-btn-hover-color: #fff;
            --bs-btn-active-bg: var(--brand-primary); --bs-btn-active-border-color: var(--brand-primary);
        }
        .btn-accent { background: var(--brand-accent); border: 1px solid var(--brand-accent); color: var(--brand-primary); }
        .btn-accent:hover { background: #d3e08f; border-color: #d3e08f; color: var(--brand-primary); }
        .site-nav { position: sticky; top: 0; z-index: 20; background: #f7f6efee; backdrop-filter: blur(8px); border-bottom: 1px solid var(--line); }
        .site-nav .brand { letter-spacing: 1px; font-size: 22px; font-weight: 750; color: var(--brand-primary); }
        .site-nav .nav-link { color: var(--brand-text); font-weight: 500; font-size: 15px; }
        .site-nav .nav-link:hover { color: var(--brand-secondary); }
        .eyebrow { letter-spacing: 2.1px; text-transform: uppercase; font-size: 11px; font-weight: 700; color: var(--brand-secondary); margin-bottom: 14px; }
        .hero { padding: 84px 0 70px; }
        .hero h1 { font-size: clamp(38px, 5.2vw, 62px); font-weight: 500; line-height: 1.04; letter-spacing: -2px; margin-bottom: 22px; }
        .hero h1 em { font-style: normal; background: linear-gradient(transparent 62%, var(--brand-accent) 62%); }
        .hero .lead { font-size: 19px; color: var(--muted); max-width: 540px; }
        .hero-note { color: var(--muted); font-size: 14px; }
        .statement { background: var(--white); border: 1px solid var(--line); border-radius: 16px; box-shadow: 0 22px 70px #173d3214; overflow: hidden; }
        .statement-head { background: var(--brand-primary); color: #fff; padding: 20px 24px; }
        .statement-head small { opacity: .75; }
        .statement-body { padding: 20px 24px 24px; }
        .statement table { width: 100%; font-size: 13px; }
        .statement th { color: var(--muted); font-weight: 600; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; padding: 6px 4px; }
        .statement td { padding: 7px 4px; border-top: 1px solid var(--line); }
        .statement .num { text-align: right; font-variant-numeric: tabular-nums; }
        .pill { display: inline-block; padding: 2px 10px; border-radius: 99px; font-size: 11px; font-weight: 700; background: var(--brand-accent); color: var(--brand-primary); }
        .mock-note { font-size: 12px; color: var(--muted); text-align: center; margin: 12px 0 0; }
        .band { background: var(--brand-primary); color: #fff; }
        .band .item { padding: 26px 8px; text-align: center; }
        .band .item strong { display: block; font-size: 17px; font-weight: 600; }
        .band .item span { font-size: 13px; opacity: .75; }
        section.block { padding: 84px 0; }
        section.block h2 { font-size: clamp(30px, 3.6vw, 42px); font-weight: 500; letter-spacing: -1.4px; line-height: 1.1; margin-bottom: 14px; }
        .section-lead { color: var(--muted); max-width: 640px; font-size: 17px; }
        .feature { background: var(--white); border: 1px solid var(--line); border-radius: 14px; padding: 28px; height: 100%; }
        .feature .icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; background: var(--brand-accent); color: var(--brand-primary); font-size: 22px; margin-bottom: 18px; }
        .feature h3 { font-size: 20px; font-weight: 600; letter-spacing: -.4px; margin-bottom: 10px; }
        .feature p { color: var(--muted); font-size: 15px; margin-bottom: 14px; }
        .feature ul { list-style: none; padding: 0; margin: 0; font-size: 14px; }
        .feature li { padding: 3px 0 3px 22px; position: relative; }
        .feature li::before { content: "\F26E"; font-family: "bootstrap-icons"; position: absolute; left: 0; color: var(--brand-secondary); }
        .alt { background: #eef0e1; }
        .step { display: flex; gap: 18px; padding: 18px 0; }
        .step .no { flex: 0 0 44px; height: 44px; border-radius: 50%; background: var(--brand-primary); color: #fff; display: grid; place-items: center; font-weight: 700; }
        .step h3 { font-size: 19px; font-weight: 600; margin-bottom: 4px; }
        .step p { margin: 0; color: var(--muted); font-size: 15px; }
        .roles .role { border: 1px solid var(--line); background: var(--white); border-radius: 12px; padding: 18px 20px; height: 100%; }
        .roles .role strong { display: block; font-size: 16px; }
        .roles .role span { font-size: 13px; color: var(--muted); }
        .cta { background: var(--brand-primary); color: #fff; border-radius: 22px; padding: 64px 40px; text-align: center; }
        .cta h2 { color: #fff; }
        .cta p { color: #ffffffcc; max-width: 560px; margin: 0 auto 28px; }
        footer.site-footer { padding: 44px 0 36px; color: var(--muted); font-size: 14px; }
        footer.site-footer .brand { font-size: 18px; font-weight: 750; letter-spacing: 1px; color: var(--brand-primary); }
        @media (max-width: 991px) { .hero { padding: 56px 0 48px; } section.block { padding: 60px 0; } }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    </style>
</head>
<body>
    <nav class="site-nav">
        <div class="container d-flex align-items-center justify-content-between py-3">
            <a href="{{ url('/') }}" class="brand">{{ config('app.name') }}</a>
            <div class="d-none d-md-flex gap-4">
                <a class="nav-link" href="#features">Features</a>
                <a class="nav-link" href="#how">How it works</a>
                <a class="nav-link" href="#reports">Reports</a>
                <a class="nav-link" href="#roles">Roles</a>
            </div>
            <div class="d-flex gap-2">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary btn-sm">Open dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Sign in</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm d-none d-sm-inline-block">Get started</a>
                @endauth
            </div>
        </div>
    </nav>

    <header class="hero">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <p class="eyebrow">Savings groups &middot; VICOBA &middot; SACCOS</p>
                    <h1>One book for <em>every shilling</em> your group keeps.</h1>
                    <p class="lead">Members, monthly contributions, shares and loans in one place. Every payment is recorded once, every balance is worked out for you, and every member can get a clear statement.</p>
                    <div class="d-flex flex-wrap gap-3 mt-4">
                        @auth
                            <a href="{{ route('dashboard') }}" class="btn btn-primary btn-lg">Open your dashboard</a>
                        @else
                            <a href="{{ route('login') }}" class="btn btn-primary btn-lg">Create your group</a>
                        @endauth
                    </div>
                    <p class="hero-note mt-3 mb-0">A new group starts ready to use: funds, financial cycle, fine types, loan products and settings are already set up.</p>
                </div>
                <div class="col-lg-6">
                    <div class="statement" aria-label="Example of a member statement">
                        <div class="statement-head d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-semibold fs-5">Member statement</div>
                                <small>Umoja Group &middot; Financial cycle Dec 2025 - Nov 2026</small>
                            </div>
                            <span class="pill">Print / PDF</span>
                        </div>
                        <div class="statement-body">
                            <table>
                                <thead>
                                    <tr><th></th><th class="num">Dec</th><th class="num">Jan</th><th class="num">Feb</th><th class="num">Mar</th><th class="num">Total</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>Shares</td><td class="num">2</td><td class="num">2</td><td class="num">2</td><td class="num">2</td><td class="num fw-semibold">8</td></tr>
                                    <tr><td>Share amount</td><td class="num">10,000</td><td class="num">10,000</td><td class="num">10,000</td><td class="num">10,000</td><td class="num fw-semibold">40,000</td></tr>
                                    <tr><td>Savings</td><td class="num">20,000</td><td class="num">20,000</td><td class="num">20,000</td><td class="num">20,000</td><td class="num fw-semibold">80,000</td></tr>
                                    <tr><td>Jamii</td><td class="num">5,000</td><td class="num">5,000</td><td class="num">5,000</td><td class="num">5,000</td><td class="num fw-semibold">20,000</td></tr>
                                    <tr><td>Loan balance</td><td class="num" colspan="4"><span class="pill">Active</span></td><td class="num fw-semibold">75,000</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <p class="mock-note">An illustration of a statement with made-up figures.</p>
                </div>
            </div>
        </div>
    </header>

    <div class="band">
        <div class="container">
            <div class="row g-0">
                <div class="col-6 col-md-3 item"><strong>Members &amp; roles</strong><span>Chairperson, Treasurer and more</span></div>
                <div class="col-6 col-md-3 item"><strong>Contributions &amp; shares</strong><span>Month by month, fund by fund</span></div>
                <div class="col-6 col-md-3 item"><strong>Loans</strong><span>From application to the last repayment</span></div>
                <div class="col-6 col-md-3 item"><strong>Reports</strong><span>Print, save as PDF or open in Excel</span></div>
            </div>
        </div>
    </div>

    <section id="features" class="block">
        <div class="container">
            <p class="eyebrow">What it does</p>
            <h2>Everything a group does, in the order a group does it.</h2>
            <p class="section-lead mb-5">Built around how savings groups really work: monthly contributions, shares with a price, loans that follow a rule, and a financial year that ends in a share-out.</p>

            <div class="row g-4">
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-people-fill"></i></div>
                        <h3>Members</h3>
                        <p>Add members by name and phone number, with or without an account, and give each a membership type such as Founder Member.</p>
                        <ul>
                            <li>Import members from CSV or Excel</li>
                            <li>Activate, suspend or remove members</li>
                            <li>Member page with contributions, shares, loans and fines</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-wallet2"></i></div>
                        <h3>Contributions &amp; funds</h3>
                        <p>Keep separate funds (Savings, Social/Jamii, General) and split one payment across them. Each payment says which month it is for.</p>
                        <ul>
                            <li>Contribution calendar shows who paid, who is short, who is missing</li>
                            <li>Filter by member, month, fund and financial cycle</li>
                            <li>Edit, delete, view details, import with receipt numbers</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-pie-chart-fill"></i></div>
                        <h3>Shares</h3>
                        <p>Set a share price and a monthly limit. Members buy shares by paying an amount, and extra shares move on to the next month.</p>
                        <ul>
                            <li>Totals of shares and amount for every member</li>
                            <li>Import share payments from a spreadsheet</li>
                            <li>Share-out at the end of the cycle</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-bank"></i></div>
                        <h3>Loans</h3>
                        <p>Loan products with amount ranges that decide the term, so the loan end date is known from the start.</p>
                        <ul>
                            <li>Applications, guarantors, approval and disbursement</li>
                            <li>Interest once for the whole loan, per month or per year; flat or reducing</li>
                            <li>Installment schedules, repayments and automatic overdue penalties</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-calendar3"></i></div>
                        <h3>Financial cycles</h3>
                        <p>Run each year as a financial cycle that can start in any month. Payments land in the right cycle by the month they are for.</p>
                        <ul>
                            <li>Open, close and reopen cycles</li>
                            <li>No overlapping cycles</li>
                            <li>Reports follow the cycle you choose</li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="feature">
                        <div class="icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                        <h3>Bring your old records</h3>
                        <p>Already keep books on paper or in Excel? Import them instead of typing them again.</p>
                        <ul>
                            <li>Members, shares, contributions, loans and loan repayments</li>
                            <li>CSV and Excel files, with a ready-made template</li>
                            <li>Safe to upload twice: duplicates are skipped</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="how" class="block alt">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5">
                    <p class="eyebrow">How it works</p>
                    <h2>Start in an afternoon, not a season.</h2>
                    <p class="section-lead">You do not have to set up the group's books before you can use them.</p>
                </div>
                <div class="col-lg-7">
                    <div class="step"><div class="no">1</div><div><h3>Create your group</h3><p>Choose the group type and name it. Funds, a financial cycle, fine types, loan products and sensible settings are created for you, and you can change any of them.</p></div></div>
                    <div class="step"><div class="no">2</div><div><h3>Add your members</h3><p>Type them in or import a list of names and phone numbers. Members who register with the same phone number are linked to their account.</p></div></div>
                    <div class="step"><div class="no">3</div><div><h3>Record what happens</h3><p>Add contributions, share payments, loans and repayments as they happen, or import the records you already have.</p></div></div>
                    <div class="step"><div class="no">4</div><div><h3>Share the results</h3><p>Print or save a statement for each member, and the monthly shares and contributions reports for the whole group.</p></div></div>
                </div>
            </div>
        </div>
    </section>

    <section id="reports" class="block">
        <div class="container">
            <div class="row g-5 align-items-center">
                <div class="col-lg-6 order-lg-2">
                    <p class="eyebrow">Reports</p>
                    <h2>Statements members can read and keep.</h2>
                    <p class="section-lead">Every report is laid out with the months across the page, for the financial cycle you choose.</p>
                </div>
                <div class="col-lg-6 order-lg-1">
                    <div class="row g-3">
                        <div class="col-sm-6"><div class="feature"><h3 class="fs-6">Member statement</h3><p class="mb-0">Shares by month, total shares and amount, contributions per fund such as Jamii, and loan history with balances.</p></div></div>
                        <div class="col-sm-6"><div class="feature"><h3 class="fs-6">Monthly shares</h3><p class="mb-0">Every member's shares for each month, with totals.</p></div></div>
                        <div class="col-sm-6"><div class="feature"><h3 class="fs-6">Monthly contributions</h3><p class="mb-0">What each member paid to one fund each month.</p></div></div>
                        <div class="col-sm-6"><div class="feature"><h3 class="fs-6">Loan statement</h3><p class="mb-0">Every loan with what was paid and what is still owed.</p></div></div>
                    </div>
                    <p class="small text-muted mt-3 mb-0"><i class="bi bi-printer"></i> Print or save as PDF from your browser, or download a CSV for Excel.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="roles" class="block alt">
        <div class="container">
            <p class="eyebrow">Who can do what</p>
            <h2>The people who run the group run the books.</h2>
            <p class="section-lead mb-5">Leaders record and approve. Everyone else sees only their own records.</p>
            <div class="row g-3 roles">
                <div class="col-6 col-lg"><div class="role"><strong>Chairperson</strong><span>Mwenyekiti: leads the group and approves transactions.</span></div></div>
                <div class="col-6 col-lg"><div class="role"><strong>Vice Chairperson</strong><span>Makamu Mwenyekiti: deputises the chairperson.</span></div></div>
                <div class="col-6 col-lg"><div class="role"><strong>Secretary</strong><span>Katibu: keeps records and minutes.</span></div></div>
                <div class="col-6 col-lg"><div class="role"><strong>Treasurer</strong><span>Mweka Hazina: receives and banks contributions.</span></div></div>
                <div class="col-12 col-lg"><div class="role"><strong>Member</strong><span>Mwanachama: sees their own contributions, shares, loans and statement.</span></div></div>
            </div>
        </div>
    </section>

    <section class="block">
        <div class="container">
            <div class="cta">
                <h2>Give your group a book it can trust.</h2>
                <p>Create your group, add your members and record your first contribution today.</p>
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-accent btn-lg">Open your dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-accent btn-lg">Create your group</a>
                @endauth
            </div>
        </div>
    </section>

    <footer class="site-footer">
        <div class="container d-flex flex-wrap justify-content-between gap-2">
            <span class="brand">{{ config('app.name') }}</span>
            <span>&copy; {{ date('Y') }} {{ config('app.name') }}. Savings group management.</span>
        </div>
    </footer>
</body>
</html>
