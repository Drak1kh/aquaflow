<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Manage meter readings, water bills, payment verification, and support in AquaFlow. Consumers can check bills and submit payment proof through their account.">
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
                <a href="#features">Features</a><a href="#audiences">Who it's for</a><a href="#how-it-works">How it works</a><a href="/docs">Docs</a>
            </nav>
            <details class="landing-mobile-menu">
                <summary aria-label="Toggle navigation menu">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16" /></svg>
                </summary>
                <nav aria-label="Mobile navigation">
                    @auth
                        <a class="landing-mobile-primary" href="{{ route('dashboard') }}">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}">Sign in</a>
                    @endauth
                    <a href="#features">Features</a><a href="#audiences">Who it's for</a><a href="#how-it-works">How it works</a>
                </nav>
            </details>
            <div class="landing-nav-actions">
                @auth
                    <a class="landing-button landing-button-small" href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    <a class="landing-button landing-button-small" href="{{ route('login') }}">Sign in <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                @endauth
            </div>
        </div>
    </header>

    <x-page-loading class="[&>[data-page-loading]]:mx-auto [&>[data-page-loading]]:max-w-6xl [&>[data-page-loading]]:p-6">
    <main id="main">
        <section class="landing-hero" aria-labelledby="hero-title">
            <div class="landing-container landing-hero-grid">
                <div class="landing-hero-copy">
                    <h1 id="hero-title">Follow your water bill from reading to payment.</h1>
                    <p class="landing-hero-lead">AquaFlow connects each water bill to its meter reading and payment history. Providers manage the records. Consumers can check what they owe and submit proof of payment.</p>
                    <div class="landing-hero-actions">
                        @auth
                            <a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @else
                            <a class="landing-button landing-button-white" href="{{ route('login') }}">Sign in to your account <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                        @endauth
                        <a class="landing-text-link" href="#how-it-works">See the billing steps <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-down" /></svg></a>
                    </div>
                    <p class="landing-hero-trust">Use the login details provided by your water provider or AquaFlow administrator.</p>
                </div>
                <figure class="landing-bill" aria-labelledby="sample-bill-title">
                    <figcaption><h2 id="sample-bill-title">Example water bill</h2><p>Fictional readings and rates, shown to explain the calculation.</p></figcaption>
                    <dl class="landing-bill-readings">
                        <div><dt>Previous reading</dt><dd>100 <span>m³</span></dd></div>
                        <div><dt>Current reading</dt><dd>115 <span>m³</span></dd></div>
                        <div><dt>Water used</dt><dd>15 <span>m³</span></dd></div>
                    </dl>
                    <dl class="landing-bill-charges">
                        <div><dt>Base charge</dt><dd>₱50.00</dd></div>
                        <div><dt>Usage charge <span>15 m³ × ₱10.00</span></dt><dd>₱150.00</dd></div>
                        <div class="landing-bill-total"><dt>Total bill</dt><dd>₱200.00</dd></div>
                    </dl>
                    <p class="landing-bill-note">Your bill uses your provider’s saved rates and readings. A submitted payment updates your balance after the provider verifies it.</p>
                </figure>
            </div>
        </section>

        <section class="landing-section" id="features" aria-labelledby="features-title">
            <div class="landing-container">
                <div class="landing-section-head"><h2 id="features-title">The records behind each water bill.</h2><p>Keep the reading, charges, payment decision, and account history together.</p></div>
                <div class="landing-feature-grid">
                    <article><h3>Consumers and meters</h3><p>Providers register consumers, add water connections and meters, and record monthly readings.</p></article>
                    <article><h3>Monthly billing</h3><p>Generate individual bills or a monthly batch using saved readings, water rates, billing periods, and due dates.</p></article>
                    <article><h3>Payment verification</h3><p>Record a received payment or review a consumer’s submission. Verified payments reduce the balance and have a receipt.</p></article>
                    <article><h3>Balances and service actions</h3><p>Review unpaid bills and record notices, disconnections, and restorations. A payment does not automatically restore service.</p></article>
                    <article><h3>Account updates</h3><p>Consumers receive notifications about bills, payments, service actions, and support responses in their portal.</p></article>
                    <article><h3>Reports and history</h3><p>Review recorded consumption, billing, and payment activity through dashboards, account histories, and reports.</p></article>
                </div>
            </div>
        </section>

        <section class="landing-section landing-audiences" id="audiences" aria-labelledby="audiences-title">
            <div class="landing-container">
                <div class="landing-section-head"><h2 id="audiences-title">One system, with access for each role.</h2><p>Your account opens the tools and records that belong to your role.</p></div>
                <div class="landing-audience-grid">
                    <article><h3>Consumers</h3><p>Check your water usage, bills, balance, and payment history. Submit payment proof, read notifications, or send a support request.</p></article>
                    <article><h3>Water Providers</h3><p>Manage consumers and connections, enter readings, set rates, generate bills, verify payments, and respond to account concerns.</p></article>
                    <article><h3>Super Admins</h3><p>Manage providers, users, and permissions. Review billing and payment activity across providers and handle escalated concerns.</p></article>
                </div>
            </div>
        </section>

        <section class="landing-section" id="how-it-works" aria-labelledby="workflow-title">
            <div class="landing-container">
                <div class="landing-section-head"><h2 id="workflow-title">What happens from reading to receipt?</h2><p>Payment submission and payment verification are separate steps, so you can see where a payment stands.</p></div>
                <ol class="landing-steps">
                    <li><span>01</span><h3>Record the reading</h3><p>The provider enters the meter reading for the billing month.</p></li>
                    <li><span>02</span><h3>Generate the bill</h3><p>AquaFlow calculates usage and charges using the saved readings and rates.</p></li>
                    <li><span>03</span><h3>Submit payment proof</h3><p>The consumer pays through a method accepted by their provider, then submits the details and proof.</p></li>
                    <li><span>04</span><h3>Review the payment</h3><p>The provider checks the submission and verifies or rejects it.</p></li>
                    <li><span>05</span><h3>Check the balance</h3><p>A verified payment reduces the balance and has a receipt. A partial payment leaves the rest due.</p></li>
                </ol>
                <p class="landing-workflow-note">Providers can also record payments they receive. Those payments go through verification too.</p>
            </div>
        </section>

        <section class="landing-section landing-service" aria-labelledby="service-title">
            <div class="landing-container landing-service-grid">
                <div><h2 id="service-title">Ask about a charge. Keep the reply with your request.</h2><p>Send a support request from your consumer account and follow the conversation in the same place. Your provider can respond, update the status, or escalate the concern for further review.</p></div>
                <figure class="landing-request">
                    <figcaption>Example support conversation · Fictional messages</figcaption>
                    <h3>Question about my water bill</h3>
                    <dl><div><dt>Consumer</dt><dd>My usage is higher this month. Could you check the reading on my bill?</dd></div><div><dt>Provider response</dt><dd>We’ve received your request and will review the recorded readings.</dd></div></dl>
                    <p>Consumers can check replies and the request status in Help &amp; Support.</p>
                </figure>
            </div>
        </section>

        <section class="landing-cta" aria-labelledby="cta-title">
            <div class="landing-container landing-cta-inner">
                <div><h2 id="cta-title">Access your AquaFlow account.</h2><p>Need login details? Contact your water provider or AquaFlow administrator.</p></div>
                @auth
                    <a class="landing-button landing-button-white" href="{{ route('dashboard') }}">Open dashboard <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                @else
                    <a class="landing-button landing-button-white" href="{{ route('login') }}">Sign in to AquaFlow <svg class="landing-arrow-icon" aria-hidden="true"><use href="#landing-arrow-up-right" /></svg></a>
                @endauth
            </div>
        </section>
    </main>
    </x-page-loading>

    <footer class="landing-footer">
        <div class="landing-container landing-footer-grid">
            <div class="landing-footer-intro">
                <a class="landing-footer-banner" href="{{ route('home') }}" aria-label="AquaFlow home">
                    <img src="{{ asset('assets/aquaflow-banner-image.png') }}" alt="" width="866" height="288">
                </a>
                <p>Meter readings, water bills, payment records, and support for providers and consumers.</p>
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
                @endauth
            </nav>
        </div>
        <div class="landing-container landing-footer-bottom">
            <span>© {{ date('Y') }} AquaFlow Water Management System</span>

        </div>
    </footer>
</body>
</html>
