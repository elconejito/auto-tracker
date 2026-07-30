# AutoTracker v1 Product and Implementation Plan

## Purpose

AutoTracker is a private vehicle-maintenance and fuel-tracking web application. Its primary purpose is to give a vehicle owner a trustworthy record of:

- Fuel purchases and calculated fuel economy
- Vehicle mileage
- Maintenance and repair history
- How many miles have elapsed since common services

Version 1 records and reports facts. It does not predict when a service will be needed, create maintenance schedules, or send service reminders.

This document is the implementation handoff and source of truth for v1.

## Execution Model

The plan is intended to be executed by GPT-5.6 Sol as the primary implementation agent, with Terra and Luna available as subagents for delegated work.

### Primary-agent responsibility

Sol remains accountable for:

- Reading this plan and all project instructions in full
- Making architectural and domain decisions
- Dividing work into bounded implementation tasks
- Preventing overlapping edits among agents
- Reviewing every delegated result and diff
- Integrating changes into a coherent application
- Running proportionate verification after integration
- Maintaining consistent API, domain, testing, and UI conventions
- Reporting completed work and unresolved decisions

Delegation does not transfer ownership of correctness to a subagent.

### Delegation guidance

Use Terra or Luna when a task is concrete, independently verifiable, and can proceed without conflicting with another agent's files. Good candidates include:

- Researching current official package installation or compatibility guidance
- Implementing a bounded backend resource with an agreed contract
- Implementing an isolated Vue view or component family
- Adding focused tests for already-defined behavior
- Reviewing a completed change for authorization, concurrency, or calculation edge cases
- Performing responsive or accessibility QA

Keep architecture, authentication integration, fuel recalculation semantics, concrete-service conventions, and final cross-cutting integration under Sol's direct supervision.

Before delegating, provide the subagent with:

- The relevant sections of this plan
- Exact files or subsystem in scope
- Domain rules and constraints
- Expected deliverables
- Required tests or verification
- Files or concerns that must not be changed

Because agents share a workspace, do not assign concurrent tasks that can edit the same files, migrations, routing definitions, package manifests, shared transformers, shared frontend infrastructure, or generated configuration. If work depends on another task's uncommitted output, run it sequentially.

After delegated work returns, Sol must inspect the actual filesystem diff, reconcile it with concurrent changes, run relevant tests, and correct any inconsistency before treating the task as complete.

## Confirmed Technology

### Backend

- Laravel
- MySQL 8
- Email and password authentication
- JWT access and refresh tokens
- `spatie/laravel-fractal`
- `spatie/laravel-query-builder`

### Frontend

- Vue SPA contained in Laravel's `/resources` directory
- Vue Router
- Pinia
- Tailwind CSS
- Vite
- A centralized HTTP client, selected during implementation

### Initial project setup

The repository currently contains no application code. Use the following order when implementation begins:

1. Install a fresh Laravel application in this repository.
2. Confirm the generated application runs and can connect to MySQL.
3. Install Laravel Boost as a development dependency immediately.
4. Run Boost's current setup workflow and review the instructions and skills it adds to the project.
5. Ask the owner about any interactive Boost choice that cannot be safely inferred.
6. Select an actively maintained JWT package after verifying compatibility with the installed Laravel version and the required refresh-token flow.
7. Install and configure Laravel Fractal and Laravel Query Builder.
8. Install and configure Vue, Vue Router, Pinia, Tailwind CSS, and the HTTP client.
9. Establish backend and frontend testing foundations.
10. Begin domain implementation.

Verify current official package documentation during implementation rather than relying on commands recorded in this plan.

Use Laravel Artisan generators for artifacts supported by Artisan, including:

- Models
- Migrations
- Controllers
- Form requests
- Policies
- Factories
- Seeders
- Tests
- Jobs, events, and listeners if later required

Modify generated files rather than manually approximating Laravel's generated structure.

## V1 Scope

### Included

- Register, sign in, refresh authentication, sign out, and reset a password
- Multiple vehicles per user
- Create, view, edit, archive, and restore vehicles
- Record historical and current fuel purchases
- Store and display calculated miles driven and MPG
- Record maintenance visits containing one or more services
- Use a full concrete model for every known service variant
- Record a generic service for work without a dedicated model
- Display miles since the most recent applicable service
- View fuel history
- View maintenance history
- View a combined vehicle activity timeline
- Filter, sort, include relationships, and paginate API collections
- Responsive, accessible SPA behavior

### Deferred

- Maintenance predictions
- Service schedules, thresholds, and due states
- Reminders and notifications
- Imports from other services
- Receipt, invoice, and photo uploads
- Shared vehicles, household accounts, or multiple drivers
- Mechanics or service-provider accounts
- Administrative interface
- VIN decoding
- Odometer replacement or rollover
- Partial fuel-ups
- Metric units or currencies other than USD
- Advanced analytics

Importing historical data from other services is expected to be an early v2 feature. V1 domain actions should therefore be usable outside HTTP controllers, and creation logic should not depend on interactive SPA behavior.

## Product Defaults

- Units: miles, US gallons, MPG
- Currency: USD
- Dates are stored in UTC and presented in the user's timezone.
- Vehicle year, make, model, trim, and nickname are entered manually.
- Historical fuel and maintenance records are allowed.
- All fuel entries represent full fill-ups.
- Gallons and odometer are required for a fuel entry.
- Fuel cost fields are optional.
- Vehicles are archived and restorable.
- Permanent vehicle deletion is not exposed through the normal v1 interface.
- Each user's account and vehicle data are private.
- Concrete service models begin with a small set of useful fields.
- Persisted service aliases use snake case, such as `oil_change`.

## Domain Principles

1. Raw user-entered facts remain authoritative.
2. Stored calculated fields must be reproducible from raw facts.
3. Historical insertion, editing, and deletion must update affected calculations.
4. A user's resources must never be accessible through another user's account.
5. Missing information is displayed as unavailable, never as a misleading zero.
6. Controllers coordinate HTTP concerns; domain actions perform business operations.
7. Service-specific behavior belongs to concrete service models and their supporting classes.
8. V1 should prefer a small, reliable model over speculative flexibility.

## Core Data Model

Use ULIDs for public domain resource identifiers unless package or framework constraints establish a compelling reason otherwise.

### Users

Use Laravel's conventional user model, extended only as required for:

- Name
- Email
- Password
- Timezone
- Authentication timestamps and token behavior

### Vehicles

Suggested fields:

- `id`
- `user_id`
- `nickname`
- `year`
- `make`
- `model`
- `trim`, nullable
- `vin`, nullable
- `license_plate`, nullable
- `current_odometer`
- `archived_at`, nullable
- Timestamps

Rules:

- A vehicle belongs to exactly one user.
- `nickname`, `year`, `make`, and `model` are required.
- `current_odometer` reflects the latest applicable chronological record.
- Historical insertion is allowed but may not produce an impossible odometer sequence.
- Archiving does not remove fuel or maintenance history.
- Archived vehicles are read-only unless restored, apart from actions explicitly required to restore them.

### Fuel Entries

Suggested fields:

- `id`
- `vehicle_id`
- `fueled_at`
- `odometer`
- `gallons`
- `total_cost`, nullable
- `price_per_gallon`, nullable
- `miles_driven`, nullable
- `calculated_mpg`, nullable
- `station`, nullable
- `fuel_grade`, nullable
- `notes`, nullable
- Timestamps

Use fixed-precision decimals for gallons, prices, costs, and MPG. Do not use floating-point database columns for these values.

Rules:

- Every fuel entry represents a full fill-up.
- `fueled_at`, `odometer`, and `gallons` are required.
- Gallons must be greater than zero.
- The first chronological fuel entry has `null` miles driven and MPG.
- Subsequent calculations are:

```text
miles_driven = current odometer - previous chronological odometer
calculated_mpg = miles_driven / current gallons
```

- When only total cost is supplied, price per gallon may be calculated.
- When only price per gallon is supplied, total cost may be calculated.
- When both are supplied, validate that they agree within an intentional rounding tolerance.
- A chronology is ordered deterministically by `fueled_at`, followed by a stable tie-breaker such as ULID.
- Creating, editing, moving, or deleting a historical entry recalculates that entry and every affected later entry.
- Recalculation happens transactionally through one domain action.
- The vehicle's current odometer is synchronized after relevant mutations.
- Invalid chronology must produce a clear validation or domain error rather than a negative distance.

Suggested domain actions:

- `CreateFuelEntry`
- `UpdateFuelEntry`
- `DeleteFuelEntry`
- `RecalculateVehicleFuelEconomy`
- `SynchronizeVehicleOdometer`

Stored calculations exist to support efficient trends, summaries, sorting, and future charts. They remain derived and rebuildable.

### Maintenance Events

A maintenance event represents a visit or work session and contains one or more service line items.

Suggested fields:

- `id`
- `vehicle_id`
- `performed_at`
- `odometer`
- `provider`, nullable
- `total_cost`, nullable
- `notes`, nullable
- Timestamps

Rules:

- A maintenance event must contain at least one service.
- Historical events are allowed.
- The event's odometer participates in vehicle chronology validation.
- Editing or deleting an event updates vehicle mileage and service recency where applicable.
- Event-level cost represents the overall visit. Individual services may optionally carry a line cost.

### Service Line Items

The common service record connects a maintenance event to a concrete service model.

Suggested fields:

- `id`
- `maintenance_event_id`
- `serviceable_type`
- `serviceable_id`
- `line_cost`, nullable
- Timestamps

Use Laravel's explicit morph map. Persist stable snake-case aliases rather than PHP class names:

```text
oil_change
tire_replacement
tire_rotation
brake_pad_replacement
brake_rotor_replacement
timing_belt_replacement
serpentine_belt_replacement
battery_replacement
air_filter_replacement
cabin_air_filter_replacement
spark_plug_replacement
transmission_service
coolant_service
vehicle_inspection
generic_service
```

### Concrete Service Models

Every known service variant must be a full Laravel model backed by its own table. Do not use an enum or a single service-type lookup table as the domain implementation.

Initial models:

- `OilChange`
- `TireReplacement`
- `TireRotation`
- `BrakePadReplacement`
- `BrakeRotorReplacement`
- `TimingBeltReplacement`
- `SerpentineBeltReplacement`
- `BatteryReplacement`
- `AirFilterReplacement`
- `CabinAirFilterReplacement`
- `SparkPlugReplacement`
- `TransmissionService`
- `CoolantService`
- `VehicleInspection`
- `GenericService`

Use an abstract base model and an interface to centralize shared behavior while allowing each concrete service to own:

- Validation
- Relationships
- Transformer output
- Form fields
- Recency grouping
- Mileage-since behavior
- Future prediction behavior
- Future service-specific rules

The exact interface should be finalized during implementation, but it should answer at least:

- Whether the service supports mileage-since reporting
- Which recency group it belongs to
- How it should be labeled
- How it relates back to its common service line

Keep initial service-specific fields restrained.

Examples:

- Oil change: oil viscosity, quantity, filter replaced, optional filter part number
- Tire replacement: position/scope, manufacturer, model, size, quantity
- Tire rotation: rotation pattern or notes only if useful
- Brake work: front, rear, or all; optional manufacturer and part number
- Battery: optional manufacturer and part number
- Belt replacement: optional manufacturer and part number
- Generic service: required user-entered name and optional description

Avoid modeling exhaustive parts catalogs in v1.

### Service Recency

For a mileage-aware service:

```text
miles_since_service = vehicle current odometer - latest applicable service odometer
```

Examples:

- `Oil changed 1,234 miles ago`
- `Front brake pads replaced 8,420 miles ago`
- `Tires: no replacement recorded`

Applicability matters:

- Tire services must distinguish all, front, rear, or individual positions as required.
- Brake services must distinguish front, rear, or all.
- Whole-vehicle services require no position.

Recency is derived, not persisted in v1. The query/service layer selects the latest applicable concrete service.

## Authentication and Session Behavior

### Required flow

- Email and password registration and login
- Short-lived JWT access credential
- Refresh credential or equivalent package-supported refresh mechanism
- Automatic access-token renewal
- Logout invalidates the renewable session
- Failed renewal clears local auth state and redirects to login
- Password reset supported

Prefer secure, HTTP-only, same-site cookies where the selected package and deployment topology permit. Final CSRF and cookie configuration must be designed alongside the selected JWT package.

### SPA initialization

On application startup:

1. Resolve the current authentication state.
2. Attempt renewal when appropriate.
3. Do not render protected routes until auth state is known.
4. Redirect unauthenticated users to login.
5. Preserve the intended protected route for return after successful login.

### Single-flight refresh

Multiple simultaneous or near-simultaneous API failures must never produce multiple refresh requests.

The shared HTTP client owns one refresh promise:

```js
let refreshPromise = null

async function refreshAccessToken() {
    if (!refreshPromise) {
        refreshPromise = performRefresh()
            .finally(() => {
                refreshPromise = null
            })
    }

    return refreshPromise
}
```

Required behavior:

- The first eligible expired-token response starts refresh.
- Other eligible requests await the same promise.
- After success, queued requests retry once.
- Retried requests are marked to prevent an infinite refresh loop.
- Login, logout, and refresh requests never trigger refresh handling.
- Failed refresh rejects all queued requests safely.
- Auth state is cleared once.
- Login redirection occurs once.
- Network errors are not automatically treated as expired authentication.
- A valid-token authorization failure does not trigger token refresh.

Concurrency tests are mandatory.

## API Standards

### Fractal responses

Every successful API controller response must be created through Fractal and a dedicated transformer. This includes:

- Authentication resources
- Single resources
- Collections
- Nested resources
- Mutation responses
- Empty successful responses where a resource body is appropriate

Centralize serializer and response configuration so controllers cannot drift.

The response design should remain close to JSON:API:

- Top-level `data`
- Stable resource `type`
- String `id`
- `attributes`
- Optional `relationships`
- Pagination metadata and links
- Consistent included resources

The project should not claim full JSON:API compliance unless it is deliberately implemented and tested. Errors and authentication payloads may use a consistent application convention where strict JSON:API behavior would be counterproductive.

Each resource requires a transformer, including each concrete service variant.

Suggested structure:

```text
app/
├── Actions/
├── Contracts/
├── Http/
│   ├── Controllers/Api/
│   └── Requests/
├── Models/
│   └── Services/
├── Policies/
├── Queries/
├── Services/
└── Transformers/
    └── Services/
```

### Query Builder

Use Spatie Laravel Query Builder for collection endpoints. Each endpoint explicitly declares:

- Allowed filters
- Allowed sorts
- Allowed includes
- Default sort
- Default page size
- Maximum page size

Do not pass client parameters indiscriminately into Eloquent.

Likely custom filters:

- Fuel date range
- Maintenance date range
- Odometer range
- MPG range
- Concrete service type
- Service position or axle
- Archived vehicle state

Use eager loading deliberately and test collection endpoints for avoidable N+1 queries.

### Errors

Establish one API error shape for:

- Validation failures
- Authentication failures
- Authorization failures
- Missing resources
- Domain conflicts
- Unexpected errors

Errors need stable machine-readable codes and useful human-readable detail. Internal exception details must not leak in production.

### Authorization

Use policies and scoped route-model binding or equivalent owner scoping.

Requirements:

- A user cannot view, mutate, include, filter into, or infer another user's data.
- Nested IDs must be validated as belonging to the parent vehicle or event.
- Archived-resource restrictions are enforced on the backend.
- Authorization is tested for every resource family.

## Initial API Surface

Exact route names may adapt to the selected JWT package, but the intended resources are:

```text
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/refresh
POST   /api/auth/logout
GET    /api/auth/user
POST   /api/auth/forgot-password
POST   /api/auth/reset-password

GET    /api/vehicles
POST   /api/vehicles
GET    /api/vehicles/{vehicle}
PATCH  /api/vehicles/{vehicle}
POST   /api/vehicles/{vehicle}/archive
POST   /api/vehicles/{vehicle}/restore

GET    /api/vehicles/{vehicle}/dashboard
GET    /api/vehicles/{vehicle}/history

GET    /api/vehicles/{vehicle}/fuel-entries
POST   /api/vehicles/{vehicle}/fuel-entries
GET    /api/fuel-entries/{fuelEntry}
PATCH  /api/fuel-entries/{fuelEntry}
DELETE /api/fuel-entries/{fuelEntry}

GET    /api/vehicles/{vehicle}/maintenance-events
POST   /api/vehicles/{vehicle}/maintenance-events
GET    /api/maintenance-events/{maintenanceEvent}
PATCH  /api/maintenance-events/{maintenanceEvent}
DELETE /api/maintenance-events/{maintenanceEvent}

GET    /api/service-definitions
```

`service-definitions` is API metadata describing supported concrete service forms and fields. It is not a database-backed enum or service-type domain table. Its implementation may use registered classes or configuration backed by the concrete models.

## Frontend Application

### Source layout

Keep the SPA inside `/resources`:

```text
resources/
├── css/
├── js/
│   ├── api/
│   ├── components/
│   ├── composables/
│   ├── layouts/
│   ├── router/
│   ├── stores/
│   ├── views/
│   └── app.js
└── views/
    └── app.blade.php
```

Laravel serves the SPA shell. Vue Router owns client-side application routes.

### State

- Pinia owns authentication state and small amounts of cross-route UI state.
- API records should remain server-driven.
- Do not duplicate all server collections into a global store without a concrete need.
- Forms own their pending state and validation errors.
- The centralized API client owns token refresh and request retry behavior.

### Tailwind CSS

Tailwind CSS is the styling foundation. Do not add a separate full component framework during v1 without an identified requirement.

The initial visual direction should be:

- Clean and utilitarian
- Automotive without decorative imitation of gauges or dashboards
- High readability
- Restrained use of color
- Clear distinctions among normal, archived, warning, error, and unavailable states
- Responsive for phone use at a fuel pump or service shop

Create reusable primitives for:

- Buttons and links
- Form controls
- Validation messages
- Cards
- Tables and responsive list alternatives
- Badges
- Empty states
- Loading states
- Error states
- Dialogs
- Pagination

Accessibility requirements:

- Keyboard-operable controls
- Visible focus states
- Associated form labels
- Sufficient color contrast
- Error messages linked to invalid fields
- Status information not communicated by color alone
- Appropriate busy and live-region behavior

### Loading and empty states

Views with API-backed summaries must not briefly show false zero values or empty history while requests are pending.

For pages with several requests:

- Show an intentional accessible loading state for each independent region or coordinate the requests where a unified transition is better.
- Let the shared authentication client handle simultaneous expiration through single-flight refresh.
- Distinguish empty, loading, authentication, authorization, and network-error states.

## Primary User Experience

### Authentication

- Register
- Sign in
- Forgot password
- Reset password
- Sign out

### Vehicle list

Each vehicle summary should show:

- Nickname
- Year, make, and model
- Current odometer
- Latest available MPG
- Average MPG
- Most recent fuel-up
- Most recent oil change and miles since
- Quick actions to add fuel or maintenance

Archived vehicles are available through an explicit filter or separate view.

### Vehicle dashboard

- Vehicle identity
- Current odometer
- Latest and average MPG
- Recent fuel summary
- Important service-recency cards
- Recent combined activity
- Add fuel-up action
- Record maintenance action

### Fuel history

- Chronological entries
- Odometer
- Gallons
- Miles driven
- Calculated MPG
- Cost information when available
- Station and fuel grade when available
- Notes
- Edit and delete
- Filtering, sorting, and pagination

The first entry should say that MPG is unavailable because a prior fill-up is required.

### Maintenance history

- Chronological maintenance visits
- Services performed
- Odometer
- Provider
- Costs when available
- Notes
- Filters by concrete service type
- Edit and delete

### Combined timeline

Display fuel and maintenance entries in deterministic chronological order. Keep the initial implementation simple and readable.

## Database and Indexing Guidance

- Use `utf8mb4`.
- Use strict SQL mode.
- Add foreign keys with intentional cascade or restrict behavior.
- Use decimals for fuel, MPG, and currency.
- Use nullable columns only when the domain permits missing information.
- Add compound indexes supporting vehicle chronology:
  - Fuel entries: vehicle plus fueled date and stable ID
  - Fuel entries: vehicle plus odometer
  - Maintenance events: vehicle plus performed date and stable ID
  - Maintenance events: vehicle plus odometer
- Index polymorphic service columns appropriately.
- Avoid MySQL `ENUM` for service identities.
- Do not persist raw PHP class names in polymorphic type columns.

## Implementation Phases

### Phase 1: Foundation

- Install Laravel
- Install and configure Laravel Boost immediately
- Review and adopt applicable Boost guidance
- Configure MySQL and testing database
- Establish code formatting and test commands
- Install Vue, Vue Router, Pinia, Tailwind CSS, and HTTP client
- Create SPA shell and route structure
- Establish API response and error conventions

Acceptance:

- Laravel and SPA both run locally.
- Backend and frontend test runners work.
- Tailwind styles compile.
- Boost is installed and its project guidance has been reviewed.

### Phase 2: Authentication

- Select and install compatible JWT package
- Implement register, login, refresh, logout, current-user, and password-reset flows
- Implement secure cookie and CSRF strategy
- Build Pinia auth store and protected routes
- Implement centralized HTTP client
- Implement single-flight refresh and one-time redirect behavior

Acceptance:

- Protected routes survive access-token expiration when refresh succeeds.
- Concurrent failed API calls produce exactly one refresh.
- Failed refresh clears auth and redirects once.
- Cross-user access is rejected.

### Phase 3: Vehicles

- Generate vehicle model, migration, factory, policy, requests, transformer, controller, and tests
- Implement CRUD minus normal permanent deletion
- Implement archive and restore
- Build vehicle list and forms

Acceptance:

- A user can manage several vehicles.
- Users cannot access other accounts' vehicles.
- Archived vehicles remain available and restorable.

### Phase 4: Fuel Tracking

- Generate fuel domain artifacts
- Implement transactional creation and recalculation actions
- Implement historical insertion, update, and deletion
- Add Fractal transformers and Query Builder collection behavior
- Build fuel form and history
- Add vehicle MPG summaries

Acceptance:

- Stored MPG is correct.
- The first entry has no MPG.
- Historical mutations recalculate all affected entries.
- Impossible chronology is rejected clearly.
- Fuel history filters, sorts, and paginates.

### Phase 5: Maintenance Domain

- Generate maintenance-event and common service-line artifacts
- Create service contract and abstract base model
- Generate each concrete service model and migration with restrained fields
- Configure the explicit snake-case morph map
- Implement transactional event creation and editing
- Implement generic service
- Add transformers for events and every concrete service
- Build dynamic maintenance forms

Acceptance:

- One visit can contain several concrete services.
- Each service is persisted through its own model.
- API output identifies concrete service types consistently.
- Generic work can be recorded without changing the service catalog.

### Phase 6: Recency and Dashboard

- Implement service-recency queries
- Respect brake and tire applicability
- Build dashboard summaries
- Build combined timeline
- Complete accessible loading, empty, and error states

Acceptance:

- Miles-since values use current vehicle mileage and the latest applicable service.
- Front and rear brake histories remain distinct.
- Missing history is displayed as unavailable rather than zero.
- No false-empty or false-zero state appears during loading.

### Phase 7: Hardening

- Complete authorization coverage
- Test query allowlists and pagination limits
- Audit N+1 behavior
- Review transactions and race conditions
- Validate responsive layouts
- Perform accessibility review
- Run full backend and frontend suites

Acceptance:

- All suites pass.
- Major user workflows work on phone and desktop layouts.
- Authentication concurrency, fuel recalculation, historical insertion, and authorization have explicit regression tests.

## Testing Strategy

### Backend

Prioritize feature tests around public behavior and focused unit tests around calculations or service contracts.

Mandatory cases:

- Registration, login, refresh, logout, and password reset
- Refresh credential invalidation
- Users cannot access another user's resources
- Nested resources cannot be mixed across vehicles or maintenance events
- Archived vehicle behavior
- First fuel entry has null calculated values
- Consecutive full-fill MPG calculation
- Historical fuel insertion recalculates later entries
- Historical fuel update recalculates later entries
- Fuel deletion reconnects and recalculates later entries
- Equal timestamps use deterministic ordering
- Invalid odometer chronology is rejected
- Cost and price derivation and rounding tolerance
- Maintenance event requires at least one service
- Each concrete service can be persisted and transformed
- Snake-case morph aliases are stored
- Generic services require a name
- Brake and tire applicability affects recency
- Query filters, sorts, includes, and pagination allowlists
- Fractal resource shapes and pagination metadata

### Frontend

Mandatory cases:

- Auth initialization and protected-route loading
- Successful automatic refresh and request retry
- Multiple concurrent 401 responses issue one refresh
- Failed refresh rejects queued requests and redirects once
- Retried requests cannot loop
- Network errors do not trigger auth redirects
- Vehicle forms and archive/restore flows
- Fuel-entry validation and unavailable first-entry MPG
- Maintenance event with several services
- Dynamic concrete service fields
- Loading, empty, error, and populated states
- Accessible form errors and keyboard behavior

## Definition of Done

A feature is complete only when:

- Its domain rules are implemented outside controller-specific code where appropriate.
- Authorization is enforced server-side.
- Requests validate input.
- Successful API responses use Fractal transformers.
- Collections use explicit Query Builder allowlists.
- Relevant factory support exists.
- Backend tests cover success, validation, and authorization.
- Frontend behavior includes loading, empty, error, and success states.
- The UI works at phone and desktop sizes.
- Accessibility basics are satisfied.
- Formatting and relevant test suites pass.

## Decisions to Avoid Reopening Without New Evidence

- Use MySQL 8 rather than PostgreSQL.
- Use Tailwind CSS.
- The Vue SPA lives under `/resources`.
- Use JWT with automatic single-flight refresh.
- Use Fractal transformers for every API controller response.
- Use Spatie Query Builder for collection querying.
- Treat every fuel entry as a full fill-up.
- Store calculated miles driven and MPG.
- Allow historical entries and recalculate affected fuel data.
- Model each known service as a full concrete Laravel model.
- Use snake-case morph aliases.
- Keep v1 service-specific fields minimal.
- Use maintenance events containing multiple services.
- Do not support odometer replacement in v1.
- Do not support data imports until early v2.
- Do not support shared accounts or vehicles in v1.
- Archive and restore vehicles instead of normal permanent deletion.

## Implementation Notes for Future Chats

Before making changes:

1. Read this entire file.
2. Inspect the repository and Git status.
3. If Laravel has already been installed, read all project-level instructions, including those added by Laravel Boost.
4. Determine the current completed phase from code and tests rather than assuming no work has begun.
5. Preserve unrelated user changes in a dirty worktree.
6. Use current official documentation for package installation and compatibility decisions.
7. If using subagents, follow the execution model in this plan and keep delegated scopes bounded and non-overlapping.

When handing off work, update project documentation with completed phases, material deviations, commands used for verification, and any unresolved decisions.
