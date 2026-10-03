# Textile Management System

The project **"Textile Management System"** is a Single Page Web Application. The entire application is developed using **Vue.js** and **Laravel** framework. 

![smartmockups_kvcl31qh](https://user-images.githubusercontent.com/68781375/139470368-bc266667-210e-4161-8c38-c22c57b35531.jpg)

## Contributors

[Vraj Shah](https://github.com/vraj0112) ● [Uddhav Savani](https://github.com/uds0128) ● [Priyansh Shah](https://github.com/Priyansh42) ● [Ishan Shah](https://github.com/ishanshah1802)

## Abstract

The **Textile Management system** does have **MySQL** as database support. The database keeps all records related to inward and sell quality, customer, vendor, broker, inward, challan, invoice, credit, expenses, and many more things. The textile industry can store and manipulate data just by doing few clicks and can even generate reports and keep track on the credit and expenses as per the financial year. This application reduces the amount of manual data entry and manual calculation and gives greater efficiency which in turn saves the time.

## Scope
 
* This application fulfills all the basic requirements for managing any Textile Industry.

## Development Environment

* **Tools:** VS Code, Postman, XAMPP
* **Frameworks:** Laravel, Vue.js
* **Database:** MySQL

## Features

* **Inward Quality**
* **Sell Quality**
* **Customer**
* **Vendor**
* **Broker**
* **Inward**
* **Challan**
* **Invoice**
* **Expense**
* **Credit**
* **Bank Details**

## Demo Video

https://user-images.githubusercontent.com/68781375/140166202-976c8834-4190-4fdb-bed0-6acd6ce2b564.mp4

**Here in this repository, we have uploaded all the files that are required to develop this application.**

## Local jewelry demo

This checkout is configured to use SQLite at `database/database.sqlite`, so entries made in the app are written to disk as you save them. The database file is local and ignored by Git; it stays on this computer across app restarts and is not included in a code commit.

Start the Laravel server from the project folder:

```powershell
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) and sign in with the seeded demo admin account:

- Email: `admin@ayyubjewelers.com`
- Password: `password`

For a client walkthrough, add a customer, record an inward purchase, then open the dashboard or stock ledger to show the saved entry and updated figures. The forms save through the app API; revisit the relevant list or dashboard after saving to see the database-backed result. Customer and inward data already in the local database will remain there, so avoid running `migrate:fresh` or deleting `database/database.sqlite` before the presentation.

The sample credentials are for a local demo only. The seeded account is created by `database/seeders/JewelrySeeder.php` when setting up a database for the first time.
