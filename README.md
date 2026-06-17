# Smart Agricultural Decision Support System (Agri-DSS)

The Smart Agricultural Decision Support System (Agri-DSS) is a web-based application designed to help farmers, DA (Department of Agriculture) officers, and extension workers make data-driven decisions. It utilizes the **Apriori Association Rule Mining Algorithm** via a Python-based engine to discover pattern relationships between crop types, planting seasons, geographic locations, and interventions. It also features resource allocation guides, data import/export capabilities, and system logs audit trails.

---

## 📋 Table of Contents
1. [Prerequisites](#-prerequisites)
2. [Project Path Constraint (Important)](#-project-path-constraint-important)
3. [Step-by-Step Installation Guide](#-step-by-step-installation-guide)
   - [Step 1: Clone the Project](#step-1-clone-the-project)
   - [Step 2: Database Import](#step-2-database-import)
   - [Step 3: Install PHP Dependencies (Composer)](#step-3-install-php-dependencies-composer)
   - [Step 4: Set Up Python Virtual Environment](#step-4-set-up-python-virtual-environment)
4. [🔑 Default Login Credentials](#-default-login-credentials)
5. [🖥️ How to Run & Use the System](#️-how-to-run--use-the-system)
6. [🛠️ Troubleshooting](#️-troubleshooting)

---

## 🛠️ Prerequisites

To run this project locally, ensure you have the following software installed:
- **XAMPP** (includes Apache, MySQL/MariaDB, PHP 8.x)
- **Composer** (PHP dependency manager)
- **Python 3.8 or higher** (with pip)
- **Git** (for version control)

---

## ⚠️ Project Path Constraint (Important)

The application communicates with the Python AI engine via an absolute execution path. By default, the system is configured to run from the XAMPP directory:
`C:\xampp\htdocs\agricultural_dss\`

> [!IMPORTANT]
> To avoid editing path variables, **install or clone the project folder directly inside `C:\xampp\htdocs\` and name it `agricultural_dss`**.
> If you choose to host it in a different directory or drive, you must open [trigger_apriori.php](file:///c:/xampp/htdocs/agricultural_dss/actions/trigger_apriori.php) and adjust these lines:
> ```php
> $python_exec = 'C:\\your-custom-path\\.venv\\Scripts\\python.exe';
> $python_script = 'C:\\your-custom-path\\python_engine\\apriori_engine.py';
> ```

---

## 🚀 Step-by-Step Installation Guide

### Step 1: Clone the Project
Open your terminal (or Command Prompt / Git Bash) and run:
```bash
cd C:\xampp\htdocs
git clone https://github.com/E1mar-cay/AgriculturalDecisionSupportSystem.git agricultural_dss
cd agricultural_dss
```

### Step 2: Database Import
1. Open your **XAMPP Control Panel** and start **Apache** and **MySQL**.
2. Open your web browser and navigate to: `http://localhost/phpmyadmin/`
3. Create a new database named **`db_agri_dss`**:
   - Click **New** in the left sidebar.
   - Enter `db_agri_dss` under Database name.
   - Choose collation `utf8mb4_general_ci` and click **Create**.
4. Select the newly created `db_agri_dss` database, then click on the **Import** tab at the top.
5. Click **Choose File** and select the SQL schema file located at:
   `C:\xampp\htdocs\agricultural_dss\database\db_agri_dss.sql`
6. Scroll down and click **Import** (or **Go** depending on your phpMyAdmin version).

### Step 3: Install PHP Dependencies (Composer)
The project uses the `setasign/fpdf` library to generate PDF exports for resource allocations.
1. Open your command line inside the project root:
   ```cmd
   cd C:\xampp\htdocs\agricultural_dss
   ```
2. Run the Composer installation command:
   ```cmd
   composer install
   ```
   *This will download dependencies and create a `vendor/` directory.*

### Step 4: Set Up Python Virtual Environment
The Apriori pattern-mining engine requires Python and specific data analysis libraries.
1. In the project root (`C:\xampp\htdocs\agricultural_dss`), create a Python virtual environment:
   ```cmd
   python -m venv .venv
   ```
2. Activate the virtual environment:
   - **Command Prompt (CMD):**
     ```cmd
     .venv\Scripts\activate
     ```
   - **PowerShell:**
     ```powershell
     .venv\Scripts\Activate.ps1
     ```
3. Install the required Python packages (Pandas, mlxtend, and MySQL connector):
   ```cmd
   pip install -r python_engine/requirements.txt
   ```

---

## 🔑 Default Login Credentials

Once the database is imported, you can log in as a **System Administrator** using:
* **URL:** `http://localhost/agricultural_dss/`
* **Username:** `admin`
* **Password:** `admin`
* **Role:** System Admin

> [!TIP]
> You can create, update, or remove users of other roles (such as `DA Officer` or `Extension Worker`) directly via the **User Management** panel inside the dashboard when logged in as Admin.

---

## 🖥️ How to Run & Use the System

1. Keep **Apache** and **MySQL** running in XAMPP.
2. Go to `http://localhost/agricultural_dss/` in your browser.
3. Sign in using the credentials listed above.
4. ** Recalculate AI Rules**:
   - Go to **System Settings** to tune Apriori parameter filters (Min Support and Min Confidence).
   - Go to **AI Forecast Rules** and click **Run Recalculation** to trigger the Python script. It will run in the background, parse the raw database records, analyze combinations, and reload with updated association rules.
5. **Manage Data**: Under **Data Management**, you can view, add, edit, delete, or upload raw farmer records (Barangay, Crop Type, Farm Size, Season, Interventions).
6. **Resource Allocation**: Set up and calculate standard inputs/seeds/fertilizers allocation per unit area and export plans as PDF tables.
7. **Audit Trails**: Under **System Logs**, administrators can inspect real-time activities and logs for security.

---

## 🛠️ Troubleshooting

- **Apriori Algorithm Fails to Recalculate or Returns Error:**
  - Make sure the virtual environment `.venv` was created in `C:\xampp\htdocs\agricultural_dss\`.
  - Check that all libraries are installed inside the virtual environment by running `pip list` with the venv activated.
  - Verify that the Python executable path in `actions/trigger_apriori.php` matches your local system.
- **Database Connection Error:**
  - Verify your MySQL connection parameters in `includes/db_connect.php`. If your MySQL server runs on a different port or username/password, update them there.
