<div class="docs-guide" data-doc-guide="admin">
    <header class="docs-guide-intro"><h2>Super Admin guide</h2><p>For people overseeing AquaFlow across providers. Set up access, review platform activity, and handle issues that need a wider review.</p></header>

    <x-docs-topic id="admin-start" title="Set up a provider and its staff" role="admin">
        <ol><li>Open <strong>Provider Management</strong> and select <strong>Register provider</strong>.</li><li>Record the provider’s name, unique code, and contact details.</li><li>Open <strong>User Management</strong> to create the staff login and assign the Water Provider role and correct provider.</li><li>Review <strong>Roles &amp; Permissions</strong> before staff begin work.</li><li>Ask provider staff to check billing settings, add consumers and meters, and prepare their first billing month.</li></ol>
        <x-docs-flow title="From provider setup to day-to-day work" :steps="[
            ['actor' => 'Super Admin', 'label' => 'Register provider'],
            ['actor' => 'Super Admin', 'label' => 'Assign staff and permissions'],
            ['actor' => 'Provider staff', 'label' => 'Set up accounts and billing'],
            ['actor' => 'Consumers', 'label' => 'Use the portal'],
        ]" />
        <p class="docs-note">Creating a staff login does not create consumer records, meter readings, rates, or bills. These are separate setup tasks.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-providers" title="Manage providers and open their operations" role="admin">
        <p>Use <strong>Provider Management</strong> to search for providers, edit their details, and change their active status.</p>
        <p>Select <strong>Open operations</strong> on the provider you intend to work with. This selects the provider context for the operational screens. Check the provider name before creating or changing any record.</p>
        <p>If the system asks you to select a provider, return to <strong>Provider Management</strong> and choose <strong>Open operations</strong> rather than guessing which provider is active.</p>
        <p class="docs-note">Deactivating a provider can prevent its staff and consumers from using their accounts. Review the impact before changing status.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-users" title="Manage logins and help a user regain access" role="admin">
        <ol><li>Open <strong>User Management</strong>. Search for the person by the information available in the list.</li><li>Check their role, provider association, and active status before changing anything.</li><li>Use the available edit or password-reset action on that user’s record.</li><li>Share temporary credentials privately and explain that a password change may be required at the next login.</li></ol>
        <p>A consumer’s operational record and portal login are related but different records. Provider staff activate consumer portal access from <strong>Consumer Management</strong>.</p>
        <x-docs-example title="A staff member cannot access a provider">
            <p>A Super Admin checks that the staff user and provider are active and that the login belongs to the correct provider. They then check role permissions.</p>
            <p>They do not create a second consumer account or reassign someone to an unrelated provider simply to remove an access message.</p>
        </x-docs-example>
    </x-docs-topic>

    <x-docs-topic id="admin-permissions" title="Control roles and permissions" role="admin">
        <ol><li>Open <strong>Roles &amp; Permissions</strong> and select the role you need to review.</li><li>Review the allowed actions for that role, such as viewing bills or managing consumers.</li><li>Make only the intended changes and save them.</li><li>Confirm the affected staff know which actions they can perform.</li></ol>
        <p>These settings affect the role, not just the person whose name you last viewed. A person may be able to view a page but not create or update its records.</p>
        <p class="docs-note">Permissions do not turn a Consumer into provider staff. Use the correct role and provider association for the work a user needs to do.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-oversight" title="Review bills, payments, and approved changes" role="admin">
        <ul><li>Open <strong>Billing Oversight</strong> to filter by provider, billing month, and status. Open a bill to review charges, adjustments, and billing activity.</li><li>Use <strong>View / print bill</strong> to open the document preview. Printing does not change the bill.</li><li>Open <strong>Payment Oversight</strong> to review recorded payments and available proof. Check both the submission and verification status.</li></ul>
        <p>For an approved bill correction, open the provider’s operations and <strong>Payment Management</strong>. Under <strong>Approved bill adjustments</strong>, use the bill’s adjustment form, choose credit or debit, and record the amount and a clear reason.</p>
        <p>A credit reduces the bill charge. A debit increases it. Review the supporting records and resulting balance before approving a change; keep the explanation meaningful for future review.</p>
        <p class="docs-note">Changing a bill is different from verifying a payment. Do not invent a payment to compensate for a billing mistake.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-reports" title="Generate and export platform reports" role="admin">
        <ol><li>Open <strong>Reports &amp; Analytics</strong>.</li><li>Select the report type and, where relevant, a provider and date range.</li><li>Select <strong>Generate</strong> and check that the records match your intended scope.</li><li>Select <strong>Download CSV</strong> for a spreadsheet-friendly file, or <strong>Print / save PDF</strong> to open a document preview.</li><li>In the preview, print the document or choose the browser’s PDF destination. Close the modal to return to the report.</li></ol>
        <p>The report document shows its provider scope, dates, and record count. Check these before sharing it. If the result is empty, review the selected report type and filters.</p>
        <p class="docs-note">Collections reports count verified payments. Pending submissions should not be treated as collected funds.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-concerns" title="Review an escalated concern" role="admin">
        <ol><li>Open <strong>Complaints &amp; Disputes</strong> and select the escalated ticket.</li><li>Read the consumer’s question, provider replies, and related account records.</li><li>Use the review controls to assign the case where needed and update its status.</li><li>Add a public response when the consumer needs an update. Use an internal note only for information meant for the administrative review.</li><li>Record a clear resolution when the review is complete.</li></ol>
        <x-docs-example title="A billing concern needs a second review">
            <p>The provider escalates a ticket because the consumer still questions an older charge. The Super Admin checks the saved readings, rate, adjustments, and payment history before responding.</p>
            <p>The resolution explains what was checked and whether a correction is needed. Internal notes are not used as a substitute for a clear public reply.</p>
        </x-docs-example>
        <p>See the <a href="#shared-support-flow" data-doc-topic-link="shared-support-flow">support flow</a> for the handoff between roles.</p>
    </x-docs-topic>

    <x-docs-topic id="admin-settings" title="Review platform settings and activity" role="admin">
        <p>Use <strong>System Settings</strong> for the available platform name, default due-day and late-fee settings, date format, and escalated-ticket email setting.</p>
        <p>Provider-specific settings may override applicable defaults. Confirm the intended provider policy instead of assuming every provider uses the same rates or due dates.</p>
        <p>The platform <strong>Dashboard</strong> summarizes activity across providers. Use billing and collection charts together with the detailed records; a summary figure does not replace reviewing the underlying transaction.</p>
    </x-docs-topic>
</div>
