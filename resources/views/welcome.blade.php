<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AquaFlow connects water providers, consumers, and platform administrators through water service, billing, payments, and account management.">
    <title>AquaFlow — Water Management System</title>
    <link rel="icon" href="{{ asset('assets/aquaflow-logo/favicon-96x96.png') }}" type="image/png" sizes="96x96">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="landing">
    <a class="landing-skip" href="#main">Skip to content</a>
    <svg class="landing-icon-sprite" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <symbol id="landing-arrow-up-right" viewBox="0 0 24 24"><path d="M7 7h10v10M7 17 17 7" /></symbol>
        <symbol id="landing-arrow-down" viewBox="0 0 24 24"><path d="M12 5v14m7-7-7 7-7-7" /></symbol>
    </svg>
    <header class="landing-nav">
        <div class="landing-container landing-nav-inner">
            <a class="landing-banner-brand" href="{{ route('home') }}" aria-label="AquaFlow home">
                <img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="" width="866" height="288">
            </a>
            <nav class="landing-nav-links" aria-label="Main navigation">
                <a href="#features">Features</a><a href="#audiences">Who it's for</a><a href="#how-it-works">How it works</a>
            </nav>
            <details class="landing-mobile-menu">
                <summary aria-label="Toggle navigation menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </summary>
                <nav aria-label="Mobile navigation">
                    @auth
                        <a class="landing-mobile-primary" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        @if (Route::has('register'))
                            <a class="landing-mobile-primary" href="{{ route('register') }}">Get started</a>
                        @endif
                        <a href="{{ route('login') }}">Sign in</a>
                    @endauth
                    <a href="#features">Features</a><a href="#audiences">Who it's for</a><a href="#how-it-works">How it works</a>
                </nav>
            </details>
            <div class="landing-nav-actions">
                @auth
                    <a class="landing-button landing-button-small" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="landing-sign-in" href="{{ route('login') }}">Sign in</a>
                    @if (Route::has('register'))
                        <a class="landing-button landing-button-small" href="{{ route('register') }}">Get started <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                    @endif
                @endauth
            </div>
        </div>
    </header>

    <main id="main">
        <section class="landing-hero" aria-labelledby="hero-title">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <p class="landing-hero-note"><span></span> For water providers and their communities</p>
                    <h1 id="hero-title">Water service, billing, and payments.<br><em>One clear flow.</em></h1>
                    <p class="landing-hero-lead">AquaFlow connects water providers, consumers, and platform administrators. Manage accounts, connections, meter readings, bills, payments, and service updates in one place.</p>
                    <div class="landing-hero-actions">
                        @auth
                            <a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @else
                            <a class="landing-button landing-button-white" href="{{ route('register') }}">Get started <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @endauth
                        <a class="landing-button landing-button-ghost" href="#features">Explore AquaFlow <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-down" /></svg></a>
                    </div>
                    <p class="landing-hero-trust">Bring account, billing, payment, and service information into a single view.</p>
                </div>
                <div class="landing-preview-wrap">
                    <div class="landing-preview-caption"><span>AquaFlow across your devices</span><strong>Example screens · Sample data</strong></div>
                    <div class="landing-device-stage" role="img" aria-label="Example AquaFlow provider dashboard on a laptop, consumer billing on a tablet, and service alerts on a phone, with sample data">
                        <div class="landing-device-laptop" aria-hidden="true">
                            <div class="landing-laptop-frame">
                                <div class="landing-preview">
                                    <div class="landing-preview-sidebar">
                                        <div class="landing-preview-logo"><img src="{{ asset('assets/aquaflow-logo/favicon-96x96.png') }}" alt="" width="25" height="25"> AquaFlow</div>
                                        <span class="selected">Overview</span><span>Connections</span><span>Billing</span><span>Payments</span>
                                    </div>
                                    <div class="landing-preview-content">
                                        <div class="landing-preview-top"><div><small>PROVIDER OVERVIEW</small><strong>Good morning, Admin</strong></div><b>AF</b></div>
                                        <div class="landing-preview-metrics"><div><small>Current consumption</small><strong>24.8 <span>m³</span></strong><em>This billing period</em></div><div><small>Collection rate</small><strong>94.6<span>%</span></strong><em>Monthly overview</em></div><div><small>Active consumers</small><strong>2,458</strong><em>Connected accounts</em></div></div>
                                        <div class="landing-preview-chart">
                                            <div><strong>Water consumption</strong><small>Monthly usage overview</small></div>
                                            <svg viewBox="0 0 460 150" preserveAspectRatio="none" aria-hidden="true">
                                                <path d="M0 27H460M0 75H460M0 122H460" stroke="#dfeaf4" stroke-width="1"/>
                                                <path d="M0 110 C48 105 58 64 103 72 S169 117 219 82 S288 33 338 56 S402 79 460 26 V150H0Z" fill="#d9f4fc"/>
                                                <path d="M0 110 C48 105 58 64 103 72 S169 117 219 82 S288 33 338 56 S402 79 460 26" fill="none" stroke="#079EE9" stroke-width="3" stroke-linecap="round"/>
                                            </svg>
                                            <div class="landing-preview-axis"><span>Jan</span><span>Feb</span><span>Mar</span><span>Apr</span><span>May</span><span>Jun</span></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="landing-laptop-base"></div>
                        </div>
                        <div class="landing-device-tablet" aria-hidden="true">
                            <div class="landing-tablet-camera"></div>
                            <div class="landing-tablet-screen">
                                <div class="landing-device-appbar"><img src="{{ asset('assets/aquaflow-logo/favicon-96x96.png') }}" alt="" width="20" height="20"><span>AquaFlow</span></div>
                                <span class="landing-device-screen-label">BILLING</span>
                                <strong>Good afternoon, Maria</strong>
                                <div class="landing-tablet-bill"><span>Current balance</span><b>₱1,240.00</b><small>Due October 28</small></div>
                                <div class="landing-tablet-row"><span>Water usage</span><b>24.8 m³</b></div>
                            </div>
                        </div>
                        <div class="landing-device-phone" aria-hidden="true">
                            <div class="landing-phone-camera"></div>
                            <div class="landing-phone-screen">
                                <div class="landing-device-appbar"><img src="{{ asset('assets/aquaflow-logo/favicon-96x96.png') }}" alt="" width="18" height="18"><span>AquaFlow</span></div>
                                <span class="landing-device-screen-label">SERVICE UPDATE</span>
                                <strong>Stay informed.</strong>
                                <div class="landing-phone-notice"><span>Maintenance notice</span><b>Scheduled service update</b><p>Selected areas · 9 AM–12 PM</p></div>
                                <div class="landing-phone-usage"><span>Current usage</span><b>24.8 m³</b></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="landing-highlights" aria-label="Platform highlights">
            <div class="landing-container landing-highlights-grid">
                <div><strong>Connected records</strong><p>Organize consumers, connections, and meters by provider.</p></div>
                <div><strong>Reading to bill</strong><p>Calculate usage and charges from readings and provider rates.</p></div>
                <div><strong>Payment visibility</strong><p>Follow payments, unpaid bills, and remaining balances.</p></div>
                <div><strong>Account access</strong><p>Give consumers a view of their own service information.</p></div>
            </div>
        </section>

        <section class="landing-section" id="features" aria-labelledby="features-title">
            <div class="landing-container">
                <div class="landing-section-head"><div><h2 id="features-title">The essentials of water service, together.</h2></div><p>Follow each account from connection and meter reading through billing, payment, and service status.</p></div>
                <div class="landing-feature-grid">
                    <article><span>ACCOUNTS</span><h3>Consumers & connections</h3><p>Keep consumer records, service connections, meters, and reading history linked to the right provider.</p></article>
                    <article><span>BILLING</span><h3>Rates & bills</h3><p>Calculate consumption, apply provider rates, and generate bills for each billing period.</p></article>
                    <article><span>PAYMENTS</span><h3>Payments & balances</h3><p>Record and verify payments, handle partial payments, and track what remains due.</p></article>
                    <article><span>SERVICE</span><h3>Service status</h3><p>Track overdue notices, disconnections, and provider-approved reconnections.</p></article>
                    <article><span>OVERSIGHT</span><h3>Reports & audit history</h3><p>Give providers operational reports and Super Admins platform-wide oversight.</p></article>
                    <article><span>PORTAL</span><h3>Consumer access</h3><p>Let consumers view their usage, bills, payment history, notices, and support requests.</p></article>
                </div>
                <p class="landing-disclosure">Keep provider records separate, give consumers access to their own information, and maintain a clear history of important changes.</p>
            </div>
        </section>

        <section class="landing-section landing-audiences" id="audiences" aria-labelledby="audiences-title">
            <div class="landing-container">
                <div class="landing-section-head landing-section-head-centered"><h2 id="audiences-title">Clear work for providers. Clear answers for consumers.</h2></div>
                <div class="landing-audience-grid">
                    <article class="landing-audience-admin"><span>FOR WATER PROVIDERS</span><h3>Follow every account from meter to payment.</h3><p>Manage consumers, connections, readings, bills, collections, and service status within your own provider records.</p><div class="landing-tags"><span>Connections</span><span>Meter readings</span><span>Billing</span><span>Balances</span></div><a href="#how-it-works">See how it works <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a></article>
                    <article class="landing-audience-consumer"><span>FOR CONSUMERS</span><h3>Understand your water account at a glance.</h3><p>View your connection, usage, bills, balances, payment history, notices, and support updates.</p><div class="landing-tags"><span>Usage history</span><span>Bills & balances</span><span>Payment history</span><span>Support</span></div><a href="{{ route('login') }}">Sign in to your account <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a></article>
                </div>
            </div>
        </section>

        <section class="landing-section landing-insight" aria-labelledby="insight-title">
            <div class="landing-container landing-insight-grid">
                <div><h2 id="insight-title">Make each meter reading easier to understand.</h2><p>Consumption history helps consumers compare billing periods and helps providers review usage alongside bills.</p><div class="landing-insight-caption"><strong>From readings to a clearer bill</strong><span>Usage history · Billing periods · Charges</span></div></div>
                <div class="landing-usage-card"><div class="landing-usage-top"><span>Consumption overview</span><span>Example</span></div><strong>24.8 <small>m³</small></strong><p>Current billing period <span>8.4% lower than last month</span></p><div class="landing-bars" aria-hidden="true"><i style="height:48%"></i><i style="height:65%"></i><i style="height:53%"></i><i style="height:79%"></i><i style="height:61%"></i><i style="height:92%"></i><i style="height:72%"></i><i style="height:59%"></i><i style="height:83%"></i><i style="height:68%"></i><i style="height:55%"></i><i style="height:74%"></i></div><div class="landing-bar-axis"><span>Jan</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Nov</span></div></div>
            </div>
        </section>

        <section class="landing-section" id="how-it-works" aria-labelledby="workflow-title">
            <div class="landing-container"><div class="landing-section-head"><div><h2 id="workflow-title">From connection to cleared balance.</h2></div><p>AquaFlow keeps readings, charges, payments, and the remaining balance tied to the right account.</p></div><ol class="landing-steps"><li><span>01</span><strong>Connect</strong><p>Register a consumer and assign a connection and meter.</p></li><li><span>02</span><strong>Read</strong><p>Record the meter reading and calculate usage.</p></li><li><span>03</span><strong>Bill</strong><p>Apply the provider rate and generate a bill.</p></li><li><span>04</span><strong>Pay</strong><p>Record and verify payments when required.</p></li><li><span>05</span><strong>Track</strong><p>Update the balance and review collections.</p></li></ol></div>
        </section>

        <section class="landing-section landing-service" aria-labelledby="service-title">
            <div class="landing-container landing-service-grid"><div><h2 id="service-title">Keep service decisions accountable.</h2><p>Support tickets help consumers raise concerns with their provider. Overdue notices, disconnections, and reconnections follow provider rules; serious unresolved disputes can reach a Super Admin.</p><div class="landing-tags"><span>Billing concern</span><span>Support ticket</span><span>Overdue notice</span><span>Reconnection</span></div></div><div class="landing-request-card"><div><strong>Support ticket</strong><span>Example ticket</span></div><h3>Billing concern</h3><p>Submitted by a consumer</p><ol><li class="done">Submitted</li><li class="done">Reviewed</li><li class="current">In progress</li><li>Resolved</li></ol><small>Your provider is reviewing this concern.</small></div></div>
        </section>

        <section class="landing-section landing-advisory" aria-labelledby="advisory-title"><div class="landing-container landing-advisory-grid"><div><h2 id="advisory-title">Stay informed about bills and service.</h2><p>Notifications cover new bills, due dates, payment status, disconnection notices, reconnection updates, and service advisories.</p></div><div class="landing-advisory-card"><div><span>Billing reminder</span><small>Example notification</small></div><h3>Upcoming due date</h3><p>Your current water bill is due soon. View the bill and outstanding balance in your account.</p><span>Account updates · Billing</span></div></div></section>

        <section class="landing-cta" aria-labelledby="cta-title"><div class="landing-container landing-cta-inner"><div><h2 id="cta-title">Bring your water service into one clear flow.</h2><p>Create an account to manage your water service information with AquaFlow.</p></div><div class="landing-cta-actions">@auth<a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>@else<a class="landing-button landing-button-white" href="{{ route('register') }}">Get started with AquaFlow <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a><a href="{{ route('login') }}">Sign in</a>@endauth</div></div></section>
    </main>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-grid">
            <div class="landing-footer-intro">
                <a class="landing-footer-banner" href="{{ route('home') }}" aria-label="AquaFlow home">
                    <img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="" width="866" height="288">
                </a>
                <p>Connecting water providers and the consumers they serve.</p>
            </div>
            <nav class="landing-footer-links" aria-label="Explore">
                <h2>Explore</h2>
                <a href="#features">Features</a>
                <a href="#audiences">Who it's for</a>
                <a href="#how-it-works">How it works</a>
            </nav>
            <nav class="landing-footer-links" aria-label="Account">
                <h2>Account</h2>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a href="{{ route('login') }}">Sign in</a>
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}">Create account</a>
                    @endif
                @endauth
            </nav>
        </div>
        <div class="landing-container landing-footer-bottom">
            <span>© {{ date('Y') }} AquaFlow Water Management System</span>
            <span>Every drop counts.</span>
        </div>
    </footer>
</body>
</html>
