<div class="docs-guide" data-doc-guide="shared">
    <header class="docs-guide-intro"><h2>Everyday help for everyone</h2><p>A few shared basics, whichever role you use.</p></header>

    <x-docs-topic id="shared-sign-in" title="Log in, change an initial password, or reset one" role="shared">
        <ol><li>Open AquaFlow and choose <strong>Log in</strong>. Use the email and password for the account your provider or administrator gave you.</li><li>If asked to change a temporary password, complete that step before continuing.</li><li>If asked to verify your email, use the link sent to your inbox. You can request another verification email from the verification screen.</li><li>If you forget your password, select the password-reset link on the login page, enter the account email, and follow the email instructions.</li></ol>
        <p>If a login is inactive, belongs to the wrong provider, or has no linked consumer record, contact your provider or administrator. Resetting a password does not create a missing water account or change your role.</p>
        <p class="docs-note">Do not share your password in chat, in a ticket, or in a payment reference. Be careful when using a shared device, and log out when finished.</p>
    </x-docs-topic>

    <x-docs-topic id="shared-security" title="Keep your account secure" role="shared">
        <p>Open your profile or settings area, then <strong>Security</strong>. The available options can include changing a password, two-factor authentication, and passkeys.</p>
        <ul><li><strong>Password:</strong> Use a password you do not use for another service. Follow the requirements shown by the form.</li><li><strong>Two-factor authentication:</strong> Follow the setup instructions with an authenticator app, and enter the requested code to finish setup. Save the recovery codes privately.</li><li><strong>Recovery code:</strong> Use one when you cannot use your authenticator. Each code can be used once. If you generate new codes, keep the new set.</li><li><strong>Passkey:</strong> If your browser and device support it, follow the prompts to add one. Device or browser confirmation is part of the process.</li></ul>
        <x-docs-example title="Emil loses access to his authenticator">
            <p>Emil uses a saved recovery code at the two-factor challenge. After regaining access, he reviews his security settings and stores the current recovery codes safely.</p>
            <p>If he cannot recover access, he contacts the administrator. He does not send recovery codes or passwords in a support ticket.</p>
        </x-docs-example>
    </x-docs-topic>

    <x-docs-topic id="shared-cycle" title="How readings, bills, and payments fit together" role="shared">
        <p>Each step has a different purpose. A reading records usage, a bill records charges, and a verified payment reduces what is owed.</p>
        <x-docs-flow title="The monthly water billing cycle" :steps="[
            ['actor' => 'Provider', 'label' => 'Record meter reading'],
            ['actor' => 'Provider', 'label' => 'Generate the bill'],
            ['actor' => 'Consumer', 'label' => 'Pay and send proof'],
            ['actor' => 'Provider', 'label' => 'Verify the payment'],
            ['actor' => 'System', 'label' => 'Update balance and receipt'],
        ]" />
        <p>A saved payment can be pending, verified, or rejected. A bill can remain partly unpaid even after one verified payment. A receipt is available for a verified payment.</p>
        <p>For a disconnected account, payment and reconnection are separate steps. The provider must also record the service request and completed restoration.</p>
    </x-docs-topic>

    <x-docs-topic id="shared-support-flow" title="How a support concern moves between roles" role="shared">
        <x-docs-flow title="From a question to a resolution" :steps="[
            ['actor' => 'Consumer', 'label' => 'Submit a concern'],
            ['actor' => 'Provider', 'label' => 'Review and reply'],
            ['actor' => 'Provider', 'label' => 'Escalate if needed'],
            ['actor' => 'Super Admin', 'label' => 'Review and record outcome'],
        ]" />
        <p>Most account questions start with the water provider. Not every concern needs escalation. Consumers can follow public replies in their support history; internal administrative notes are not shown to them.</p>
        <p>Include the bill number, billing month, payment reference, or connection details that help identify the issue. Avoid sending unrelated private information.</p>
    </x-docs-topic>

    <x-docs-topic id="shared-glossary" title="Plain-language word guide" role="shared">
        <dl class="docs-glossary">
            <dt>Consumer</dt><dd>The person or account receiving water service.</dd>
            <dt>Water provider</dt><dd>The organization supplying water and managing service records.</dd>
            <dt>Super Admin</dt><dd>A person who oversees providers, access, and platform-wide concerns.</dd>
            <dt>Account number</dt><dd>The identifier for a consumer’s record with the provider.</dd>
            <dt>Connection / meter</dt><dd>A water service connection and the meter used to measure its usage.</dd>
            <dt>Consumption</dt><dd>The amount of water used: current meter reading minus previous meter reading.</dd>
            <dt>m³</dt><dd>A cubic meter of water. One m³ is 1,000 liters.</dd>
            <dt>Billing month</dt><dd>The month the water charge belongs to.</dd>
            <dt>Due date</dt><dd>The date by which payment is due.</dd>
            <dt>Outstanding balance</dt><dd>The amount still owed after payments and applicable changes have been applied.</dd>
            <dt>Proof of payment</dt><dd>A receipt, image, or document that helps the provider check a payment.</dd>
            <dt>Pending</dt><dd>Recorded and waiting for review. A pending payment has not reduced the balance.</dd>
            <dt>Verified</dt><dd>The provider has accepted the payment and applied it.</dd>
            <dt>Rejected</dt><dd>The payment submission was not accepted. Read the reason before taking the next step.</dd>
            <dt>Credit / debit</dt><dd>A credit reduces a bill charge; a debit adds to it.</dd>
            <dt>Grace period</dt><dd>The configured time after a due date used when checking whether a late fee is eligible.</dd>
            <dt>Escalation</dt><dd>Sending a concern to a Super Admin for further review.</dd>
            <dt>CSV / PDF</dt><dd>CSV is useful for spreadsheets. PDF keeps a document’s layout for saving or printing.</dd>
        </dl>
    </x-docs-topic>
</div>
