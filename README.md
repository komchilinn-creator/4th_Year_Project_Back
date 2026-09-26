# AttendQR Backend

## Run

1. Start Apache and MySQL in XAMPP.
2. Import `database/schema.sql` in phpMyAdmin, or run:
   `C:\xampp2\mysql\bin\mysql.exe -u root < database\schema.sql`
3. Open `http://localhost/4th_Year_Pj_Backend/public/index.php?action=health`.

For an existing database, apply migrations in numeric order. Apply `database/migrations/004_teacher_classes.sql` once to add teacher/session class context and the hardcoded 3IT subjects.

The default XAMPP user configuration is in `config/database.php`. Change it if your MySQL root account has a password.

## Login rules

Students are registered to one device using the client device UUID; administrators can clear that registration with `admin/device/reset`. Teacher logins are multi-device: each successful login receives its own API token and does not invalidate a teacher's sessions on other devices. Logging out removes only the current device's token.

## Initial administrator

- Username: `admin`
- Password: `admin123`

Change this password before deploying anywhere beyond local development.

## Implemented API actions

`register`, `login`, `logout`, `me`, `student/profile`, `student/attendance`,
`student/schedule`, `student/scan`, `attendance/create`, `attendance/live`,
`reports/monthly`, `admin/users`, `admin/verify`, `admin/device/reset`,
`admin/subject`, and `subjects`.
