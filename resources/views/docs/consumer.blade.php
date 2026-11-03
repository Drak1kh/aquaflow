<div class="docs-guide" data-doc-guide="consumer">
    <header class="docs-guide-intro"><h2>Consumer guide</h2><p>For the person receiving water service. Check your usage, understand your bill, and send payment details to your provider.</p></header>

    <x-docs-topic id="consumer-start" title="Start with your water account" role="consumer">
        <p>Your water provider records your consumer account, connection, and meter. Ask the provider to activate your portal access if you do not have a login.</p>
        <ol><li>Get your login email and temporary password from your provider privately.</li><li>Log in to AquaFlow. Change the temporary password when asked.</li><li>Open <strong>My Account</strong> and check your account number, service address, and provider details.</li><li>If an official name, address, or meter detail is wrong, contact your provider through <strong>Help &amp; Support</strong>.</li></ol>
        <p class="docs-result"><strong>What happens next:</strong> Your Dashboard brings together the bills and readings connected to your account. A login by itself does not create a water connection.</p>
        <p>Need help signing in? Read <a href="#shared-sign-in" data-doc-topic-link="shared-sign-in">login and password help</a>.</p>
    </x-docs-topic>

    <x-docs-topic id="consumer-dashboard" title="Read your Dashboard and water usage" role="consumer">
        <p>Open <strong>Dashboard</strong> to see the current monthly bill, outstanding balance, payment status, and upcoming due date.</p>
        <ul><li><strong>Current monthly bill</strong> is for the current billing month. It may contain bills for more than one connection.</li><li><strong>Outstanding balance</strong> includes older unpaid amounts, not just this month’s bill.</li><li>The charts show six months of billing and recorded water usage. Open <strong>View monthly values</strong> to read the numbers in a table.</li><li>Open <strong>My Connection</strong> for meter details and reading history. Use the billing-month filter to find a particular period.</li></ul>
        <p class="docs-note">“No bill generated” or “No activity recorded” means there is no matching record yet. It does not mean the account has been disconnected.</p>
    </x-docs-topic>

    <x-docs-topic id="consumer-bills" title="Understand a bill and save a copy" role="consumer">
        <ol><li>Open <strong>My Bills</strong>. Find the bill number and billing month you want.</li><li>Check the consumption, total charges, due date, and remaining balance.</li><li>Select <strong>View / print bill</strong>. A document preview opens in a modal.</li><li>Select <strong>Print / save PDF</strong>. Choose a printer, or your browser’s PDF destination to save a file.</li><li>Close the preview to return to your bills.</li></ol>
        <p>The bill keeps the meter readings and rate used when it was generated. An approved adjustment or penalty can change its charges. The displayed remaining balance reflects payments that have been applied.</p>
        <p class="docs-note">A billing month describes the period charged. The due date tells you when payment is due. They are different.</p>
    </x-docs-topic>

    <x-docs-topic id="consumer-payments" title="Submit payment details and proof" role="consumer">
        <p>First ask your provider which payment methods it accepts and make the payment through that method. AquaFlow records your payment details for review; it is not an online payment gateway.</p>
        <ol><li>Open <strong>Payments</strong> and select <strong>Submit payment</strong>. Or open a bill in <strong>My Bills</strong> and select <strong>Submit payment details</strong> to choose that bill automatically.</li><li>Choose the unpaid bill. Enter the receipt or transaction reference, the amount paid, and the payment date.</li><li>Attach proof: a PDF, JPG, or PNG file up to <strong>5 MB</strong>.</li><li>Select <strong>Submit payment</strong>. If a field needs correction, fix it in the reopened form. Attach the proof again after a validation error.</li></ol>
        <p class="docs-result"><strong>What happens next:</strong> The payment is pending until your provider checks it. Submitting proof does not immediately reduce the bill balance.</p>
        <p class="docs-note">The payment form shows up to 50 recent unpaid bills. For an older bill, start from that bill’s <strong>Submit payment details</strong> button. You cannot submit more than the remaining bill balance.</p>
        <x-docs-flow title="From your payment to a confirmed record" :steps="[
            ['actor' => 'Consumer', 'label' => 'Pay the provider'],
            ['actor' => 'Consumer', 'label' => 'Send details and proof'],
            ['actor' => 'Provider', 'label' => 'Check the payment'],
        ]" :outcomes="[
            ['label' => 'Verified', 'detail' => 'The applied payment reduces the balance. A receipt becomes available.'],
            ['label' => 'Rejected', 'detail' => 'The balance stays the same. Read the reason and contact your provider.'],
        ]" />
    </x-docs-topic>

    <x-docs-topic id="consumer-payment-status" title="Check a payment, partial payment, or rejection" role="consumer">
        <p>Open <strong>Payments</strong> and use <strong>Payment status</strong> to find pending, confirmed, or rejected submissions.</p>
        <ul><li><strong>Pending:</strong> Your provider has not verified the submission yet. Allow time for review or contact the provider.</li><li><strong>Confirmed / verified:</strong> The payment has been accepted. Select <strong>Print receipt</strong> for a document preview.</li><li><strong>Rejected:</strong> Read the reason shown on the payment. The balance has not been reduced.</li></ul>
        <x-docs-example title="Ana makes a partial payment">
            <p>Ana’s bill is ₱250. She submits proof of a ₱100 payment. While it is pending, the balance is still ₱250.</p>
            <p>After the provider verifies and applies the ₱100, the balance is ₱150. The bill is not fully paid until the remaining ₱150 is also settled.</p>
        </x-docs-example>
        <x-docs-example title="Ben’s payment proof is rejected">
            <p>Ben’s screenshot shows a different transaction reference. He reads the rejection reason, checks his receipt, and contacts the provider. If asked to submit again, he uses the correct reference and proof.</p>
            <p>Ben should not pay twice just because the proof was rejected. First confirm whether the provider received his original payment.</p>
        </x-docs-example>
    </x-docs-topic>

    <x-docs-topic id="consumer-notifications" title="Read notifications" role="consumer">
        <ol><li>Open <strong>Notifications</strong> to read updates about bills, payments, service changes, and support responses.</li><li>Use the unread filter when you only want new messages.</li><li>Mark a message as read, or mark all as read after checking them.</li></ol>
        <p>Reading or marking a notification does not pay a bill, verify a payment, or restore service. Open the relevant page to check the actual record.</p>
    </x-docs-topic>

    <x-docs-topic id="consumer-support" title="Ask for help or question a charge" role="consumer">
        <ol><li>For a quick explanation, open <strong>FAQs</strong>. For help with your own account, open <strong>Help &amp; Support</strong>.</li><li>Check your provider’s contact details, or select <strong>Submit concern</strong>.</li><li>Choose a concern type. Add a clear subject and details such as the bill number, month, payment reference, or service problem.</li><li>Submit the concern. Open it under <strong>Your support history</strong> to follow the conversation.</li><li>Select <strong>Reply</strong> to add information. A reply may include a PDF or image up to 5 MB. Closed tickets do not accept replies.</li></ol>
        <x-docs-example title="Carla receives a higher bill than expected">
            <p>Carla compares the bill’s previous and current meter readings with <strong>My Connection</strong>. She opens a billing concern with the bill number and month, explains what looks wrong, and waits for the provider’s review.</p>
            <p>She does not edit official meter readings herself. If the provider cannot resolve the concern, it can escalate the ticket for Super Admin review.</p>
        </x-docs-example>
    </x-docs-topic>

    <x-docs-topic id="consumer-settings" title="Update contact details and protect your login" role="consumer">
        <p>Open <strong>Settings</strong> to update the contact fields available to you. Registered names, meters, and service addresses are maintained by the provider.</p>
        <p>Use the <strong>Security</strong> settings to change your password and manage any available two-factor authentication or passkeys. Keep recovery codes somewhere private and safe.</p>
        <p>For the detailed steps, read <a href="#shared-security" data-doc-topic-link="shared-security">account security</a>. Never include passwords or recovery codes in a support ticket or payment screenshot.</p>
    </x-docs-topic>
</div>
