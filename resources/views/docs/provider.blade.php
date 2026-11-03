<div class="docs-guide" data-doc-guide="provider">
    <header class="docs-guide-intro"><h2>Water Provider guide</h2><p>For staff recording consumers, meter readings, bills, and payments. Work only with the records assigned to your provider.</p></header>

    <x-docs-topic id="provider-start" title="Prepare for your first billing month" role="provider">
        <p>Get your staff login from your Super Admin. Change a temporary password when prompted. Your menus and actions depend on the permissions assigned to your role.</p>
        <ol><li>Check the provider’s details and billing rules in <strong>Profile Settings</strong>.</li><li>Register a consumer in <strong>Consumer Management</strong>.</li><li>Add the connection and meter in <strong>Meter Readings</strong>.</li><li>Set the applicable water rate and billing period in <strong>Billing Management</strong>.</li><li>Record the meter reading, preview the bill, and generate it.</li></ol>
        <p class="docs-result"><strong>What happens next:</strong> The bill appears in the consumer’s portal if portal access has been activated. Payment still needs to be recorded and verified separately.</p>
        <p>See the <a href="#shared-cycle" data-doc-topic-link="shared-cycle">complete meter-to-payment flow</a> for who handles each step.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-consumers" title="Register, edit, and activate a consumer" role="provider">
        <ol><li>Open <strong>Consumer Management</strong> and select <strong>Register consumer</strong>.</li><li>Enter the name, service address, and available contact details. Leave the account number blank to generate one, or enter the provider’s own unique number.</li><li>Use <strong>Edit</strong> on a consumer row to correct permitted details. Save the changes in the modal.</li><li>Select <strong>Activate portal</strong> for a consumer without a portal account. Enter a unique login email and a temporary password of at least 12 characters.</li><li>Share those login details privately. The consumer must change the temporary password when asked.</li></ol>
        <p>Registering a consumer and activating portal access are separate tasks. A consumer can have an operational record before they can log in.</p>
        <p class="docs-note">Activate / Deactivate on a consumer row changes account availability. It is not the same as physically disconnecting or restoring water service.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-connections" title="Add a water connection and meter" role="provider">
        <ol><li>Open <strong>Meter Readings</strong> and select <strong>Add connection</strong>.</li><li>Choose the consumer. Enter the connection number and meter number, plus the installation date if known.</li><li>Save the connection. Check that the meter belongs to the correct consumer before recording usage.</li></ol>
        <p>A consumer may have more than one connection. Each connection has its own readings and monthly bill.</p>
        <p class="docs-note">The form lists up to 50 matching consumers. Use the page search to narrow the choices if someone is missing.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-readings" title="Record and correct meter readings" role="provider">
        <ol><li>Open <strong>Meter Readings</strong> and select <strong>Record reading</strong>.</li><li>Choose the connection and billing month. For its first reading only, enter the initial previous reading if needed.</li><li>Enter the current meter reading and select <strong>Save reading</strong>. Later months use the last saved reading as the previous value automatically.</li><li>In the reading history, use <strong>Save correction</strong> when an editable reading needs correction.</li></ol>
        <p>Record months in order. The current reading cannot be lower than the previous reading, and a connection cannot have two readings for the same month.</p>
        <x-docs-example title="A reading needs a correction">
            <p>A staff member enters 151 instead of 115. If no bill or later reading uses it, they can correct the latest reading in the history.</p>
            <p>If a bill has already been generated, the reading is locked. Ask the Super Admin to review the billing issue rather than changing later readings to hide the mistake.</p>
        </x-docs-example>
    </x-docs-topic>

    <x-docs-topic id="provider-rates" title="Set a rate and billing due date" role="provider">
        <ol><li>In <strong>Billing Management</strong>, select <strong>Set water rate</strong>. Enter the base charge, price per m³, and effective date.</li><li>Under <strong>Billing periods</strong>, choose the billing month and due date, then select <strong>Create billing period</strong>.</li><li>Check the rate’s effective date and the intended month before generating bills.</li></ol>
        <p>The base charge is a fixed amount. The price per m³ is multiplied by the recorded water consumption. Historical bills keep their saved readings and rate; a new rate does not rewrite old bills.</p>
        <p class="docs-note">If there is no applicable rate for a month, billing cannot proceed. A saved billing period supplies that month’s due date; otherwise the provider’s configured due-day rules apply.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-bills" title="Preview and generate bills" role="provider">
        <ol><li>In <strong>Billing Management</strong>, choose the connection and month under <strong>Connection to preview</strong>, then select <strong>Preview bill</strong>.</li><li>Review consumption and charges. Previewing alone does not create a saved bill.</li><li>Select <strong>Generate bill</strong> for a single connection, or <strong>Generate monthly batch</strong> for the month’s eligible active connections.</li><li>Review the result. A batch skips connections that already have a bill or do not have a reading for that month.</li><li>Open a saved bill number to preview the document. Select <strong>Print / save PDF</strong> when you need a copy.</li></ol>
        <x-docs-example title="A provider generates its first bill">
            <p>For a fictional connection, the previous reading is 100 m³ and the current reading is 115 m³. Consumption is 15 m³.</p>
            <p>With a ₱50 base charge and a ₱10 rate per m³, the bill is ₱200 before any adjustment or penalty: ₱50 + (15 × ₱10).</p>
        </x-docs-example>
        <p class="docs-note">If generation fails, check the reading, the rate’s effective date, and whether the month already has a bill. Do not generate a second payment or bill to work around an error.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-payments" title="Record, verify, or reject a payment" role="provider">
        <ol><li>Open <strong>Payment Management</strong>. Use the payment form to record a payment received by the provider, or review a consumer’s submitted payment.</li><li>Check the bill, reference, amount, payment date, and method. Review attached proof where available. Recording a payment does not verify it.</li><li>Compare the submission with your provider’s actual payment records.</li><li>Verify a valid pending payment. Reject an invalid submission with a clear reason.</li><li>Open <strong>Receipt</strong> for a verified payment to preview and print it.</li></ol>
        <p class="docs-result"><strong>What happens next:</strong> Verification applies the payment to the bill. Partial payments reduce the balance; the bill becomes fully paid only when its remaining balance reaches zero.</p>
        <p>Rejecting proof does not refund money or reverse a bank transaction. Resolve any money movement with the provider’s payment process. See <a href="#consumer-payment-status" data-doc-topic-link="consumer-payment-status">the consumer’s payment examples</a> for how these outcomes appear to them.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-balances" title="Review balances, penalties, and adjustments" role="provider">
        <p>Open <strong>Outstanding Balances</strong> to review unpaid bills. Check the due date, remaining amount, and any penalty already applied.</p>
        <p>Where available, <strong>Apply eligible penalty</strong> applies the configured late fee after the due date and grace period. A late fee is not applied repeatedly to the same bill.</p>
        <p><strong>Payment Management</strong> also shows approved bill adjustments. A credit reduces a charge; a debit adds a charge. Approval controls are restricted to Super Admins. Ask for review instead of changing a historical reading or inventing a payment to correct a bill.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-service" title="Record disconnection and service restoration" role="provider">
        <p>Open <strong>Disconnection &amp; Reconnection</strong>. Search for the consumer and review the balance and service history before choosing an action.</p>
        <ol><li>For an eligible overdue account, choose <strong>Record disconnection notice</strong>, enter the notice details, and select <strong>Record action</strong>.</li><li>After the required notice, record the disconnection when it takes place.</li><li>After all outstanding bills on the consumer account are cleared, record <strong>Request reconnection</strong>.</li><li>Use <strong>Complete service restoration</strong> when restoration has actually been completed.</li></ol>
        <x-docs-flow title="Service changes require separate records" :steps="[
            ['actor' => 'Provider', 'label' => 'Record notice'],
            ['actor' => 'Provider', 'label' => 'Record disconnection'],
            ['actor' => 'Payment review', 'label' => 'Clear account balance'],
            ['actor' => 'Provider', 'label' => 'Request reconnection'],
            ['actor' => 'Provider', 'label' => 'Complete restoration'],
        ]" />
        <x-docs-example title="Dina has paid but is still disconnected">
            <p>The provider first verifies and applies Dina’s payment. Once all outstanding bills are cleared, it records a reconnection request and later records completed restoration.</p>
            <p>The payment alone does not change the connection back to active. The service team still needs to complete and record the restoration.</p>
        </x-docs-example>
        <p class="docs-note">If an action is unavailable, check the account’s unpaid bills, due dates, current service status, and most recent service action.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-reports" title="Read reports and check unusual usage" role="provider">
        <ul><li><strong>Billing Reports:</strong> Choose a daily, monthly, or yearly summary and a date range, then generate the report. These summaries use bill generation dates.</li><li><strong>Water Consumption Reports:</strong> Filter by billing month or consumer to compare usage with the preceding recorded period. Review flags highlight large increases or decreases; they do not prove a leak or a meter fault.</li><li><strong>Dashboard:</strong> View six-month charts and open <strong>View monthly values</strong> for the numbers behind a chart.</li></ul>
        <p>Check the selected date range and consumer search before interpreting an empty report. A report filter does not change the underlying records.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-support" title="Respond to concerns and escalate a ticket" role="provider">
        <ol><li>Open <strong>Support Tickets</strong> and read the consumer’s concern and previous replies.</li><li>Check the relevant bill, payment, meter, or service record before responding.</li><li>Reply with the next step in simple language. Attach a relevant document if needed.</li><li>If the concern needs platform-level review, use the escalation action to send it to a Super Admin.</li></ol>
        <p>Consumers see public replies to their tickets. Administrative internal notes are kept separate from the consumer conversation.</p>
        <p>See <a href="#shared-support-flow" data-doc-topic-link="shared-support-flow">how a concern moves between roles</a>.</p>
    </x-docs-topic>

    <x-docs-topic id="provider-settings" title="Maintain provider details and billing rules" role="provider">
        <p>Open <strong>Profile Settings</strong> to maintain the available provider contact fields, due days, grace period, and late fee. Only staff with the required permission can change these settings.</p>
        <p>Double-check billing rules before saving. Changes affect applicable future actions; they do not rewrite the snapshots in existing bills. Keep your office phone, email, and address accurate so consumers know how to reach you.</p>
    </x-docs-topic>
</div>
