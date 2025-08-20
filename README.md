
<img width="932" height="517" alt="Screenshot 2025-08-20 at 11 22 01" src="https://github.com/user-attachments/assets/c372eea7-82f8-4096-ba7e-09213058363b" />

# NBN Speed Test

This project runs internet speed tests on a Raspberry Pi using Ookla Speedtest CLI, stores results in a MySQL database, and shows them on a web dashboard.

## Requirements

-   Raspberry Pi with Raspberry Pi OS
-   Python 3.7+
-   MySQL/MariaDB
-   Web server with PHP 7.4+
-   Internet connection

## Setup

### 1. Install Speedtest CLI

```bash
curl -s https://packagecloud.io/install/repositories/ookla/speedtest-cli/script.deb.sh | sudo bash
sudo apt install speedtest

```

To use a specific network interface (e.g., Wi-Fi `wlan0`):

```bash
speedtest --interface wlan0

```

### 2. Set Up Database

1.  Log into MySQL:
    
    ```bash
    mysql -u root -p
    
    ```
    
2.  Create database:
    
    ```sql
    CREATE DATABASE speedtest;
    
    ```
    
3.  Run `database.sql`:
    
    ```bash
    mysql -u root -p speedtest < database.sql
    
    ```
    
4.  Create user:
    
    ```sql
    CREATE USER 'speedtest_user'@'localhost' IDENTIFIED BY 'your_password';
    GRANT ALL ON speedtest.* TO 'speedtest_user'@'localhost';
    FLUSH PRIVILEGES;
    
    ```
    

### 3. Configure Python Script (`active_nbn.py`)

Edit these lines:

-   `SERVER_URL`: Set to your `receive.php` URL (e.g., `https://yourdomain.com/nbn/receive.php`).
-   `API_KEY`: Match the key in `receive.php` (use [https://www.uuidgenerator.net/version4](https://www.uuidgenerator.net/version4) for a new one).
-   To change network interface, edit:
    
    ```python
    cp = subprocess.run(
        ["speedtest", "--format=json", "--accept-license", "--accept-gdpr", "--interface=wlan0"],
        capture_output=True, text=True, check=True
    )    
    ```
    
    Replace `eth0` with your interface (e.g., `wlan0`) if required.

### 4. Configure PHP Receiver (`receive.php`)

Update:

-   `$dbHost`: Database host (e.g., `localhost`).
-   `$dbName`: Database name (e.g., `speedtest`).
-   `$dbUser`: Database user (e.g., `speedtest_user`).
-   `$dbPass`: Database password.
-   `$API_KEY`: Match the key in `active_nbn.py`.

### 5. Configure PHP Data Endpoint (`data.php`)

Update:

-   `$dbHost`: Database host.
-   `$dbName`: Database name.
-   `$dbUser`: Database user.
-   `$dbPass`: Database password.
-   `$API_KEY`: Match the key in `index.html`.

### 6. Configure Web Dashboard (`index.html`)

Update the `X-API-KEY` in the `fetch` call:

```javascript
headers: { 'X-API-KEY': '491cc49c-94ea-410a-8adf-1ac79027771f' }

```

Match the `$API_KEY` in `data.php` (use [https://www.uuidgenerator.net/version4](https://www.uuidgenerator.net/version4) for a new one).

### 7. Run Everything

1.  Place `receive.php`, `data.php`, and `index.html` in your web server’s `nbn` folder (e.g., `/var/www/html/nbn/`).
2.  Ensure HTTPS is enabled.
3.  Run the Python script:
    
    ```bash
    python3 active_nbn.py
    
    ```
    
4.  Schedule it with cron (e.g., every 30 minutes):
    
    ```bash
    crontab -e
    */30 * * * * /usr/bin/python3 /path/to/active_nbn.py
    
    ```
    
5.  View the dashboard at `https://yourdomain.com/nbn/index.html`.

## Notes

-   Keep API keys secure and match them across files.
-   Dashboard shows hourly data (GMT+10) for 24h, 7d, 30d, or custom ranges.
-   Protect `receive.php` and `data.php` from unauthorized access.
