# PRD — Fleet Management System (MVP)

## 1. Overview
The Fleet Management System (MVP) aims to replace manual spreadsheet-based operations with a web application that enables route tracking, revenue monitoring, and, most importantly, fuel consumption performance indicators per vehicle and driver.

The MVP focuses on operational visibility with minimal user friction, prioritizing fast data entry and automated metric generation.

---

## 2. Product Goals

### Business Goals
- Eliminate reliance on manual spreadsheets
- Improve control over operational costs
- Identify optimization opportunities (fuel efficiency and margins)
- Enable data-driven decision-making

### User Goals
- Quickly log trips and expenses
- Track individual performance (drivers)
- Monitor operational and financial indicators (admin)

---

## 3. Personas

### Administrator
- Monitors global performance indicators
- Analyzes profitability and efficiency
- Needs clear and reliable reports

### Driver / Operator
- Logs trip and expense data
- Requires a simple and fast interface
- Can only access their own records

---

## 4. MVP Scope

### In Scope
- Authentication and authorization
- Vehicle and driver management
- Trip logging (logbook)
- Dashboard with key metrics

### Out of Scope (Future)
- GPS/telemetry integration
- Maintenance management
- Payment integrations
- Multi-tenant support

---

## 5. Functional Requirements

### RF01 — Access Management (Auth)
- User registration and login
- Role-based access control:
  - Admin: full access
  - Driver: restricted to own data

---

### RF02 — Asset Management (CRUDs)

#### Vehicles
- Plate (unique)
- Model
- Capacity (kg)
- Fuel type

#### Drivers
- Name
- Driver’s license (CNH)
- Phone

---

### RF03 — Trip Log (Core Feature)

Optimized interface for fast input:

#### Required Fields
- Date
- Vehicle (plate)
- Driver
- Start KM
- End KM

#### Derived Field
- Distance = End KM - Start KM

#### Revenue
- Freight value / daily rate

#### Fuel
- Liters
- Price per liter
- Station (optional)

#### Expenses
- Toll
- Assistant
- Food

---

### RF04 — Metrics Dashboard

#### Automatically calculated indicators:

- Fuel Efficiency:
  - Distance / Liters

- Cost per KM:
  - Total fuel cost / Distance

- Net Margin:
  - Revenue - (Fuel + Toll + Assistant + Food)

#### Visualizations:
- KPI cards
- Charts by period (day, week, month)
- Filters by vehicle and driver

---

## 6. Non-Functional Requirements

### Performance
- Fast response times (< 300ms for common operations)
- Use caching for aggregated indicators

### Usability
- Simple and intuitive interface
- Mobile-friendly layout for drivers

### Security
- Secure authentication (Laravel Fortify/Breeze)
- Role-based access control
- Backend validation

### Scalability
- Prepared for future evolution (modular/domain-oriented design)

---

## 7. Initial Data Model (Entities)

### User
- id
- name
- email
- password
- role (admin | driver)

### Vehicle
- id
- plate
- model
- capacity
- fuel_type

### Driver
- id
- name
- license_number
- phone

### Trip
- id
- date
- vehicle_id
- driver_id
- km_start
- km_end
- km_total (derived)
- revenue

### Fuel
- id
- trip_id
- liters
- price_per_liter
- station

### Expense
- id
- trip_id
- type (toll | assistant | food)
- amount

---

## 8. Business Rules

- End KM must be greater than Start KM
- Each trip belongs to one driver and one vehicle
- Metrics are derived (not initially persisted)
- Drivers can only access their own data

---

## 9. Success Metrics

- Reduction in spreadsheet usage
- Average logging time < 2 minutes
- Data accuracy improvement
- Admin dashboard engagement frequency

---

## 10. Tech Stack

- Backend: Laravel 11
- Frontend: Livewire 3 + Blade
- UI: TailwindCSS
- Database: MySQL or PostgreSQL
- Charts: Chart.js (via Livewire integration)

---

## 11. Roadmap

### Phase 1 (MVP)
- Auth
- CRUDs
- Trip logging
- Basic dashboard

### Phase 2
- Exportable reports (PDF/Excel)
- Advanced filtering
- Alerts for abnormal fuel consumption

---

## 12. Risks

- Low driver adoption (usability issues)
- Inconsistent manual data input
- Lack of initial data standardization

---

## 13. Assumptions

- Users have internet access
- Drivers will manually input data
- Initial system load is low

---