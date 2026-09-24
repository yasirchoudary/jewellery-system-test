# Jewellery Management & POS System --- Master Development Context

## 1. Mission

Transform the existing jewellery POS application into a connected,
production-ready jewellery inventory and business-management system.

**Critical rule: READ THE EXISTING CODEBASE FIRST. DO NOT BUILD
BLINDLY.**

The existing repository is the source of truth for the current
architecture, database, business logic, routes, APIs, frontend,
permissions, calculations, and workflows. This document defines the
target business behavior and integration requirements, but it must not
be used as an excuse to rewrite or duplicate existing functionality.

------------------------------------------------------------------------

## 1a. Repository Scope --- READ THIS BEFORE ANY TOOL CALL

**Target repository for all work in this task:**
`yasirchoudary/jewellery-system-test`

This is a full, up-to-date copy of the original production codebase,
created specifically so that development, auditing, and testing can
happen without any risk to the live application.

Rules for the agent (Antigravity IDE or any other coding agent
executing this context document):

1.  **All codebase audit, code reading, and logic analysis described in
    this document must be performed against
    `yasirchoudary/jewellery-system-test`.** Do not clone, read, or
    reference the original production repository for this task.
2.  **All commits, branches, and pull requests produced from this work
    must be pushed to `yasirchoudary/jewellery-system-test`.**
3.  **Do not open, modify, push to, or otherwise touch the original
    repository.** That repository is deployed to the live website; any
    change there --- even an accidental one --- can take the live site
    down. It is off-limits for this task.
4.  If the agent's tooling/config (e.g. an `antigravity.json`,
    workspace config, remote URL, or IDE project setting) currently
    points at the original repository, update it to point at
    `yasirchoudary/jewellery-system-test` before doing anything else.
    Verify the working directory's git remote (`git remote -v`) matches
    `yasirchoudary/jewellery-system-test` before making any commit.
5.  Everywhere else in this document that says "the existing
    repository," "the repository," or "the codebase," read that as
    `yasirchoudary/jewellery-system-test` for the purposes of this
    task --- not the original production repo.
6.  Once changes are audited, implemented, and verified in
    `yasirchoudary/jewellery-system-test`, they may later be
    cherry-picked or merged into the original repository as a separate,
    explicitly authorized step. That step is out of scope here unless
    the user explicitly asks for it.

------------------------------------------------------------------------

## 2. Mandatory First Phase: Codebase Audit

Before changing any file, inspect the complete repository.

### Backend audit

Inspect:

-   framework and version
-   database and configuration
-   models/entities
-   migrations
-   seeders
-   controllers
-   services/domain classes
-   repositories, if present
-   validators/form requests
-   policies and middleware
-   API routes
-   web routes
-   events/listeners/jobs
-   authentication
-   authorization/roles/permissions
-   existing stock calculations
-   existing inventory tables
-   audit logging
-   invoice/PDF generation
-   tests

### Frontend audit

Inspect:

-   framework/version
-   pages
-   components
-   layouts/navigation
-   forms
-   tables
-   modals
-   API clients
-   state management
-   validation
-   error handling
-   existing dashboard
-   purchase UI
-   sales UI
-   stock UI
-   karigar UI
-   laboratory UI
-   customer/supplier UI
-   permissions

### Business-module audit

Search the repository for existing implementations of:

-   Item Types
-   Supplier
-   Customer
-   Broker
-   Stock Ledger
-   Purchase
-   Manage Purchase
-   Sales Bill
-   Manage Sales Bill
-   Invoice
-   Direct Invoice
-   Invoice from Sales Bill
-   Karigar
-   Laboratory
-   Expenses
-   Credit
-   Investors
-   Bank
-   Audit Logs
-   Dashboard
-   Metal
-   Roles/Permissions
-   inventory
-   stock
-   tags/barcodes
-   manufacturing/job cards
-   returns
-   exchanges

### Required audit output

Before implementation, produce a project map containing:

1.  Current architecture
2.  Current database schema
3.  Current inventory logic
4.  Current purchase flow
5.  Current sales flow
6.  Current karigar flow
7.  Current invoice flow
8.  Current customer/supplier flow
9.  Current authentication/permissions
10. Current calculations
11. Existing reusable modules
12. Missing functionality
13. Duplicate/conflicting functionality
14. Files/tables/routes that will need modification
15. Files/tables/routes that need to be created
16. Risks and compatibility concerns

Do not guess. Search the code.

------------------------------------------------------------------------

# 3. Existing-System Rule

This is an existing application.

**Do not rewrite the entire application.**

Before creating a new model/table/page/service:

1.  Search for an existing equivalent.
2.  Understand how it is used.
3.  Identify its relationships.
4.  Identify its API consumers.
5.  Identify its permissions.
6.  Identify reports/invoices depending on it.
7.  Reuse or extend it when appropriate.

Do not create parallel systems such as `StockLedgerV2`,
`NewKarigarSystem`, or duplicate purchase/sales logic unless the
existing architecture truly requires it.

The target is **integration**, not a collection of disconnected CRUD
screens.

------------------------------------------------------------------------

# 4. The 23 Required Inventory Areas

The system must properly support these 23 areas:

1.  Gross Weight
2.  Stone Weight
3.  Net Metal Weight
4.  Purity / Karat
5.  Fine Gold Weight
6.  Opening Stock
7.  Stock In / Stock Out
8.  Purchase → Inventory
9.  Sale → Inventory
10. Karigar Issue
11. Karigar Return
12. Karigar Outstanding
13. Wastage
14. Karigar Mazdoori
15. Stock Adjustment
16. Inventory Ledger
17. Gold Exchange / Old Gold
18. Customer Returns
19. Supplier Returns
20. Manufacturing / Job Card
21. Stock Valuation
22. Tag / Barcode / Item Code
23. Stone Inventory

These are **connected capabilities**, not 23 independent pages.

------------------------------------------------------------------------

# 5. Core Jewellery Weight Model

The system must clearly distinguish:

-   Gross weight
-   Stone weight
-   Net metal weight
-   Purity/karat
-   Fine metal weight
-   Pieces
-   Monetary value

## Gross weight

Total physical weight of the complete item.

Example:

`Gross = 10.000 g`

## Stone weight

Weight attributed to stones/non-metal components.

Example:

`Stone = 1.000 g`

## Net metal weight

`Net = Gross - Stone`

Example:

`10.000 - 1.000 = 9.000 g`

Validation:

`Stone >= 0` `Stone <= Gross` `Net >= 0`

## Purity

Support configurable purities such as:

-   24K
-   22K
-   21K
-   20K
-   18K
-   other business-defined values

Do not hard-code only 22K.

## Fine gold weight

`Fine = Net × Karat / 24`

Example:

`9 × 22 / 24 = 8.250 g`

Fine weight is important for stock, exchange, karigar reconciliation,
manufacturing, and valuation.

------------------------------------------------------------------------

# 6. Precision

Never use binary floating-point arithmetic as the authoritative
mechanism for jewellery weights or money.

Use database decimal fields and decimal-safe calculations.

Recommended weight precision is at least:

`DECIMAL(14,3)`

Use greater precision if the actual business/codebase requires it.

Do not repeatedly round intermediate calculations. Round only at defined
business boundaries.

------------------------------------------------------------------------

# 7. Central Calculation Engine

All authoritative calculations must be centralized.

Do not implement separate versions of:

-   net calculation
-   fine calculation
-   purity conversion
-   stock balance
-   karigar reconciliation
-   wastage

inside individual controllers/components.

The frontend may show live calculations for UX, but backend/server-side
validation is authoritative.

------------------------------------------------------------------------

# 8. Central Inventory Movement Ledger

The system needs one coherent inventory movement concept.

Every physical stock-changing transaction should create a traceable
movement.

Movement examples:

-   Opening stock
-   Purchase
-   Purchase return
-   Sale
-   Customer return
-   Supplier return
-   Karigar issue
-   Karigar return
-   Manufacturing consumption
-   Manufacturing output
-   Old-gold receipt
-   Old-gold return if applicable
-   Stock adjustment
-   Stone receipt
-   Stone consumption
-   Transfer, if supported

Conceptually:

`Current Stock = Opening + IN - OUT ± Adjustments`

The ledger must make it possible to answer:

**Why is the current stock this amount?**

Every balance must be traceable to movements and source documents.

------------------------------------------------------------------------

# 9. Inventory Movement Data

Adapt this to the existing schema rather than blindly creating duplicate
tables.

A movement should be able to identify:

-   movement ID
-   date/time
-   movement type
-   source/reference type
-   source/reference ID
-   item/stock identity
-   tag/item code where applicable
-   metal
-   purity
-   gross weight
-   stone weight
-   net weight
-   fine weight
-   pieces
-   IN/OUT direction
-   source location
-   destination/responsibility
-   user
-   notes
-   audit metadata

Use database transactions so the business document and its stock
movement succeed or fail atomically.

------------------------------------------------------------------------

# 10. Module 1 --- Gross Weight

Capture total measured jewellery weight.

Used by:

-   purchases
-   sales
-   manufacturing
-   karigar issue
-   karigar return
-   returns
-   stock
-   exchange

Validate non-negative decimal values.

------------------------------------------------------------------------

# 11. Module 2 --- Stone Weight

Separate stone/non-metal weight from gross weight.

Formula:

`Net = Gross - Stone`

Validation:

`Stone >= 0` `Stone <= Gross`

If detailed stone inventory exists, connect stone records to the
jewellery item instead of treating all stone data as unexplained text.

------------------------------------------------------------------------

# 12. Module 3 --- Net Metal Weight

Normally derived:

`Net = Gross - Stone`

Do not allow arbitrary manual net values unless the business has a
legitimate exception.

If manual override is supported:

-   require permission
-   require reason
-   record audit information

------------------------------------------------------------------------

# 13. Module 4 --- Purity / Karat

Purity must be stored on applicable inventory/transaction records.

Do not silently convert or overwrite purity.

Where multiple metals/purities exist, keep their stock balances
distinct.

------------------------------------------------------------------------

# 14. Module 5 --- Fine Gold Weight

Formula:

`Fine = Net × Purity / 24`

Example:

Gross 10g Stone 1g Net 9g Purity 22K Fine 8.25g

Fine weight should be available to:

-   inventory
-   karigar
-   old-gold exchange
-   manufacturing
-   reports
-   valuation where appropriate

------------------------------------------------------------------------

# 15. Module 6 --- Opening Stock

Opening stock must create actual inventory movements.

Fields may include:

-   opening date
-   item
-   item type
-   tag/item code
-   metal
-   purity
-   gross
-   stone
-   net
-   fine
-   pieces
-   cost/value
-   location
-   notes

Do not simply write a number into a dashboard.

Opening stock should flow:

`Opening Stock → Inventory Ledger → Current Stock`

Editing posted opening stock must be permission-controlled and
auditable.

If existing records lack required weight data, do not invent values.

------------------------------------------------------------------------

# 16. Module 7 --- Stock IN / OUT

### Stock IN

Examples:

-   opening stock
-   purchase
-   karigar finished-goods return
-   manufacturing output
-   customer return
-   customer old gold
-   supplier adjustment
-   approved transfer

### Stock OUT

Examples:

-   sale
-   karigar issue
-   manufacturing consumption
-   supplier return
-   stock adjustment
-   transfer
-   other approved business movements

Every movement must have a source/reference.

------------------------------------------------------------------------

# 17. Module 8 --- Purchase → Inventory

A purchase is not complete when only the purchase table is saved.

When posted/finalized:

1.  Validate supplier.
2.  Validate item.
3.  Validate metal/purity.
4.  Validate weights.
5.  Calculate net.
6.  Calculate fine.
7.  Calculate amount.
8.  Save/post purchase.
9.  Create inventory IN.
10. Assign/create tag if applicable.
11. Update inventory/ledger.
12. Update relevant balances.
13. Audit.

Example:

Gross 20g Stone 2g Net 18g 22K Fine 16.5g

The correct stock movement must reflect these values.

### Editing posted purchase

Do not silently overwrite historical stock movement.

Use a controlled reversal/re-post or equivalent safe strategy.

------------------------------------------------------------------------

# 18. Module 9 --- Sale → Inventory

A finalized sale must reduce inventory.

Flow:

`Available Inventory → Sales Bill → Invoice → Stock OUT`

Before posting:

-   verify item exists
-   verify tag if applicable
-   verify availability
-   verify pieces/weight
-   validate pricing
-   validate customer rules

After posting:

-   create stock OUT
-   mark item/tag sold where appropriate
-   update ledger
-   update customer account/credit where applicable
-   generate invoice
-   audit

Do not sell the same uniquely tagged item twice.

Do not permit negative stock unless explicitly supported by a business
rule and protected by permissions.

------------------------------------------------------------------------

# 19. Module 10 --- Karigar Issue

Karigar receiving gold is generally a **responsibility transfer**, not a
sale.

Flow:

`Shop Inventory OUT → Karigar Responsibility`

Record:

-   karigar
-   job card
-   item to make
-   metal
-   purity
-   gross
-   stone if applicable
-   net
-   fine
-   pieces
-   issue date
-   expected return date
-   agreed mazdoori
-   notes

The shop stock decreases, but the material remains traceable as being
with that karigar.

------------------------------------------------------------------------

# 20. Module 11 --- Karigar Return

A return can contain:

-   finished jewellery
-   gross weight
-   stone weight
-   net weight
-   fine weight
-   pieces
-   remaining material
-   wastage
-   mazdoori
-   job status

Example:

Issued = 50g Returned finished jewellery = 47g Remaining = 2g Wastage =
1g

Reconciliation:

`47 + 2 + 1 = 50g`

Return must create the correct inventory IN movements.

Remaining gold must be returned to the appropriate shop stock.

Wastage must be explicitly recorded.

------------------------------------------------------------------------

# 21. Module 12 --- Karigar Outstanding

The system must maintain the current material responsibility of every
karigar.

Example:

Issued = 50g Returned = 47g Wastage = 1g Remaining = 2g

Outstanding = 2g.

Reports should support:

-   total issued
-   total returned
-   outstanding
-   wastage
-   job-wise balance
-   date-wise movements
-   fine-weight balance where relevant

If multiple purities/metals are used, do not collapse them into one
meaningless gross-weight number. Reconcile by the appropriate
metal/purity/fine-weight dimension.

------------------------------------------------------------------------

# 22. Module 13 --- Wastage

Wastage must be explicit.

Support where appropriate:

-   allowed wastage
-   actual wastage
-   wastage percentage
-   wastage weight
-   excess wastage
-   reason
-   karigar
-   job
-   approval

Never hide unexplained stock differences as wastage.

If actual wastage exceeds allowed wastage, flag it for authorized
review.

Do not automatically label a variance as theft, fraud, or error.

------------------------------------------------------------------------

# 23. Module 14 --- Karigar Mazdoori

Mazdoori is labour/workmanship cost.

Depending on the existing/client rules, support:

-   fixed amount
-   per gram
-   per piece
-   percentage if required

Keep mazdoori conceptually separate from:

-   gold weight
-   fine gold
-   stone value

unless a documented business rule says otherwise.

------------------------------------------------------------------------

# 24. Module 15 --- Stock Adjustment

Authorized users need a controlled way to correct physical stock.

Examples:

-   physical count difference
-   weight correction
-   damaged item
-   missing item
-   measurement correction
-   migration correction

Record:

-   adjustment type
-   item
-   before
-   delta/after
-   weight/pieces
-   reason
-   user
-   timestamp
-   approval if required

Do not silently edit historical ledger values.

------------------------------------------------------------------------

# 25. Module 16 --- Inventory Ledger

The inventory ledger is a primary audit mechanism.

It should support:

-   date
-   reference
-   movement type
-   item
-   tag
-   metal
-   purity
-   gross
-   stone
-   net
-   fine
-   pieces
-   IN
-   OUT
-   balance
-   user

Filters:

-   date range
-   item
-   item type
-   metal
-   purity
-   movement type
-   tag
-   supplier
-   customer
-   karigar
-   reference

The ledger must reconcile with inventory summaries.

------------------------------------------------------------------------

# 26. Module 17 --- Gold Exchange / Old Gold

Old gold is a physical inventory receipt, not merely a discount.

Example:

Old gold: - Gross = 12g - Stone = 1g - Net = 11g - Purity = 21K - Fine =
9.625g

New jewellery: - Net = 15g - Purity = 22K - Fine = 13.750g

The system must separately track:

-   old gold received
-   old gold fine weight
-   old gold value/rate
-   new jewellery
-   new jewellery fine weight
-   making charges
-   wastage
-   stone value
-   final payable/refundable amount

Inventory effects:

`Old Gold → Stock IN`

`New Jewellery → Stock OUT`

Both should be traceable to the same exchange/business transaction where
appropriate.

Do not hard-code the monetary exchange rule without first inspecting
existing logic/client requirements.

------------------------------------------------------------------------

# 27. Module 18 --- Customer Returns

Customer return should reference the original sale/invoice where
possible.

Flow:

`Sale → Customer Return → Inventory IN`

Validate:

-   original sale
-   customer
-   item/tag
-   return eligibility
-   returned quantity/weight

Then:

-   reverse/offset the original stock OUT
-   restore item status
-   update customer financial record
-   create return documentation
-   audit

Prevent double-return of the same sold item.

------------------------------------------------------------------------

# 28. Module 19 --- Supplier Returns

Supplier return reverses stock received from a supplier.

Flow:

`Purchase → Supplier Return → Inventory OUT`

Validate against original purchase.

Do not allow more quantity/weight to be returned than is eligible.

Update:

-   stock
-   supplier balance
-   purchase return history
-   ledger
-   audit

------------------------------------------------------------------------

# 29. Module 20 --- Manufacturing / Job Card

Manufacturing connects raw material to finished jewellery.

Conceptual flow:

`Raw Material → Job Card → Karigar → Finished Jewellery`

Job card can contain:

-   job number
-   item to manufacture
-   target pieces
-   metal
-   purity
-   expected material
-   stones
-   karigar
-   issue weight
-   expected output
-   allowed wastage
-   mazdoori
-   due date
-   status

Possible statuses:

-   Draft
-   Issued
-   In Progress
-   Partially Returned
-   Completed
-   Reconciled
-   Cancelled

Use the project's existing status conventions if they already exist.

Manufacturing must create real inventory movements.

------------------------------------------------------------------------

# 30. Module 21 --- Stock Valuation

Physical quantity and monetary valuation are different.

Before implementing valuation, inspect the existing application/business
rules.

Possible methods include:

-   purchase cost
-   average cost
-   specific tagged-item cost
-   fine-gold rate
-   configured market valuation

Do not invent an accounting valuation method.

Reports should distinguish:

-   gross stock
-   net metal stock
-   fine gold stock
-   pieces
-   stone stock
-   cost/value
-   estimated/current value where supported

------------------------------------------------------------------------

# 31. Module 22 --- Tag / Barcode / Item Code

Uniquely tracked jewellery should have stable identity.

Possible fields:

-   item ID
-   item code
-   tag number
-   barcode
-   SKU
-   item type
-   metal
-   purity
-   gross
-   stone
-   net
-   fine
-   pieces
-   cost
-   sale price
-   status

Tag identity must connect to:

-   purchase
-   inventory
-   sale
-   return
-   transfer
-   adjustment
-   invoice
-   reports

Enforce uniqueness where required.

------------------------------------------------------------------------

# 32. Module 23 --- Stone Inventory

Where the business needs detailed stone inventory, support:

-   stone ID
-   type
-   name
-   shape
-   size
-   quality
-   color
-   quantity
-   weight
-   rate
-   value
-   supplier
-   purchase reference
-   consumption reference
-   return
-   adjustment
-   current balance

Stone movement types:

-   receipt/purchase
-   consumption
-   sale if applicable
-   return
-   adjustment
-   transfer

Stone weight consumed into jewellery must connect to the jewellery
item's stone information.

Do not assume every client requires the same level of stone detail.

------------------------------------------------------------------------

# 33. End-to-End Flow A --- Purchase

``` text
Supplier
  ↓
Purchase
  ↓
Gross / Stone / Net / Purity / Fine
  ↓
Post Purchase
  ↓
Inventory IN
  ↓
Tag / Item Identity
  ↓
Inventory Ledger
  ↓
Stock Summary / Reports
```

------------------------------------------------------------------------

# 34. End-to-End Flow B --- Sale

``` text
Available Item
  ↓
Select Tag / Item
  ↓
Read Gross / Stone / Net / Fine / Purity
  ↓
Sales Bill
  ↓
Pricing / Making / Other Charges
  ↓
Post Sale
  ↓
Inventory OUT
  ↓
Tag = Sold
  ↓
Invoice
  ↓
Customer Account
  ↓
Inventory Ledger
```

------------------------------------------------------------------------

# 35. End-to-End Flow C --- Karigar

``` text
Shop Inventory
  ↓
Job Card
  ↓
Karigar Issue
  ↓
Shop Stock OUT
  ↓
Karigar Outstanding
  ↓
Work in Progress
  ↓
Karigar Return
  ↓
Finished Goods + Remaining Gold + Wastage
  ↓
Reconciliation
  ↓
Finished Inventory IN
  ↓
Karigar Outstanding Updated
  ↓
Job Completed
```

For every job:

`Issued = Returned + Remaining + Recognized Wastage`

subject to configured metal/purity/fine-weight rules.

------------------------------------------------------------------------

# 36. End-to-End Flow D --- Old Gold Exchange

``` text
Customer
  ↓
Old Gold Received
  ↓
Gross / Stone / Net / Purity / Fine
  ↓
Old Gold Inventory IN
  ↓
Old Gold Value/Credit
  ↓
Select New Jewellery
  ↓
New Jewellery Valuation
  ↓
Making / Wastage / Stone / Other Charges
  ↓
New Jewellery Inventory OUT
  ↓
Difference Payable/Refundable
  ↓
Invoice / Exchange Document
  ↓
Audit
```

------------------------------------------------------------------------

# 37. End-to-End Flow E --- Customer Return

``` text
Original Sale
  ↓
Return Request
  ↓
Original Invoice / Tag
  ↓
Validate Eligibility
  ↓
Customer Return
  ↓
Inventory IN
  ↓
Item Status Restored
  ↓
Customer Financial Adjustment
  ↓
Audit
```

------------------------------------------------------------------------

# 38. End-to-End Flow F --- Supplier Return

``` text
Original Purchase
  ↓
Supplier Return
  ↓
Validate Returnable Quantity
  ↓
Inventory OUT
  ↓
Supplier Financial Adjustment
  ↓
Ledger
  ↓
Audit
```

------------------------------------------------------------------------

# 39. End-to-End Flow G --- Manufacturing

``` text
Raw Material
  ↓
Job Card
  ↓
Material Allocation
  ↓
Karigar Issue
  ↓
Production
  ↓
Karigar Return
  ↓
Wastage / Remaining Material / Mazdoori
  ↓
Finished Jewellery
  ↓
Inventory IN
  ↓
Tagging
  ↓
Available for Sale
```

------------------------------------------------------------------------

# 40. End-to-End Flow H --- Stock Adjustment

``` text
Current Stock
  ↓
Physical Verification
  ↓
Difference
  ↓
Reason
  ↓
Permission / Approval
  ↓
Adjustment Movement
  ↓
New Stock Balance
  ↓
Audit
```

------------------------------------------------------------------------

# 41. Core Invariants

These must always hold for applicable records.

### Weight

`Gross = Net + Stone`

### Net

`Net = Gross - Stone`

### Fine

`Fine = Net × Purity / 24`

### Stock

`Current = Opening + IN - OUT ± Adjustments`

### Karigar

`Issued = Returned + Remaining + Recognized Wastage`

For multiple metals/purities, reconcile using the correct
metal/purity/fine-weight dimensions.

------------------------------------------------------------------------

# 42. Multiple Metals and Purities

Do not assume all inventory is gold.

The architecture should be capable of supporting configured metals such
as:

-   Gold
-   Silver
-   Platinum
-   other business-defined metals

Do not combine 22K and 24K balances as if they are physically identical.

When conversion/comparison is needed, use fine-metal weight or an
explicit configured conversion rule.

------------------------------------------------------------------------

# 43. Transaction Lifecycle

Use a clear distinction between draft and finalized business documents.

### Draft

Editable; should not create permanent stock effects unless the existing
architecture explicitly requires reservations.

### Posted/Finalized

Creates the permanent inventory/business effects.

### Cancelled/Reversed

Original transaction remains traceable and its effects are reversed.

Never delete finalized stock transactions simply to correct them.

------------------------------------------------------------------------

# 44. Atomic Transactions

Any operation that modifies a business document AND inventory must be
atomic.

For example, posting a purchase may involve:

-   purchase record
-   purchase lines
-   inventory movement
-   tag
-   stock balance/cache
-   supplier balance
-   audit log

Use a database transaction.

If one required step fails:

`ROLLBACK`

No partial stock update.

------------------------------------------------------------------------

# 45. Editing Posted Transactions

Preferred strategy:

1.  Reverse old inventory effects.
2.  Preserve original history.
3.  Apply corrected data.
4.  Create new correct movement.
5.  Record audit.

Never silently overwrite historical movement data.

------------------------------------------------------------------------

# 46. Permissions

Use the existing role/permission architecture if present.

Sensitive permissions should cover, where applicable:

-   view inventory
-   create/post purchase
-   create/post sale
-   issue to karigar
-   return from karigar
-   adjust stock
-   edit opening stock
-   approve wastage
-   override calculations
-   process old-gold exchange
-   customer return
-   supplier return
-   view valuation
-   view audit logs
-   reverse transactions

Do not create a second permission system if the existing one can be
extended.

------------------------------------------------------------------------

# 47. Audit Trail

Important events must record:

-   user
-   timestamp
-   action
-   entity
-   entity ID
-   reference
-   before data where appropriate
-   after data where appropriate
-   reason

High-risk actions:

-   opening stock changes
-   stock adjustments
-   reversals
-   wastage approval
-   manual fine/net override
-   tag reassignment
-   destructive operations

Prefer reversal/soft deletion over destroying historical transaction
records.

------------------------------------------------------------------------

# 48. Reports

The finished system should support, according to the existing
architecture:

## Stock Summary

-   metal
-   purity
-   gross
-   stone
-   net
-   fine
-   pieces

## Inventory Ledger

-   all stock movements

## Karigar Report

-   issued
-   returned
-   outstanding
-   wastage
-   mazdoori

## Purchase Report

-   supplier
-   gross
-   net
-   fine
-   amount

## Sales Report

-   customer
-   item
-   gross
-   net
-   fine
-   amount

## Old Gold Report

-   received
-   fine
-   value

## Returns

-   customer returns
-   supplier returns

## Manufacturing

-   open jobs
-   completed jobs
-   material outstanding

## Stone Inventory

-   received
-   consumed
-   balance

## Valuation

-   quantity
-   cost/value using configured method

------------------------------------------------------------------------

# 49. Dashboard

Dashboard values must use the same authoritative services/queries as the
underlying inventory system.

Possible cards:

-   total fine gold
-   total net metal
-   total gross jewellery
-   total pieces
-   karigar outstanding
-   open manufacturing jobs
-   today's purchases
-   today's sales
-   customer returns
-   supplier returns
-   low stock
-   pending reconciliation

Do not create separate dashboard-only stock formulas.

------------------------------------------------------------------------

# 50. Performance

For large datasets:

-   paginate
-   filter server-side
-   index search/filter fields
-   avoid N+1 queries
-   eager-load where appropriate
-   aggregate in the database where appropriate
-   do not load the entire inventory table into the browser

Follow existing project conventions.

------------------------------------------------------------------------

# 51. Data Migration Safety

Before changing existing tables:

1.  Inspect current schema.
2.  Create reversible migrations.
3.  Preserve existing data.
4.  Backfill only when values can be derived safely.
5.  Never invent missing weight/purity values.
6.  Test migrations against representative data.

If old records cannot support a new field, mark them for review instead
of fabricating data.

------------------------------------------------------------------------

# 52. Validation

### Weight

-   gross \>= 0
-   stone \>= 0
-   stone \<= gross
-   net \>= 0

### Purity

-   valid configured purity

### Sale

-   item exists
-   item available
-   quantity/weight available

### Return

-   linked original transaction
-   return does not exceed eligible amount

### Karigar

-   return does not exceed issued material unless authorized
-   reconciliation must be explicit

### Tag

-   unique where configured

### Inventory

-   no unexplained negative stock unless explicitly configured

------------------------------------------------------------------------

# 53. Testing Requirements

Do not consider a module complete merely because its page opens.

## Unit tests

Test:

`10 - 1 = 9`

`9 × 22 / 24 = 8.25`

`10 - 0 = 10`

`stone > gross → reject`

## Inventory test

Opening = 100g Purchase = 20g Sale = 30g

Expected = 90g.

## Karigar test

Issue = 50g Return = 47g Remaining = 2g Wastage = 1g

Expected:

`47 + 2 + 1 = 50`

## Partial return

Issue = 100g Return 1 = 60g Outstanding = 40g Return 2 = 38g Remaining =
2g

Job must remain open between returns.

## Old gold

Gross = 12g Stone = 1g Net = 11g 21K

Fine:

`11 × 21 / 24 = 9.625g`

Verify old gold stock IN.

Verify new jewellery stock OUT.

## Purchase → Sale

-   purchase
-   verify stock increase
-   sell
-   verify stock decrease
-   verify tag status
-   verify ledger
-   verify invoice
-   verify no duplicate movement

## Customer return

-   sale
-   return
-   verify stock restored
-   verify item status
-   verify customer adjustment
-   verify ledger

## Supplier return

-   purchase
-   supplier return
-   verify stock reduction
-   verify supplier adjustment
-   verify ledger

## Adjustment

Stock = 100g Physical = 99.5g Adjustment = -0.5g

Expected = 99.5g.

## Negative stock

Attempt to sell unavailable stock.

Expected:

-   rejection
-   no partial write
-   no invalid ledger movement

## Atomicity

Force an error during a multi-step posting operation.

Expected:

-   full rollback
-   no orphaned posted document
-   no orphaned stock movement

------------------------------------------------------------------------

# 54. UI Testing

For every relevant module:

1.  Open screen.
2.  Create record.
3.  Enter realistic data.
4.  Verify live calculations.
5.  Save draft.
6.  Edit draft.
7.  Post/finalize.
8.  Check inventory.
9.  Check ledger.
10. Check related customer/supplier/karigar data.
11. Check report.
12. Refresh browser.
13. Verify persistence.

Test both valid and invalid inputs.

------------------------------------------------------------------------

# 55. Build and Deployment Verification

Use the actual project commands discovered from:

-   package files
-   composer configuration
-   scripts
-   Docker configuration
-   framework configuration
-   CI configuration

Run appropriate:

-   migrations
-   tests
-   lint
-   type checks if applicable
-   frontend build
-   backend checks
-   production build
-   startup/health checks

Do not invent commands.

------------------------------------------------------------------------

# 56. Live Demo Rules

If a temporary demo account is provided:

-   use it only to understand/test the application
-   do not use real customer data
-   do not change production data unless explicitly authorized
-   prefer a test/demo environment
-   never request or store permanent passwords/secrets
-   never commit credentials to the repository

The live application is useful for understanding the actual UI/business
workflow, but the repository remains the implementation source of truth
--- and for this task, "the repository" means
`yasirchoudary/jewellery-system-test`, never the production repository
backing the live site.

------------------------------------------------------------------------

# 57. No Blind UI Construction

Before adding a new page:

-   find the existing layout
-   find existing table components
-   find existing form components
-   find existing modal patterns
-   find API conventions
-   find validation conventions
-   find permission checks
-   find notification patterns

Extend the existing design system instead of creating unrelated UI.

------------------------------------------------------------------------

# 58. No Blind Database Construction

Before adding a table:

-   search existing migrations
-   search model/entity names
-   search foreign keys
-   search controllers/services
-   search frontend API calls
-   search seeders
-   search reports
-   search routes

Determine whether the concept already exists under a different name.

------------------------------------------------------------------------

# 59. Required Implementation Map

After the initial audit, create a table like this using REAL codebase
findings:

  --------------------------------------------------------------------------------------------
  Requirement     Existing         Current    Required   Tables/models   APIs       Frontend
                  implementation   behavior   change                                
  --------------- ---------------- ---------- ---------- --------------- ---------- ----------
  Gross Weight                                                                      

  Stone Weight                                                                      

  Net Weight                                                                        

  Purity                                                                            

  Fine Weight                                                                       

  Opening Stock                                                                     

  Stock IN/OUT                                                                      

  Purchase                                                                          

  Sale                                                                              

  Karigar Issue                                                                     

  Karigar Return                                                                    

  Karigar                                                                           
  Outstanding                                                                       

  Wastage                                                                           

  Mazdoori                                                                          

  Adjustment                                                                        

  Ledger                                                                            

  Gold Exchange                                                                     

  Customer Return                                                                   

  Supplier Return                                                                   

  Manufacturing                                                                     

  Valuation                                                                         

  Tag/Barcode                                                                       

  Stone Inventory                                                                   
  --------------------------------------------------------------------------------------------

Do not populate this from assumptions.

------------------------------------------------------------------------

# 60. Recommended Implementation Order

The exact order may change after the audit, but dependencies generally
follow:

1.  Audit existing architecture
2.  Map existing database
3.  Map existing purchase/sales/karigar logic
4.  Centralize weight calculations
5.  Establish/extend inventory movement engine
6.  Gross/stone/net/purity/fine
7.  Opening stock
8.  Stock IN/OUT
9.  Purchase integration
10. Sales integration
11. Inventory ledger
12. Karigar issue
13. Karigar return
14. Karigar outstanding
15. Wastage
16. Mazdoori
17. Manufacturing/job cards
18. Old-gold exchange
19. Customer returns
20. Supplier returns
21. Stock adjustments
22. Tags/barcodes
23. Stone inventory
24. Valuation
25. Reports/dashboard
26. Permissions/audit hardening
27. Full integration testing
28. Production build verification

The agent may reorder implementation after discovering actual
dependencies in the repository.

------------------------------------------------------------------------

# 61. Project Checkpoints

### Checkpoint 1

Codebase understood.

### Checkpoint 2

Database and relationships mapped.

### Checkpoint 3

Calculation engine works.

### Checkpoint 4

Inventory movement engine works.

### Checkpoint 5

Purchase and stock are connected.

### Checkpoint 6

Sales and stock are connected.

### Checkpoint 7

Karigar and manufacturing are connected.

### Checkpoint 8

Exchange and returns are connected.

### Checkpoint 9

Tags, stones, valuation, reports are connected.

### Checkpoint 10

Full end-to-end testing passes.

Do not move past a checkpoint if the underlying architecture is
fundamentally broken.

------------------------------------------------------------------------

# 62. Diagnostic/Reconciliation Tools

The system should be able to identify:

-   stock summary vs ledger mismatch
-   negative stock
-   duplicate active tags
-   purchase without inventory movement
-   sale without inventory movement
-   return without reversal movement
-   karigar issue without responsibility balance
-   karigar return exceeding issue
-   job that cannot reconcile
-   posted document missing required movement
-   inconsistent fine-weight calculation

These can be implemented as admin reports, validation services,
commands, or diagnostics depending on the existing architecture.

------------------------------------------------------------------------

# 63. Security

Never commit:

-   passwords
-   API keys
-   database credentials
-   production secrets
-   private keys
-   `.env` files

Use environment variables.

Never put secrets in frontend code.

Never log passwords or access tokens.

------------------------------------------------------------------------

# 64. Error Handling

User-facing errors must be understandable.

Instead of exposing raw SQL/framework errors, show a meaningful message
such as:

> This item is already sold and cannot be sold again.

Technical details may remain in server logs.

Never silently ignore stock errors.

------------------------------------------------------------------------

# 65. Core User Questions the Finished System Must Answer

A jewellery shop user should be able to determine:

-   What is currently in stock?
-   How much gross weight?
-   How much net metal?
-   How much fine gold?
-   What purity?
-   How many pieces?
-   Which tags are available?
-   Which items are sold?
-   How much gold is with each karigar?
-   Which jobs are open?
-   What was purchased?
-   What was sold?
-   What was returned?
-   What old gold was received?
-   What was used in manufacturing?
-   What was the wastage?
-   What is the current valuation?
-   Which transaction created the current stock?
-   Who performed the transaction?
-   When did it happen?

------------------------------------------------------------------------

# 66. Architecture Diagram

The target conceptual architecture is:

``` text
                     ┌─────────────────────┐
                     │ ITEM / TAG / SKU    │
                     └──────────┬──────────┘
                                │
          ┌─────────────────────┼──────────────────────┐
          │                     │                      │
          v                     v                      v
   GROSS / STONE / NET      PURITY / FINE        STONE INVENTORY
          │                     │                      │
          └─────────────────────┼──────────────────────┘
                                v
                     ┌─────────────────────┐
                     │ INVENTORY ENGINE    │
                     │ IN / OUT / ADJUST   │
                     └──────────┬──────────┘
                                │
       ┌────────────┬───────────┼────────────┬─────────────┐
       │            │           │            │             │
       v            v           v            v             v
   PURCHASE       SALES      KARIGAR     MANUFACTURING  EXCHANGE
       │            │           │            │             │
       │            │       ┌───┴───┐        │        ┌────┴────┐
       │            │       │       │        │        │         │
       │            │       v       v        v        v         v
       │            │    ISSUE    RETURN   OUTPUT   OLD GOLD  NEW ITEM
       │            │       │       │        │        IN        OUT
       │            │       └───┬───┘        │
       │            │           v            │
       │            │     OUTSTANDING        │
       │            │       / WASTAGE        │
       │            │           │            │
       └────────────┴───────────┴────────────┘
                                │
                                v
                     ┌─────────────────────┐
                     │ INVENTORY LEDGER    │
                     └──────────┬──────────┘
                                │
          ┌─────────────────────┼──────────────────────┐
          v                     v                      v
     STOCK SUMMARY           VALUATION             REPORTS
          │
          v
     DASHBOARD / UI
```

------------------------------------------------------------------------

# 67. Final Development Standard

The objective is NOT:

> "Create 23 screens."

The objective is:

> "Create one connected jewellery business system in which every real
> transaction correctly changes the relevant inventory, responsibility,
> financial reference, ledger, reports, and audit trail."

A real transaction must be traceable end-to-end:

``` text
Purchase / Opening Stock
        ↓
     Inventory
        ↓
Manufacturing / Karigar
        ↓
 Finished Jewellery
        ↓
       Sale
        ↓
     Invoice
        ↓
Return / Exchange if applicable
        ↓
   Inventory Ledger
        ↓
 Reports / Valuation / Audit
```

------------------------------------------------------------------------

# 68. Final AI Agent Instruction

**DO NOT START CODING IMMEDIATELY.**

First:

``` text
READ REPOSITORY
      ↓
UNDERSTAND ARCHITECTURE
      ↓
UNDERSTAND DATABASE
      ↓
UNDERSTAND EXISTING BUSINESS LOGIC
      ↓
UNDERSTAND FRONTEND
      ↓
UNDERSTAND CURRENT FLOWS
      ↓
MAP EXISTING MODULES
      ↓
IDENTIFY GAPS
      ↓
DESIGN INTEGRATION
      ↓
IMPLEMENT CENTRAL CALCULATIONS
      ↓
IMPLEMENT/EXTEND INVENTORY ENGINE
      ↓
CONNECT EXISTING MODULES
      ↓
ADD ONLY MISSING MODULES
      ↓
TEST INDIVIDUAL MODULES
      ↓
TEST END-TO-END FLOWS
      ↓
VERIFY BUILD
      ↓
VERIFY NO REGRESSIONS
      ↓
DECLARE COMPLETE ONLY AFTER VERIFICATION
```

### Non-negotiable rules

1.  **Read before writing.**
2.  **Search before creating.**
3.  **Reuse before duplicating.**
4.  **Do not rewrite working modules without justification.**
5.  **Do not create disconnected CRUD screens.**
6.  **Do not duplicate calculations.**
7.  **Do not change stock without a traceable movement.**
8.  **Do not silently overwrite finalized historical transactions.**
9.  **Do not invent missing business data.**
10. **Do not invent valuation/exchange/wastage rules when the business
    rule is unknown.**
11. **Use atomic database transactions for stock-changing operations.**
12. **Keep server-side validation authoritative.**
13. **Preserve existing permissions and audit architecture.**
14. **Do not expose or commit secrets.**
15. **Test the actual business flows, not just whether pages compile.**
16. **Do not declare a module complete until its connected downstream
    effects are verified.**
17. **The existing codebase must be understood before implementation
    begins.**
18. **The final result must function as one connected jewellery
    inventory system, not 23 independent modules.**
