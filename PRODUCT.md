# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Two audiences weigh the same:

- **Administrador** — owner or manager of a small or mid-sized distributor, carrier, or company with its own fleet (the demo client is a distributor in Registro-SP). Works at a desk: imports the day's orders, fixes addresses on the map, builds routes per sector, follows the routes during the day, audits fuel and expense numbers, and manages vehicles, drivers, gas stations, and integrations.
- **Motorista** — drives the route. Uses the system on a phone, outdoors and on the move: records trips, fuel, and expenses in the Diário de Bordo and checks reports. A driver app for delivery confirmation and location sharing is planned, not built.

## Product Purpose

Kilomo puts the cost of running a fleet and the work of delivering in one system: fuel, expenses, oil changes, and cost per km on one side; order import, geocoding, route optimization, and route follow-up on the other. Success is the administrator trusting the numbers (what was launched in the Diário matches the reports) and planning a day of deliveries in minutes instead of a spreadsheet afternoon.

## Positioning

Fleet cost control and delivery routing live in the same product and the same data: the vehicle and driver that burn fuel are the ones that run the route. Route optimization and the map run without paid APIs; only new addresses go to a geocoding provider, through one platform account, and every located address is reused across companies.

## Operating Context

- Orders arrive as a spreadsheet (.xlsx or .csv, template provided) per delivery date, grouped by sector, with optional time windows and coordinates.
- Customer coordinates come, in order: the customer's confirmed position (driver GPS or dragged pin) → the shared address book of already-located addresses → the platform's geocoding provider (Geoapify, one platform account, monthly quota per company) → the spreadsheet or a pin dragged on the map.
- Routes start at the depot, stops are ordered automatically (nearest neighbor + 2-opt, straight-line distance times a road factor).
- The route monitor refreshes every 15 seconds; the map is Leaflet over OpenStreetMap tiles.
- Address search is included in the plan: companies never create provider accounts or keys; Integrações shows their monthly usage against the quota.
- Multi-tenant: each company's data is isolated; roles are administrador and motorista.

## Capabilities and Constraints

- Built: Diário de Bordo (trips, fuel, expenses), Relatórios (consumption, cost per km, monthly report, export), Trocas de óleo with alerts, Veículos, Motoristas, Postos (price per liter), Entregas (import, paginated table with search/filters, map, route creation), Rotas (monitor), Integrações (address-search usage against the monthly quota), Perfil.
- Not built: driver-side delivery confirmation, failure reason entry, and live location sending. Never present these as available.
- Stack: Laravel 13, Livewire 3, Tailwind CSS 3, Alpine.js, Vite; PHPUnit tests.
- Currency and numbers follow the Brazilian format (R$, comma decimal). UI language is Portuguese (pt-BR).

## Brand Commitments

- Name: **Kilomo**. The current logo (`public/img/logo.png`, green) stays as is; it is the only place its green is required.
- Visual direction pinned by the user (2026-10-29, replacing earlier attempts): warm cream canvas, near-black ink, one electric blue filled pill per area, Space Grotesk headlines and Inter body — see DESIGN.md. Derived from another company's reference DESIGN.md; Kilomo must never use that company's name, copy, customer logos, or proprietary fonts.
- Voice: plain, factual Portuguese. No hype words or AI marketing clichés.

## Evidence on Hand

- Product screens and real feature set in this repository; demo dataset for Registro-SP (`database/seeders/RouteManagementSeeder.php`), synthetic.
- No customers, testimonials, case studies, pricing, or benchmarks exist. Do not invent them.
- Geocoding provider: Geoapify (free plan 3,000 requests/day with "Powered by Geoapify" credit; paid from US$ 59/month), checked 2026-10-29. Storage terms to be confirmed on the official page before production.

## Product Principles

1. Numbers must reconcile: every figure shown traces back to a launched record.
2. Planning a delivery day should take minutes; the map is for checking, not for decoration.
3. Free to operate: no paid dependency the company did not choose.
4. Works as well on a driver's phone outdoors as on an office monitor.
5. Say only what the product does today; label what is coming as coming.

## Accessibility & Inclusion

Outdoor phone use by drivers: strong contrast and large touch targets on mobile. Target WCAG 2.2 AA.
