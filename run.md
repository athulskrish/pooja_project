# Run Zippy on Your Laptop (Git + XAMPP)

## Part 1: Install the tools (one time only)

**1. Install XAMPP**
Download from https://www.apachefriends.org, install with the default options, and keep the install folder as `C:\xampp`.

**2. Install Git**
Download from https://git-scm.com/download/win and click **Next** through the installer with the default options.

## Part 2: Download the project with Git

**3. Open Git CMD**
Press the Windows key, type **Git CMD**, and press Enter. A black window opens.

**4. Go to the XAMPP website folder**
Type this and press Enter:
```
cd /d C:\xampp\htdocs
```

**5. Clone the project**
Type `git clone`, then a space, then your repo link. For example:
```
https://github.com/athulskrish/pooja_project.git
```
To paste in Git CMD, use **right-click** or **Shift + Insert**. Wait until it says "done".

**6. Find your project folder name**
Type:
```
dir
```
You will see a new folder named after your repo. Open it in File Explorer (`C:\xampp\htdocs\repo-name`) and check:
- If you see `index.php`, `admin`, `customer` right away, that is your project folder.
- If you only see another folder called `pooja_project` inside, then **that inner folder** is your project folder.

Write down the path from `htdocs` to the folder with `index.php`. For example, `repo-name` or `repo-name/pooja_project`. You will use it in the links below as **YOUR-FOLDER**.

## Part 3: Start XAMPP

**7.** Open the **XAMPP Control Panel**.

**8.** Click **Start** next to **Apache**.

**9.** Click **Start** next to **MySQL**.

Both should turn green. If Apache won't start, another program is using the port. Close Skype or similar apps and try again.

## Part 4: Create / Reset the database

**10.** If resetting the database, you can either:
- Drop `zippy_db` in `http://localhost/phpmyadmin` and create it again with collation `utf8mb4_unicode_ci`, OR
- Simply run Step 13 below (`database/seed.php`), which automatically drops, recreates, and seeds the entire database fresh!

## Part 5: Fill the database with data

**13.** Open this link in your browser (replace `YOUR-FOLDER` with the name from step 6):
```
http://localhost/YOUR-FOLDER/database/seed.php
```
Or run in terminal / CMD from the project folder:
```
php database/seed.php
```
Wait for it to finish. This automatically recreates the database, all 17 tables, and the 10 demo orders across 3 customers and 3 drivers.

## Part 6: Open the website

**14.** Go to:
```
http://localhost/YOUR-FOLDER/
```
This main page has direct links to the Customer, Kitchen, Delivery, and Admin portals.

## Part 7: Log in

The password for every account is **`password123`**

| Portal | Name | Login Email (or Alias) | Role |
|---|---|---|---|
| **Admin** | **Pooja** | `admin@zippy.com` (or `pooja@zippy.com`) | Administrator |
| **Customer 1** | **Athul** | `athul@example.com` (or `athul@zippy.com`) | Customer (3 active, 1 delivered order) |
| **Customer 2** | **Amal** | `amal@example.com` (or `amal@zippy.com`) | Customer (2 active, 1 delivered order) |
| **Customer 3** | **Abhijith** | `abhijith@example.com` (or `abhijith@zippy.com`) | Customer (2 active, 1 delivered order) |
| **Kitchen** | **Chef Sajeev** | `chef@zippy.com` (or `sajeev@zippy.com`) | Head Chef |
| **Delivery 1** | **Shaji** | `shaji@zippy.com` (or `rider@zippy.com`) | Delivery Captain |
| **Delivery 2** | **Kichu** | `kichu@zippy.com` | Delivery Partner |
| **Delivery 3** | **Abhi** | `abhi@zippy.com` | Delivery Partner |

Each login page also has **1-click quick-fill buttons** for instant demo login.

### Demo Orders Breakdown (10 Orders Total):
- **2 Reached Kitchen KDS (`CONFIRMED`)**: Orders `#1001` (Athul) and `#1002` (Amal)
- **3 In Kitchen Cooking (`COOKING`)**: Orders `#1003` (Abhijith), `#1004` (Athul), `#1005` (Amal)
- **2 Ready for Dispatch (`READY`)**: Orders `#1006` (Abhijith) and `#1007` (Athul)
- **3 Completed Doorstep Deliveries (`DELIVERED`)**: Orders `#1008` (Amal), `#1009` (Abhijith), `#1010` (Athul) with full audit trails & reviews.

## Next time you want to run it

You only need to do **steps 7, 8, 9, then 14**. Everything else is already set up.

## Getting the latest updates later

If the project is updated on GitHub, open Git CMD and run:
```
cd /d C:\xampp\htdocs\YOUR-FOLDER
git pull
```

## If something goes wrong

| Problem | Fix |
|---|---|
| `git` is not recognized | Git isn't installed, or close and reopen Git CMD after installing. |
| Login or "Authentication failed" during clone | The repo is private. Sign in with GitHub in the window that pops up. |
| Page not found (404) | The folder name in your link doesn't match the folder in `htdocs`. Recheck step 6. |
| Cannot connect to database | MySQL isn't green in XAMPP. |
| Tables not created message | Redo step 13. |
| Access denied for user root | You set a MySQL password earlier. In the project folder, copy `.env.example` to a new file named `.env` and write your password after `DB_PASS=`. |

If you get stuck, tell me the step number and the exact error message.