# AquaFlow User Flows

These diagrams describe the planned system in [PRODUCT.md](PRODUCT.md) and [plan.md](plan.md). The application currently has a public preview, authentication, account settings, and a placeholder dashboard. The water management features below are planned.

## Consumer

The consumer's journey starts after a provider registers their water account and enables portal access.

```mermaid
flowchart TD
    A[Provider registers consumer and connection] --> B[Consumer receives or activates portal access]
    B --> C[Sign in]
    C --> D[Consumer dashboard]
    D --> E[View account and connection]
    D --> F[View usage and meter readings]
    D --> G[View bills and balance]
    D --> H[View notifications]
    D --> I[Get help or submit a concern]
    D --> J[Update permitted settings]
    G --> K{Payment workflow available?}
    K -->|Provider records payment| L[Provider records payment]
    K -->|Submission enabled| M[Consumer submits payment or proof]
    M --> N[Provider verifies when required]
    L --> O[Balance updated and receipt available]
    N --> O
    O --> P[Consumer views payment status]
    I --> Q[Provider reviews concern]
    Q --> R{Resolved?}
    R -->|Yes| S[Resolution recorded]
    R -->|No, escalation needed| T[Super Admin reviews dispute]
```

## Water provider

The provider manages the operational cycle for its own consumers.

```mermaid
flowchart TD
    A[Sign in] --> B[Provider dashboard]
    B --> C[Register consumer]
    C --> D[Create connection and assign meter]
    D --> E[Invite or activate consumer portal access]
    D --> F[Record meter reading]
    F --> G[Calculate consumption]
    G --> H[Apply configured rate and adjustments]
    H --> I[Generate bill]
    I --> J[Notify consumer]
    J --> K[Record or verify payment]
    K --> L[Update balance and issue receipt]
    L --> M{Balance fully paid?}
    M -->|Yes| N[Bill marked paid]
    M -->|No| O[Track outstanding balance]
    O --> P{Overdue and eligible for action?}
    P -->|No| O
    P -->|Yes| Q[Issue disconnection notice]
    Q --> R[Confirm and record disconnection]
    R --> S[Receive reconnection request]
    S --> T[Verify payment and provider requirements]
    T --> U{Eligible?}
    U -->|No| V[Keep request pending or reject]
    U -->|Yes| W[Approve and restore service]
    W --> X[Mark connection active]
    B --> Y[Review reports and support concerns]
    B --> Z[Manage provider settings and rates]
```

A partial payment reduces the balance but does not mark the bill as paid. Recording a payment alone does not automatically restore disconnected service.

## Super Admin

The Super Admin oversees the platform and handles issues escalated beyond a provider.

```mermaid
flowchart TD
    A[Sign in] --> B[Platform dashboard]
    B --> C[Manage users and provider associations]
    C --> D[Assign roles and permissions]
    B --> E[Review provider and platform activity]
    E --> F[Review billing and payment oversight]
    E --> G[View audit history]
    B --> H[Generate platform reports]
    B --> I[Review escalated complaints]
    I --> J[Investigate or refer to provider]
    J --> K[Record resolution and close dispute]
    B --> L[Manage global platform settings]
```

Provider staff should only access their own provider's records, while consumers should only access their own account information. These access rules are part of the planned system.
