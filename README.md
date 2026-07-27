# AttendQR Backend

## Run

1. Start Apache and MySQL in XAMPP.
2. Import `database/schema.sql` in phpMyAdmin, or run:
   `C:\xampp2\mysql\bin\mysql.exe -u root < database\schema.sql`
3. Open `http://localhost/4th_Year_Pj_Backend/public/index.php?action=health`.

The default XAMPP user configuration is in `config/database.php`. Change it if your MySQL root account has a password.

## Initial administrator

- Username: `admin`
- Password: `admin123`

Change this password before deploying anywhere beyond local development.

## Implemented API actions

`register`, `login`, `logout`, `me`, `student/profile`, `student/attendance`,
`student/schedule`, `student/scan`, `attendance/create`, `attendance/live`,
`reports/monthly`, `admin/users`, `admin/verify`, `admin/device/reset`,
`admin/subject`, and `subjects`.
