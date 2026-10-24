<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Explore the early preview of AquaFlow, a water management platform being built for utilities and consumers.">
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
                    <p class="landing-hero-note"><span></span> AquaFlow is in early preview</p>
                    <h1 id="hero-title">Smarter water management.<br><em>Better service.</em> Every drop counts.</h1>
                    <p class="landing-hero-lead">AquaFlow is being built to bring water consumption monitoring, billing, payment records, service requests, and utility operations together in one intelligent platform.</p>
                    <div class="landing-hero-actions">
                        @auth
                            <a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @else
                            <a class="landing-button landing-button-white" href="{{ route('register') }}">Get started <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @endauth
                        <a class="landing-button landing-button-ghost" href="#features">Explore AquaFlow <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-down" /></svg></a>
                    </div>
                    <p class="landing-hero-trust">Create an account to explore the preview. Water management tools are still in development.</p>
                </div>
                <div class="landing-preview-wrap">
                    <div class="landing-preview-caption"><span>AquaFlow across your devices</span><strong>Illustrative preview</strong></div>
                    <div class="landing-device-stage" role="img" aria-label="Illustrative AquaFlow dashboard on a laptop, billing on a tablet, and service alerts on a phone">
                        <div class="landing-device-laptop" aria-hidden="true">
                            <div class="landing-laptop-frame">
                                <div class="landing-preview">
                                    <div class="landing-preview-sidebar">
                                        <div class="landing-preview-logo"><img src="{{ asset('assets/aquaflow-logo/favicon-96x96.png') }}" alt="" width="25" height="25"> AquaFlow</div>
                                        <span class="selected">Overview</span><span>Consumption</span><span>Billing</span><span>Service requests</span>
                                    </div>
                                    <div class="landing-preview-content">
                                        <div class="landing-preview-top"><div><small>UTILITY OVERVIEW</small><strong>Good morning, Admin</strong></div><b>AF</b></div>
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
                <div><strong>Real-time monitoring</strong><p>Track water usage and system activity.</p></div>
                <div><strong>Automated billing</strong><p>Simplify readings, bills, and payment tracking.</p></div>
                <div><strong>Consumer access</strong><p>Keep account information within reach.</p></div>
                <div><strong>Smart analytics</strong><p>Turn consumption data into useful insight.</p></div>
            </div>
        </section>

        <section class="landing-section" id="features" aria-labelledby="features-title">
            <div class="landing-container">
                <div class="landing-section-head"><div><h2 id="features-title">Everything you need to manage water smarter.</h2></div><p>AquaFlow is designed to connect the essential parts of water utility management in one easy-to-use place.</p></div>
                <div class="landing-feature-grid">
                    <article><span>USAGE</span><h3>Water consumption</h3><p>Monitor household and commercial usage, review history, and spot unusual changes.</p></article>
                    <article><span>BILLING</span><h3>Billing & invoicing</h3><p>Calculate bills from meter readings, rates, and billing periods with less manual work.</p></article>
                    <article><span>PAYMENTS</span><h3>Payment records</h3><p>Give consumers a clear view of balances and recorded payments.</p></article>
                    <article><span>INSIGHTS</span><h3>Analytics & reports</h3><p>Understand consumption, collections, account activity, and operations at a glance.</p></article>
                    <article><span>METERS</span><h3>Meter management</h3><p>Keep meters, readings, history, replacements, and assignments organized.</p></article>
                    <article><span>UPDATES</span><h3>Alerts & notifications</h3><p>Share bill reminders, maintenance notices, interruptions, and announcements.</p></article>
                </div>
                <p class="landing-disclosure">These water management capabilities describe the planned AquaFlow platform. The current app is an early-stage preview.</p>
            </div>
        </section>

        <section class="landing-section landing-audiences" id="audiences" aria-labelledby="audiences-title">
            <div class="landing-container">
                <div class="landing-section-head landing-section-head-centered"><h2 id="audiences-title">A better way to serve—and be served.</h2></div>
                <div class="landing-audience-grid">
                    <article class="landing-audience-admin"><span>FOR UTILITY ADMINISTRATORS</span><h3>Run your water service from one clear view.</h3><p>Keep consumers, meters, billing, collections, reports, and service requests connected.</p><div class="landing-tags"><span>Consumer management</span><span>Meter readings</span><span>Billing</span><span>Reports</span></div><a href="#how-it-works">Explore admin features <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a></article>
                    <article class="landing-audience-consumer"><span>FOR CONSUMERS</span><h3>Your water account, always within reach.</h3><p>Understand usage, view bills and payment history, report concerns, and stay informed.</p><div class="landing-tags"><span>Usage history</span><span>View bills</span><span>Service requests</span><span>Notifications</span></div><a href="{{ route('login') }}">Sign in to the preview <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a></article>
                </div>
            </div>
        </section>

        <section class="landing-section landing-insight" aria-labelledby="insight-title">
            <div class="landing-container landing-insight-grid">
                <div><h2 id="insight-title">See the story behind your water usage.</h2><p>Clear consumption views can help households understand their patterns and give utilities a stronger basis for planning and service.</p><div class="landing-insight-caption"><strong>From readings to insight</strong><span>Usage history · Trends · Billing context</span></div></div>
                <div class="landing-usage-card"><div class="landing-usage-top"><span>Consumption overview</span><span>Example</span></div><strong>24.8 <small>m³</small></strong><p>Current billing period <span>8.4% lower than last month</span></p><div class="landing-bars" aria-hidden="true"><i style="height:48%"></i><i style="height:65%"></i><i style="height:53%"></i><i style="height:79%"></i><i style="height:61%"></i><i style="height:92%"></i><i style="height:72%"></i><i style="height:59%"></i><i style="height:83%"></i><i style="height:68%"></i><i style="height:55%"></i><i style="height:74%"></i></div><div class="landing-bar-axis"><span>Jan</span><span>Mar</span><span>May</span><span>Jul</span><span>Sep</span><span>Nov</span></div></div>
            </div>
        </section>

        <section class="landing-section" id="how-it-works" aria-labelledby="workflow-title">
            <div class="landing-container"><div class="landing-section-head"><div><h2 id="workflow-title">From meter reading to payment.</h2></div><p>Every step stays connected, making everyday work easier to follow.</p></div><ol class="landing-steps"><li><span>01</span><strong>Record</strong><p>Capture accurate meter readings.</p></li><li><span>02</span><strong>Calculate</strong><p>Apply consumption and charges.</p></li><li><span>03</span><strong>Bill</strong><p>Generate bills for each period.</p></li><li><span>04</span><strong>Pay</strong><p>Record customer payments.</p></li><li><span>05</span><strong>Analyze</strong><p>Review usage and collections.</p></li></ol></div>
        </section>

        <section class="landing-section landing-service" aria-labelledby="service-title">
            <div class="landing-container landing-service-grid"><div><h2 id="service-title">Report. Track. Resolve.</h2><p>Give consumers a straightforward way to report water concerns and give teams one place to follow them through.</p><div class="landing-tags"><span>Water leak</span><span>Low pressure</span><span>No supply</span><span>Meter issue</span><span>Billing concern</span></div></div><div class="landing-request-card"><div><strong>Service request</strong><span>Illustrative preview</span></div><h3>Low water pressure</h3><p>Submitted by a consumer</p><ol><li class="done">Submitted</li><li class="done">Assigned</li><li class="current">In progress</li><li>Resolved</li></ol><small>Your request is being reviewed by the service team.</small></div></div>
        </section>

        <section class="landing-section landing-advisory" aria-labelledby="advisory-title"><div class="landing-container landing-advisory-grid"><div><h2 id="advisory-title">Important updates, right when they matter.</h2><p>AquaFlow can help utilities share billing reminders, maintenance schedules, service interruptions, payment confirmations, and community announcements.</p></div><div class="landing-advisory-card"><div><span>Service advisory</span><small>Example notification</small></div><h3>Scheduled maintenance</h3><p>Water service will temporarily be unavailable in selected areas from 9:00 AM to 12:00 PM.</p><span>Service updates · Maintenance</span></div></div></section>

        <section class="landing-cta" aria-labelledby="cta-title"><div class="landing-container landing-cta-inner"><div><h2 id="cta-title">Ready for smarter water management?</h2><p>Create an account to explore AquaFlow's early preview while the water management tools are being built.</p></div><div class="landing-cta-actions">@auth<a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>@else<a class="landing-button landing-button-white" href="{{ route('register') }}">Get started with AquaFlow <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a><a href="{{ route('login') }}">Sign in</a>@endauth</div></div></section>
    </main>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-grid">
            <div class="landing-footer-intro">
                <a class="landing-footer-banner" href="{{ route('home') }}" aria-label="AquaFlow home">
                    <img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="" width="866" height="288">
                </a>
                <p>Connecting water utilities and the communities they serve.</p>
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
