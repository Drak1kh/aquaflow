# Product

<!-- impeccable:product-schema 1 -->

## Platform

Web application for water providers and their consumers.

## Users

- **Super Admins** oversee the AquaFlow platform, provider accounts, users, permissions, platform reports, escalated disputes, and global settings.
- **Water Provider staff** manage their provider's consumers, connections, meters, readings, rates, bills, payments, service status, and operational reports.
- **Consumers** view their own account, connection, usage, bills, payments, notices, and support requests.

## Product Purpose

AquaFlow is a water management and billing platform that connects water service providers with consumers. It aims to centralize provider operations and give consumers clear access to their water account information.

## Product Goals

- Keep provider, consumer, connection, billing, and payment records in one system.
- Reduce manual billing and payment tracking, and make overdue balances visible.
- Give consumers convenient access to account, connection, usage, bill, and payment information.
- Support controlled disconnection and reconnection workflows.
- Provide provider-level reporting and platform-wide oversight.
- Maintain an audit trail for important account, service, and financial changes.

## Planned Capabilities

### Super Admin

- Manage users, roles, permissions, and provider associations.
- View platform-wide provider, consumer, billing, collection, balance, and activity summaries.
- Monitor billing and payment activity across providers, review reported issues, and inspect audit history.
- Generate platform reports and monitor escalated complaints or disputes.
- Configure global platform settings. Provider-specific rates and service rules remain under provider control unless a platform-wide default is needed.

### Water Provider

- Manage consumer records independently from portal login accounts, so consumers can be recorded before they activate online access.
- Manage service connections, meters, meter readings, provider rates, billing periods, and provider profile settings.
- Calculate consumption and generate individual or batch bills from readings and configured rates.
- Record and verify payments, handle partial payments, issue receipts, and track outstanding balances.
- Manage overdue notices, disconnections, reconnection requests, and restoration history.
- Review billing and consumption reports.

### Consumer

- View account and connection details, current and historical consumption, bills, balances, payment history, and receipts.
- Receive account, payment, billing, service, and support notifications.
- Update permitted contact and security settings and get help from the provider.
- Submit payment or proof of payment for provider verification when that workflow is enabled.

## Core Workflows

1. A provider registers a consumer, assigns a connection and meter, and invites or activates portal access.
2. The provider records a meter reading; AquaFlow calculates consumption, applies the configured rate and eligible adjustments, and generates a bill.
3. A payment is recorded, verified when required, applied to the bill, and reflected in the remaining balance and receipt.
4. An overdue account is evaluated under provider rules. The provider issues a notice and records disconnection or approves a reconnection after eligibility checks.
5. A consumer submits a support concern to the provider. Unresolved or serious disputes may be escalated for Super Admin review.

## Business and Security Rules

- Every consumer and provider-owned record belongs to a provider. Provider staff can only access records for their provider.
- Consumers can access only their own account and connection information.
- Enforce role permissions on the server, not only by hiding interface controls.
- Preserve the meter readings and rates used to create a bill so later changes do not alter billing history.
- Partial payments reduce the balance without marking a bill paid until its payable balance reaches zero.
- Reconnection requires provider verification; recording a payment alone does not automatically restore service.
- Keep financial and service-change audit history. Reverse or void important financial records rather than permanently deleting them where practical.
- Use fixed decimal precision for financial calculations and enforce unique identifiers within their intended scope.

## Scope and Delivery

The planned MVP prioritizes authentication and role-based access, provider and user administration, provider dashboards, consumer and connection management, meters and readings, rate configuration, billing, payments, outstanding balances, a consumer portal, basic notifications and reports, and audit logs.

The development plan sequences delivery from the security and provider foundation, through consumer and connection management, billing, payments and balances, service status workflows, and the consumer portal, then reporting and production readiness.

Online payment gateway integration, SMS, smart meters, automated reading, anomaly detection, advanced analytics, demand forecasting, mobile applications, and other external integrations are future enhancements. Payment recording and proof submission for provider verification are part of the planned operational workflow; the external payment gateway is not required for the MVP.

## Operating Context

- Provider staff work with operational records, billing periods, collections, service requests, and reports.
- Consumers need a simple desktop and mobile experience for their own account and service information.
- Important workflows include meter-to-bill, payment-to-balance, overdue-to-disconnection, reconnection, and complaint escalation.
- Billing, penalties, due dates, disconnection conditions, and reconnection fees may vary by provider.

## Current Application State

The application now implements invitation-based authentication, provider memberships and configurable role permissions, provider and consumer records, connections and meter readings, rates and billing periods, bill generation, verified payments and balances, overdue fees, disconnection and reconnection history, a consumer portal, basic notifications and reports, support escalation, audit logs, and platform administration. The implementation is in the repository; production deployment has not been completed.

External payment gateways, SMS, smart-meter integration, automated meter reading, advanced analytics, mobile apps, and the other integrations in this document remain planned enhancements.

The application uses Laravel, Livewire, and Flux UI. Existing AquaFlow logo and banner assets are in `public/assets/`.

## Brand and UI Direction

- Product name: AquaFlow — Water Management System.
- Use the supplied AquaFlow blue and aqua palette, with light backgrounds, clear status indicators, readable data tables, and responsive layouts.
- Keep provider operations and consumer tasks understandable for their distinct audiences.
- Make usage, billing, balances, and service status easy to find and explain.
- Use the existing logo and banner assets when appropriate.

## Evidence and Content Constraints

- Landing page dashboard figures are illustrative mockup values, not production metrics.
- No verified customer proof, case studies, or testimonials have been provided.
- Describe planned functionality as planned until it is implemented and verified.

## Product Principles

- Keep role access explicit and provider data isolated.
- Make financial and service changes traceable.
- Present billing and consumption clearly to both provider staff and consumers.
- Keep provider-specific policies configurable while preserving historical records.
- Represent product capabilities and metrics honestly.
